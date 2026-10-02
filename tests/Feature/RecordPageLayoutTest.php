<?php

namespace Tests\Feature;

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
        $this->assertStringContainsString('btn-primary', $actions, 'Save is the primary action');

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

        $css = file_get_contents(resource_path('css/public.css'));
        $this->assertStringContainsString('@media (prefers-color-scheme: dark)', $css);
        foreach (['white', 'navy', 'paper', 'line', 'muted', 'body'] as $token) {
            $this->assertSame(2, preg_match_all('/--c-'.$token.': [\d ]+;/', $css), "--c-{$token} has a light and a dark value");
        }
    }

    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        $this->assertNotFalse($start);

        return substr($html, $start, strpos($html, $to, $start) - $start);
    }
}
