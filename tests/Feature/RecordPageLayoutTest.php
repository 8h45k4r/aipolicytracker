<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordPageLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_a_policy_page_states_its_dates_once_and_leads_with_two_actions(): void
    {
        $policy = PolicyInstrument::published()->whereNotNull('applies_from')->whereNotNull('adopted_on')->firstOrFail();
        $html = $this->get($policy->url())->assertOk()->getContent();

        $this->assertStringNotContainsString('At a glance', $html, 'the key facts table is the one place for the record\'s dates');
        $this->assertSame(1, substr_count($html, '>'.$policy->adopted_on->format('j F Y').'<'), 'the adoption date is shown once');

        $actions = $this->between($html, 'data-record-actions', '</p>');
        $this->assertLessThan(strpos($html, 'data-answer-box'), strpos($html, 'data-record-actions'), 'actions sit in the header, before the answer');
        // The official text is what a reader came for: it is the one primary button, Save and Follow are secondary.
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(e($policy->official_source_url), '#').'" rel="noopener" class="btn-primary"[^>]*>Open official source</a>#', $actions);
        $this->assertSame(1, substr_count($actions, 'btn-primary'), 'one primary action');
        $this->assertMatchesRegularExpression('#class="btn-secondary[^"]*"[^>]*data-save=#', $actions, 'Save is secondary');

        foreach (['Copy link', 'Report a correction', 'How we verify'] as $label) {
            $this->assertStringContainsString('>'.$label.'<', $actions);
        }
        $this->assertDoesNotMatchRegularExpression('#class="btn-secondary"[^>]*>(Copy link|Report a correction|How we verify)#', $html, 'secondary actions are text links, not buttons');
    }

    public function test_pages_declare_both_colour_schemes_and_swap_the_logo_in_dark_mode(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="color-scheme" content="light dark">', $html);
        $this->assertStringContainsString('<source srcset="'.asset('brand/logo-on-dark.svg').'" media="(prefers-color-scheme: dark)">', $html);
        $this->assertStringContainsString('class="mt-20 bg-brand-footer text-snow/80"', $html, 'the footer uses tokens that stay dark in both schemes');

        // The logo is read aloud as the words it shows, and stays light enough to load on
        // every page: it was two 95 KB files of one-unit colour stripes before.
        $this->assertStringContainsString('alt="Artificial Intelligence Policy Tracker"', $html);
        foreach (['logo-on-light.svg', 'logo-on-dark.svg'] as $logo) {
            $svg = file_get_contents(public_path('brand/'.$logo));
            $this->assertLessThan(40_000, strlen($svg), "{$logo} is too heavy for an image on every page");
            $this->assertStringContainsString('viewBox="0 0 696 213"', $svg, "{$logo} must keep its viewBox to scale");
        }

        $css = file_get_contents(resource_path('css/public.css'));
        $this->assertStringContainsString('@media (prefers-color-scheme: dark)', $css);
        foreach (['white', 'navy', 'paper', 'line', 'muted', 'body'] as $token) {
            $this->assertSame(2, preg_match_all('/--c-'.$token.': [\d ]+;/', $css), "--c-{$token} has a light and a dark value");
        }
    }

    public function test_a_page_shows_its_questions_once(): void
    {
        $this->artisan('templates:build');
        foreach (['/templates', '/policies/eu-ai-act', '/glossary', '/methodology', route('guides.show', 'iso-42001-vs-eu-ai-act')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertLessThanOrEqual(1, substr_count($html, 'id="faq-heading"'), "{$url} prints its FAQ more than once");
        }
        $this->assertSame(1, substr_count($this->get('/templates')->getContent(), 'id="faq-heading"'));
    }

    public function test_a_policy_page_has_an_on_this_page_list_of_the_sections_it_renders(): void
    {
        $html = $this->get('/policies/eu-ai-act')->assertOk()->getContent();

        $this->assertStringContainsString('data-toc="sidebar"', $html, 'a sidebar list for desktop');
        $this->assertMatchesRegularExpression('#<details class="[^"]*lg:hidden[^"]*" data-toc="mobile">#', $html, 'a <details> jump menu for phones, no JavaScript');
        $sidebar = $this->between($html, 'data-toc="sidebar"', '</nav>');
        preg_match_all('#href="\#([a-z0-9-]+)"#', $sidebar, $m);
        $this->assertContains('obligations-heading', $m[1]);
        $this->assertContains('faq-heading', $m[1]);
        foreach ($m[1] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html, "the list points at #{$id}, which the page renders");
        }
        // A section the record lacks is not listed.
        $bare = PolicyInstrument::published()->whereNull('penalties_summary')->firstOrFail();
        $this->assertStringNotContainsString('href="#penalties-heading"', $this->get($bare->url())->assertOk()->getContent());
    }

    public function test_obligations_on_a_policy_page_are_one_line_each_and_link_to_their_own_page(): void
    {
        $policy = PolicyInstrument::where('slug', 'eu-ai-act')->with('obligations.terms')->firstOrFail();
        $html = $this->get($policy->url())->assertOk()->getContent();

        preg_match_all('#<details class="card-flat group"([^>]*)data-obligation-row>#', $html, $rows);
        $this->assertCount($policy->obligations->count(), $rows[0], 'one row per recorded obligation');
        foreach ($rows[1] as $attrs) {
            $this->assertStringNotContainsString(' open', $attrs, 'every obligation starts collapsed');
        }
        $o = $policy->obligations->first(fn ($o) => $o->applies_from !== null);
        $row = $this->between($html, 'id="obligation-'.$o->slug.'"', '</details>');
        $summary = $this->between($row, '<summary', '</summary>');
        $this->assertStringContainsString($o->is_binding ? 'Legal requirement' : 'Voluntary', $summary);
        $this->assertStringContainsString(e($o->title), $summary);
        $this->assertStringContainsString('datetime="'.$o->applies_from->toDateString().'"', $summary);
        if ($o->termsOf('actor')->isNotEmpty()) {
            $this->assertStringContainsString('data-obligation-actor', $summary);
        }
        $this->assertStringContainsString('href="'.$o->url().'"', $row, 'each row links to the obligation page');
    }

    public function test_one_verification_badge_with_a_legend_on_every_record_page(): void
    {
        $policy = PolicyInstrument::where('slug', 'eu-ai-act')->with(['obligations', 'jurisdiction'])->firstOrFail();
        $obligation = $policy->obligations->first();
        $control = Control::published()->firstOrFail();

        foreach ([$policy, $obligation, $control, $policy->jurisdiction] as $record) {
            $html = $this->get($record->url())->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, 'data-verification-badge'), $record->url().': one badge in the header');
            $this->assertStringContainsString('data-verification-state="'.$record->verificationState().'"', $html, $record->url());
            $legend = $this->between($html, 'data-verification-legend', '</details>');
            $this->assertStringContainsString('href="'.route('verification').'"', $legend, 'the legend links the verification policy');
            foreach (['Verified', 'Pending review', 'Source-linked'] as $state) {
                $this->assertStringContainsString('>'.$state.'</dt>', $legend);
            }
            $this->assertDoesNotMatchRegularExpression('#<a [^>]*>\s*<span[^>]*data-verification-badge#', $html, 'the badge is not wrapped in a link (it holds the reviewer link)');
        }

        // Each unverified state says what it means.
        $obligation->forceFill(['review_status' => 'pending_review', 'last_verified_at' => null])->save();
        $this->assertSame('pending_review', $obligation->verificationState());
        $this->get($obligation->url())->assertSee('Pending review')->assertSee('waiting for a reviewer to confirm it');
        $obligation->forceFill(['review_status' => 'needs_update'])->save();
        $this->assertSame('source_linked', $obligation->verificationState());
        $this->get($obligation->url())->assertSee('Source-linked')->assertSee('not yet confirmed by a reviewer');
    }

    public function test_an_obligation_page_leads_with_the_source_and_folds_its_questions(): void
    {
        $o = Obligation::published()->whereHas('policyInstrument', fn ($q) => $q->where('slug', 'eu-ai-act'))->firstOrFail();
        $html = $this->get($o->url())->assertOk()->getContent();

        $actions = $this->between($html, 'data-record-actions', '</p>');
        $this->assertLessThan(strpos($html, 'data-answer-box'), strpos($html, 'data-record-actions'), 'actions sit in the header');
        $this->assertStringContainsString('class="btn-primary" data-track="source_click" data-primary-source>Open official source</a>', $actions);

        $faq = $this->between($html, 'data-faq-collapsed', '</section>');
        $this->assertGreaterThan(0, substr_count($faq, '<details'), 'the questions are folded');
        $this->assertSame(substr_count($faq, '<h3'), substr_count($faq, '<details'), 'every question folds');
    }

    public function test_the_eu_ai_act_page_separates_entry_into_force_from_first_application(): void
    {
        $html = $this->get('/policies/eu-ai-act')->assertOk()->getContent();
        $answer = $this->between($html, 'data-answer>', '</p>');

        $this->assertStringNotContainsString('applied in part since 1 August 2024', $answer);
        $this->assertStringContainsString('in force since 1 August 2024; its first obligations applied from 2 February 2025', $answer);
        $this->assertMatchesRegularExpression('#Applies from \(first\)</dt>\s*<dd[^>]*>2 February 2025</dd>#', $html);
    }

    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        $this->assertNotFalse($start);

        return substr($html, $start, strpos($html, $to, $start) - $start);
    }
}
