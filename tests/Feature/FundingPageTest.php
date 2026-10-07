<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FundingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_funding_page_states_the_rules_and_says_plainly_when_there_are_no_funders(): void
    {
        config(['funding.funders' => [], 'funding.sponsor_url' => null]);
        $this->get('/funding')->assertOk()
            ->assertSee('Who pays for this, and what money cannot buy')
            ->assertSee('No advertising and no affiliate links.')
            ->assertSee('None yet')
            ->assertDontSee('data-track="sponsor_click"', false);
        $this->get('/sitemap-static.xml')->assertOk()->assertSee('/funding');
        $this->get('/')->assertOk()->assertSee(route('funding'));
    }

    public function test_funders_and_the_sponsor_link_are_listed_from_configuration(): void
    {
        config(['funding.funders' => [['name' => 'Example Foundation', 'kind' => 'grant', 'amount' => '€30,000', 'period' => '2027', 'purpose' => 'Second reviewer and verification']], 'funding.sponsor_url' => 'https://github.com/sponsors/8h45k4r']);
        $this->get('/funding')->assertOk()->assertSee('Example Foundation')->assertSee('€30,000')->assertSee('Second reviewer and verification')
            ->assertSee('https://github.com/sponsors/8h45k4r')->assertDontSee('None yet');
    }

    public function test_no_page_promotes_the_related_product(): void
    {
        foreach (['/', route('tools.applicability')] as $url) {
            $this->assertStringNotContainsString('certifyi_click', $this->get($url)->assertOk()->getContent());
        }
        $this->assertFileDoesNotExist(resource_path('views/components/site/certifyi-cta.blade.php'));
    }
}
