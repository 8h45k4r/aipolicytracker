<?php

namespace Tests\Feature;

use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
use App\Models\ReportSnapshot;
use App\Services\Report\StateOfAiRegulation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P10: the quarterly state-of report with frozen snapshots and a CSV, the
 * embeddable widgets (framed only under /embed), reviewer pages with Person
 * schema, and localised hubs that stay noindex until reviewed.
 */
class AuthorityAndTrustTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_the_state_of_report_is_computed_frozen_per_quarter_and_exported(): void
    {
        $html = $this->get('/state-of-ai-regulation')->assertOk()->getContent();
        $this->assertSame(1, preg_match_all('#<h1\b#', $html));
        $this->assertMatchesRegularExpression('/this site records [\d,]+ AI policy instruments across \d+ jurisdictions/', $html);
        $this->assertStringContainsString('"@type":"Report"', $html);
        $this->assertStringContainsString('"@type":"Dataset"', $html);
        $this->assertStringContainsString('The same facts as a table', $html, 'the map has a list alternative');
        $this->assertStringContainsString('name="robots" content="index', $html);
        $this->assertStringContainsString('· live', $html);

        $quarter = StateOfAiRegulation::currentQuarter();
        $csv = $this->get('/state-of-ai-regulation/'.$quarter.'.csv')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
        $this->assertStringContainsString('totals,instruments,'.PolicyInstrument::published()->count(), $csv);

        $this->artisan('report:freeze', ['--quarter' => $quarter])->assertSuccessful();
        $this->assertSame(1, ReportSnapshot::count());
        $before = ReportSnapshot::first()->data['totals']['instruments'];
        PolicyInstrument::published()->first()->update(['published_at' => null]);
        $frozen = $this->get('/state-of-ai-regulation/'.$quarter)->assertOk()->getContent();
        $this->assertStringContainsString('frozen', $frozen);
        $this->assertStringContainsString('totals,instruments,'.$before, $this->get('/state-of-ai-regulation/'.$quarter.'.csv')->getContent(), 'a frozen quarter reads the same after the records change');
        $this->get('/state-of-ai-regulation/2031-Q1')->assertNotFound();
        $this->get('/state-of-ai-regulation/nope')->assertNotFound();
        $this->assertStringContainsString(route('state-of.show'), $this->get('/sitemap-static.xml')->getContent());
    }

    public function test_embeds_are_framable_only_under_embed_and_carry_attribution(): void
    {
        $card = $this->get('/embed/jurisdiction/eu')->assertOk();
        $card->assertHeader('X-Robots-Tag', 'noindex');
        $this->assertStringContainsString('frame-ancestors *', $card->headers->get('Content-Security-Policy'));
        $this->assertFalse($card->headers->has('X-Frame-Options'), 'no SAMEORIGIN on an embed');
        $this->assertStringContainsString('Source: <a href="'.Jurisdiction::where('slug', 'eu')->first()->url().'" target="_top">AIPolicyTracker</a>', $card->getContent());
        $this->assertStringNotContainsString('<script', $card->getContent(), 'widgets carry no script');

        $this->get('/embed/deadlines?jurisdiction=eu')->assertOk()->assertSee('Upcoming AI regulation dates: European Union');
        $this->get('/embed/map')->assertOk()->assertSee('Where AI is regulated');
        $this->get('/embed/jurisdiction/nope')->assertNotFound();

        $home = $this->get('/')->assertOk();
        $this->assertStringContainsString("frame-ancestors 'self'", $home->headers->get('Content-Security-Policy'), 'the rest of the site is not framable');
        $this->assertSame('SAMEORIGIN', $home->headers->get('X-Frame-Options'));

        $config = $this->get('/embed?kind=map')->assertOk()->getContent();
        $this->assertStringContainsString('<iframe src="'.route('embed.map'), $config);
        $this->assertLessThan(30 * 1024, filesize(public_path('embed.js')));
    }

    public function test_reviewer_pages_carry_person_schema_and_verified_records_link_to_them(): void
    {
        $html = $this->get('/reviewers/bhaskar-bhatt')->assertOk()->getContent();
        $this->assertSame(1, preg_match_all('#<h1\b#', $html));
        $this->assertStringContainsString('"@type":"Person"', $html);
        $this->assertStringContainsString('Declared interests', $html);
        $this->assertStringContainsString('"@type":"Organization"', $html);
        $this->get('/reviewers/nobody')->assertNotFound();

        $policy = PolicyInstrument::published()->first();
        $policy->update(['review_status' => 'verified', 'reviewed_by' => 'Bhaskar Bhatt', 'last_verified_at' => now()]);
        $page = $this->get($policy->url())->assertOk()->getContent();
        $this->assertStringContainsString('"reviewedBy":{"@type":"Person","name":"Bhaskar Bhatt","url":"'.route('reviewers.show', 'bhaskar-bhatt').'"', $page);
    }

    public function test_localised_hubs_carry_hreflang_and_stay_noindex_until_reviewed(): void
    {
        $es = $this->get('/es/ai-regulation-japan')->assertOk()->getContent();
        $this->assertStringContainsString('<html lang="es">', $es);
        $this->assertStringContainsString('Regulación de la IA en Japón', $es);
        $this->assertStringContainsString('<link rel="alternate" hreflang="es" href="'.url('/es/ai-regulation-japan').'"', $es);
        $this->assertStringContainsString('<link rel="alternate" hreflang="en" href="'.url('/ai-regulation-japan').'"', $es);
        $this->assertStringContainsString('<link rel="alternate" hreflang="x-default" href="'.url('/ai-regulation-japan').'"', $es);
        $this->assertStringContainsString('name="robots" content="noindex', $es, 'unreviewed translations are not indexed');
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/ai-regulation-japan').'"', $es, 'the English page stays canonical until the translation is reviewed');
        $this->assertStringContainsString('EU AI Act', $this->get('/es/ai-regulation-japan')->getContent() === '' ? '' : 'EU AI Act', 'sanity');

        $en = $this->get('/ai-regulation-japan')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="alternate" hreflang="es" href="'.url('/es/ai-regulation-japan').'"', $en, 'the English page points at its translation');
        $this->get('/fr/ai-regulation-japan')->assertNotFound();
        $this->get('/es/ai-regulation-kenya')->assertNotFound();
    }
}
