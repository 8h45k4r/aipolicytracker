<?php

namespace Tests\Feature;

use App\Models\ChangeEvent;
use App\Models\Obligation;
use App\Models\Subscriber;
use App\Models\TemplateVersion;
use App\Services\Templates\TemplateBuilder;
use App\Services\Templates\TemplateCatalog;
use App\Support\PageTitle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\OpenApiContract;
use Tests\TestCase;

/**
 * P5: the templates library. Files are generated from the records, carry a
 * README with version, dataset hash, date, disclaimer and licence, cite every
 * duty, are versioned only when their content changes, and download without
 * an account. The old free-tool addresses redirect here.
 */
class TemplatesLibraryTest extends TestCase
{
    use OpenApiContract;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(TemplateBuilder::DISK);
        $this->artisan('policy:import');
    }

    public function test_every_catalogued_template_has_a_definition_and_builds_files_that_open(): void
    {
        $builder = app(TemplateBuilder::class);
        foreach (TemplateCatalog::all() as $slug => $meta) {
            $this->assertNotNull(TemplateCatalog::definition($slug), "{$slug} has no definition");
            $result = $builder->build($slug);
            $v = $result['version'];
            $this->assertTrue($result['changed']);
            $this->assertSame(1, $v->version);
            $this->assertSame(array_values($meta['formats']), $v->formats(), "{$slug} ships the catalogued formats");
            foreach ($v->files as $f) {
                Storage::disk(TemplateBuilder::DISK)->assertExists($f['path']);
                $this->assertGreaterThan(2000, $f['bytes']);
            }
        }
    }

    public function test_a_workbook_has_a_readme_dropdowns_formulas_colour_rules_and_frozen_headers_and_cites_every_duty(): void
    {
        $v = app(TemplateBuilder::class)->build('ai-risk-register')['version'];
        $ss = IOFactory::load(Storage::disk(TemplateBuilder::DISK)->path($v->file('xlsx')['path']));

        $readme = $ss->getSheetByName('README');
        $this->assertNotNull($readme);
        $text = implode(' ', array_map(fn ($r) => implode(' ', array_filter($r, 'is_string')), $readme->toArray()));
        $this->assertStringContainsString('v1', $text);
        $this->assertStringContainsString($v->dataset_version, $text, 'the dataset hash is on the README sheet');
        $this->assertStringContainsString(now()->format('Y-m-d'), $text);
        $this->assertStringContainsString('not legal advice', $text);
        $this->assertStringContainsString('CC BY 4.0', $text);

        $register = $ss->getSheetByName('Register');
        $this->assertSame('A2', $register->getFreezePane());
        $this->assertNotEmpty($register->getDataValidationCollection(), 'domain and subdomain dropdowns');
        $this->assertNotEmpty($register->getConditionalStylesCollection(), 'traffic-light rules');
        $formulas = 0;
        foreach ($register->getRowIterator(2, 3) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $formulas += $cell->isFormula() ? 1 : 0;
            }
        }
        $this->assertGreaterThan(0, $formulas, 'scores are formulas');

        $duties = $ss->getSheetByName('Risk duties');
        $header = $duties->rangeToArray('A1:'.$duties->getHighestDataColumn().'1')[0];
        $refCol = array_search('Source reference', $header, true);
        $urlCol = array_search('Record', $header, true);
        $this->assertNotFalse($urlCol);
        $expected = Obligation::published()->whereIn('category', ['risk_management', 'safety_testing'])->count();
        $this->assertSame($expected, $duties->getHighestDataRow() - 1, 'one row per covered duty');
        foreach ($duties->rangeToArray('A2:'.$duties->getHighestDataColumn().$duties->getHighestDataRow()) as $r) {
            $this->assertStringStartsWith(url('/obligations/'), (string) $r[$urlCol], 'every duty row links to its record');
        }
        $this->assertNotFalse($refCol);
        $ss->disconnectWorksheets();
    }

    public function test_a_document_has_heading_styles_placeholders_and_record_links(): void
    {
        $v = app(TemplateBuilder::class)->build('acceptable-use-policy')['version'];
        $zip = new \ZipArchive;
        $zip->open(Storage::disk(TemplateBuilder::DISK)->path($v->file('docx')['path']));
        $xml = $zip->getFromName('word/document.xml');
        $rels = $zip->getFromName('word/_rels/document.xml.rels');
        $this->assertStringContainsString('w:pStyle w:val="Heading1"', $xml);
        $this->assertStringContainsString('w:pStyle w:val="Heading2"', $xml);
        $this->assertStringContainsString('[PLACEHOLDER', $xml);
        $this->assertStringContainsString('not legal advice', $xml);
        $this->assertStringContainsString(url('/obligations/'), $rels, 'citations link to the records');
        $this->assertStringContainsString($v->dataset_version, $xml);
    }

    public function test_a_rebuild_is_skipped_when_nothing_changed_and_versioned_with_a_changelog_and_change_event_when_it_did(): void
    {
        $builder = app(TemplateBuilder::class);
        $first = $builder->build('article-50-transparency-kit');
        $again = $builder->build('article-50-transparency-kit');
        $this->assertFalse($again['changed']);
        $this->assertSame($first['version']->id, $again['version']->id);
        $this->assertSame(0, ChangeEvent::where('slug', 'like', TemplateBuilder::CHANGE_SLUG_PREFIX.'%')->count(), 'the first build is not announced');

        // A duty the template covers changes: the next build is a new version with a changelog and a change event.
        $duty = Obligation::published()->whereHas('policyInstrument', fn ($p) => $p->where('slug', 'eu-ai-act'))->where('category', 'transparency')->firstOrFail();
        $duty->update(['practical_action' => 'Changed for the test: '.now()->timestamp]);
        $third = $builder->build('article-50-transparency-kit');
        $this->assertTrue($third['changed']);
        $this->assertSame(2, $third['version']->version);
        $this->assertNotEmpty($third['version']->changelog);
        $event = ChangeEvent::where('slug', TemplateBuilder::CHANGE_SLUG_PREFIX.'article-50-transparency-kit-v2')->first();
        $this->assertNotNull($event, 'a change event announces the new version');
        $this->assertSame('routine', $event->impact_level);
        $this->assertSame(route('templates.show', 'article-50-transparency-kit'), $event->official_source_url);

        // Alerts are opt-in: a subscriber to "templates" wants it, one following a country does not.
        $this->assertTrue((new Subscriber(['topics' => ['templates']]))->wants($event));
        $this->assertFalse((new Subscriber(['topics' => ['eu']]))->wants($event));
        $this->assertTrue((new Subscriber(['topics' => ['all']]))->wants($event));

        $html = $this->get('/templates/article-50-transparency-kit')->assertOk()->getContent();
        $this->assertStringContainsString('v2', $html);
        $this->assertStringContainsString($third['version']->changelog, $html, 'the version history shows what changed');
    }

    public function test_the_hub_and_detail_pages_follow_the_page_rules_and_download_needs_no_account(): void
    {
        app(TemplateBuilder::class)->build('ai-system-inventory');
        $meta = TemplateCatalog::find('ai-system-inventory');

        $hub = $this->get('/templates')->assertOk()->getContent();
        $this->assertSame(1, preg_match_all('#<h1\b#', $hub));
        $this->assertStringContainsString('<title>'.e(PageTitle::templatesHub()), $hub);
        $this->assertStringContainsString('"@type":"CollectionPage"', $hub);
        $this->assertStringContainsString('"@type":"FAQPage"', $hub);
        $this->assertStringContainsString('<link rel="canonical" href="'.route('templates.index').'"', $hub);
        $this->assertStringContainsString('name="robots" content="index', $hub);
        foreach (TemplateCatalog::all()->keys() as $slug) {
            $this->assertStringContainsString(route('templates.show', $slug), $hub, "the hub links every template ({$slug})");
        }
        $this->assertStringContainsString('name="robots" content="noindex', $this->get('/templates?type=register')->assertOk()->getContent(), 'filtered views are not indexed');

        $page = $this->get('/templates/ai-system-inventory')->assertOk()->getContent();
        $head = substr($page, 0, strpos($page, '</head>'));
        $this->assertSame(1, preg_match_all('#<h1\b#', $page));
        $this->assertStringContainsString('<title>'.e(PageTitle::template($meta)), $head);
        $this->assertLessThanOrEqual(PageTitle::MAX, mb_strlen(PageTitle::template($meta)));
        $this->assertStringContainsString('"@type":"DigitalDocument"', $page);
        $this->assertStringContainsString('"@type":"FAQPage"', $page);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $page);
        $this->assertStringContainsString('"isAccessibleForFree":true', $page);
        $this->assertStringContainsString('Built <time datetime=', $page, 'the build date is visible');
        $this->assertStringContainsString('Duties this template covers', $page);
        $this->assertStringContainsString('Legal basis', $page);
        $this->assertStringContainsString('Version history', $page);
        $this->assertStringNotContainsString('Create a free account', $page);
        $this->assertGreaterThanOrEqual(5, preg_match_all('#href="'.preg_quote(url('/obligations/'), '#').'#', $page), 'the page links the duties it covers');

        $this->get('/templates/ai-system-inventory/download?format=xlsx')->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('X-Robots-Tag', 'noindex')
            ->assertDownload('ai-system-inventory-v1.xlsx');
        $this->get('/templates/ai-system-inventory/download?format=docx')->assertOk()->assertDownload('ai-system-inventory-v1.docx');
        $this->get('/templates/ai-system-inventory/download?format=pdf')->assertNotFound();
        $this->assertSame(2, (int) TemplateVersion::for('ai-system-inventory')->sum('downloads'));
        $this->get('/templates/not-a-template')->assertNotFound();
    }

    public function test_old_free_tool_addresses_redirect_to_the_template_that_replaces_them(): void
    {
        foreach (TemplateCatalog::redirects() as $old => $new) {
            $this->get('/guides/tools/'.$old)->assertStatus(301)->assertRedirect(route('templates.show', $new));
            $this->get('/guides/tools/'.$old.'/download')->assertStatus(301);
        }
        $this->assertCount(10, TemplateCatalog::redirects());
        $this->assertStringNotContainsString('/guides/tools/ai-risk-register-template', $this->get('/guides')->assertOk()->getContent(), 'a replaced tool is no longer listed');
    }

    public function test_obligation_pages_link_the_templates_that_cover_them(): void
    {
        $duty = Obligation::published()->where('category', 'risk_management')->firstOrFail();
        $templates = TemplateCatalog::forObligation($duty->load('frameworkMappings'));
        $this->assertTrue($templates->has('ai-risk-register'));
        $html = $this->get($duty->url())->assertOk()->getContent();
        $this->assertStringContainsString('Templates that cover this duty', $html);
        $this->assertStringContainsString(route('templates.show', 'ai-risk-register'), $html);
    }

    public function test_the_library_is_in_the_sitemap_feed_llms_and_api_and_the_api_matches_its_contract(): void
    {
        app(TemplateBuilder::class)->build('ai-risk-register');
        $this->assertStringContainsString(route('sitemap.section', 'templates'), $this->get('/sitemap.xml')->assertOk()->getContent());
        $sitemap = $this->get('/sitemap-templates.xml')->assertOk()->getContent();
        $this->assertSame(TemplateCatalog::all()->count() + 1, substr_count($sitemap, '<loc>'));
        $this->assertStringContainsString('<enclosure url="'.route('templates.download', ['slug' => 'ai-risk-register', 'format' => 'xlsx']), $this->get('/templates/feed')->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')->getContent());
        $this->assertStringContainsString(route('templates.index'), $this->get('/llms.txt')->assertOk()->getContent());

        $list = $this->getJson('/api/v1/templates')->assertOk()->json();
        $this->assertMatchesOpenApi('/templates', $list);
        $this->assertCount(TemplateCatalog::all()->count(), $list['data']);
        $row = collect($list['data'])->firstWhere('slug', 'ai-risk-register');
        $this->assertSame(1, $row['version']);
        $this->assertSame(route('templates.download', ['slug' => 'ai-risk-register', 'format' => 'xlsx']), $row['files'][0]['url']);
        $this->assertCount(count(TemplateCatalog::filter(TemplateCatalog::all(), ['type' => 'register'])), $this->getJson('/api/v1/templates?type=register')->assertOk()->json('data'));

        $one = $this->getJson('/api/v1/templates/ai-risk-register')->assertOk()->json();
        $this->assertMatchesOpenApi('/templates/{slug}', $one);
        $this->assertNotEmpty($one['data']['covered_obligations']);
        $this->assertCount(1, $one['data']['versions']);
        $this->getJson('/api/v1/templates/ai-risk-register/download?format=xlsx')->assertRedirect(route('templates.download', ['slug' => 'ai-risk-register', 'format' => 'xlsx']));
        $this->getJson('/api/v1/templates/ai-risk-register/download?format=docx')->assertNotFound();
        $this->getJson('/api/v1/templates/nope')->assertNotFound();
    }

    public function test_a_change_to_the_records_reaches_the_file_on_the_next_build(): void
    {
        $builder = app(TemplateBuilder::class);
        $builder->build('human-oversight-procedure');
        $duty = Obligation::published()->where('category', 'human_oversight')->firstOrFail();
        $duty->update(['title' => 'A duty renamed for the test']);
        $v = $builder->build('human-oversight-procedure')['version'];
        $this->assertSame(2, $v->version);
        $ss = IOFactory::load(Storage::disk(TemplateBuilder::DISK)->path($v->file('xlsx')['path']));
        $this->assertStringContainsString('A duty renamed for the test', json_encode($ss->getSheetByName('Oversight duties')->toArray()));
        $ss->disconnectWorksheets();
    }
}
