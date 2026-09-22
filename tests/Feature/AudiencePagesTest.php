<?php

namespace Tests\Feature;

use App\Http\Controllers\Site\AudienceController;
use App\Models\Obligation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The corpus cut by role, sector and use case: generated, gated, and pointing at records. */
class AudiencePagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
    }

    public function test_every_audience_page_renders_its_duties_controls_and_evidence(): void
    {
        $this->get('/for')->assertOk()->assertSee('Start from who you are');
        foreach (config('content.audiences') as $slug => $page) {
            $duties = Obligation::published()->withTerm($page['taxonomy'], $page['term'])->with('policyInstrument')->get()->filter(fn ($o) => $o->policyInstrument?->published_at);
            $response = $this->get('/for/'.$slug)->assertOk()->assertSee($page['h1'])->assertSee('Which controls meet these duties?')->assertSee('Governance card');
            foreach ($duties->take(3) as $o) {
                $response->assertSee('href="'.$o->url().'"', false);
            }
            $html = $response->getContent();
            $expected = $duties->count() >= AudienceController::MIN_INDEXABLE_DUTIES ? 'index,follow' : 'noindex,follow';
            $this->assertStringContainsString('content="'.$expected, $html, "{$slug} has ".$duties->count().' duties');
            $this->assertSame(1, substr_count(str_replace(' ', '', $html), '"@type":"FAQPage"'), "{$slug} must carry exactly one FAQPage block");
        }
        $this->get('/for/no-such-audience')->assertNotFound();
    }

    public function test_indexable_audience_pages_are_in_the_sitemap_and_llms_txt(): void
    {
        $xml = $this->get('/sitemap-resources.xml')->assertOk()->getContent();
        $txt = $this->get('/llms.txt')->assertOk()->getContent();
        foreach (config('content.audiences') as $slug => $page) {
            $count = Obligation::published()->withTerm($page['taxonomy'], $page['term'])->count();
            if ($count >= AudienceController::MIN_INDEXABLE_DUTIES) {
                $this->assertStringContainsString('<loc>'.route('audiences.show', $slug).'</loc>', $xml, $slug);
            } else {
                $this->assertStringNotContainsString('<loc>'.route('audiences.show', $slug).'</loc>', $xml, $slug.' is thin and must not be advertised');
            }
            $this->assertStringContainsString(route('audiences.show', $slug), $txt);
        }
    }
}
