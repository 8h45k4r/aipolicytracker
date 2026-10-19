<?php

namespace Tests\Feature;

use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Services\PolicyData\FrameworkCrosswalk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Findings from the October 2026 SEO and answer-engine crawl of every sitemap URL. */
class SeoAeoAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
    }

    /** The answers a page gives in FAQ markup that a reader cannot see on it. */
    private function hiddenAnswers(string $html): array
    {
        $visible = html_entity_decode(strip_tags(preg_replace('#<script.*?</script>#s', '', $html)), ENT_QUOTES);
        $visible = preg_replace('/\s+/', ' ', $visible);
        $hidden = [];
        preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m);
        foreach ($m[1] as $raw) {
            $data = json_decode($raw, true);
            foreach ($data['@graph'] ?? [$data] as $node) {
                if (($node['@type'] ?? null) !== 'FAQPage') {
                    continue;
                }
                foreach ($node['mainEntity'] as $q) {
                    $start = mb_substr(preg_replace('/\s+/', ' ', html_entity_decode($q['acceptedAnswer']['text'], ENT_QUOTES)), 0, 50);
                    if (! str_contains($visible, $start)) {
                        $hidden[] = $q['name'];
                    }
                }
            }
        }

        return $hidden;
    }

    public function test_every_faq_answer_in_the_markup_is_shown_on_the_page(): void
    {
        foreach (['/ai-risk', '/pricing', '/developers', '/mcp', '/glossary', '/ai-regulation-asia', '/policies/eu-ai-act', '/jurisdictions/india', '/open-data'] as $path) {
            $html = $this->followingRedirects()->get($path)->assertOk()->getContent();
            $this->assertSame([], $this->hiddenAnswers($html), "{$path} has FAQ markup a reader cannot see");
        }
    }

    public function test_the_ai_risk_answer_is_computed_from_the_incident_snapshot(): void
    {
        $html = $this->get('/ai-risk')->assertOk()->getContent();
        $this->assertStringContainsString('What are the seven domains of AI risk?', $html);
        $this->assertStringNotContainsString('aI system', $html);
        $this->assertStringNotContainsString('privacy &amp; Security', $html);
    }

    public function test_llms_txt_lists_one_entry_per_line(): void
    {
        $txt = $this->get(route('llms'))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('#ai-regulation-[a-z]+ +- #', $txt, 'a list item ran into the next one');
        $this->assertSame(1, substr_count($txt, route('compare.index')."\n"));
        $this->assertStringContainsString('commercial use is allowed', $txt);
        $this->assertStringContainsString(route('pricing'), $txt);
    }

    public function test_a_jurisdiction_without_changes_does_not_link_to_an_empty_updates_page(): void
    {
        $quiet = Jurisdiction::published()->whereNotIn('id', ChangeEvent::published()->select('jurisdiction_id'))->firstOrFail();
        $html = $this->followingRedirects()->get('/jurisdictions/'.$quiet->slug)->assertOk()->getContent();
        $this->assertStringNotContainsString('href="'.route('updates.jurisdiction', $quiet->slug).'"', $html);
        $this->get(route('updates.jurisdiction.feed', $quiet->slug))->assertOk();
    }

    public function test_framework_links_point_only_at_framework_pages_that_exist(): void
    {
        $crosswalk = app(FrameworkCrosswalk::class);
        foreach (['/frameworks/compare', '/controls/ai-impact-assessment', '/controls/ai-risk-assessment'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            preg_match_all('#href="'.preg_quote(url('/frameworks/'), '#').'([a-z0-9-]+)"#', $html, $m);
            foreach (array_unique($m[1]) as $slug) {
                $this->get('/frameworks/'.$slug)->assertOk();
            }
        }
        $this->assertFalse($crosswalk->hasPage('not-a-framework'));
    }

    public function test_the_api_and_exports_say_whether_an_instrument_is_about_ai(): void
    {
        $this->getJson('/api/v1/policies/india-dpdp-act')->assertOk()->assertJsonPath('data.ai_specific', false);
        $this->getJson('/api/v1/policies/eu-ai-act')->assertOk()->assertJsonPath('data.ai_specific', true);
        $csv = $this->get(route('open-data.csv', 'policies'))->assertOk()->streamedContent();
        $this->assertStringContainsString('ai_specific', strtok($csv, "\n"));
    }
}
