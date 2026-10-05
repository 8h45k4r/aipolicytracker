<?php

namespace Tests\Feature;

use App\Models\PolicyInstrument;
use App\Services\Templates\TemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_every_page_has_a_search_box_and_phones_get_a_search_button(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('action="'.route('search').'"', $html);
        $this->assertStringContainsString('role="search"', $html);
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('search'), '#').'" class="sm:hidden btn-primary[^"]*"[^>]*>.*?Search</a>#s', $html, 'on phones search is a labelled primary button, not hidden in the menu');
    }

    public function test_search_groups_results_across_records_templates_guides_and_the_glossary(): void
    {
        $html = $this->get(route('search', ['q' => 'transparency']))->assertOk()->getContent();

        foreach (['Obligations', 'Templates', 'Glossary'] as $group) {
            $this->assertStringContainsString('>'.$group.' <span', $html, "{$group} results are shown");
        }
        $this->assertStringContainsString('noindex', $html);

        $html = $this->get(route('search', ['q' => 'European Union']))->assertOk()->getContent();
        $this->assertStringContainsString('>Jurisdictions <span', $html);
        $this->assertStringContainsString(route('jurisdictions.show', 'eu'), $html);

        $template = TemplateCatalog::all()->first();
        $this->get(route('search', ['q' => $template['title']]))->assertOk()->assertSee(TemplateCatalog::url($template['slug']), false);
    }

    public function test_an_empty_or_unmatched_query_says_so_and_wildcards_are_literal(): void
    {
        $this->get(route('search'))->assertOk()->assertSee('autofocus', false)->assertDontSee('Nothing found');
        $this->get(route('search', ['q' => 'zzqqxx']))->assertOk()->assertSee('Nothing found for');
        $this->get(route('search', ['q' => 'zz%_qq']))->assertOk()->assertSee('Nothing found for');
        $this->assertSame(0, PolicyInstrument::search('_')->count(), 'an underscore is a character, not a wildcard');
    }

    public function test_the_site_search_action_points_at_the_search_page(): void
    {
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString(route('search').'?q={search_term_string}', str_replace('\\/', '/', $html));
    }
}
