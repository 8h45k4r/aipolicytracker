<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sign-up form asks readers to accept terms and a privacy policy, and the
 * download gate writes `terms_accepted_at` against that acceptance. Both links
 * resolved to the About page whenever the two optional environment variables
 * were unset, which is what they were in production. An acceptance checkbox
 * pointing at a page that does not contain the terms is not consent.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_both_pages_are_published_and_indexable(): void
    {
        foreach ([route('privacy'), route('terms')] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('index,follow', false)
                ->assertSee('<link rel="canonical" href="'.$url.'"', false);
        }
    }

    public function test_the_sign_up_acceptance_links_to_the_terms_and_the_policy_themselves(): void
    {
        $html = $this->get('/register')->assertOk()->getContent();

        $this->assertStringContainsString(route('terms'), $html);
        $this->assertStringContainsString(route('privacy'), $html);
        // The regression: the checkbox used to point at /about when the optional
        // override was unset, which is how it shipped.
        $this->assertStringNotContainsString('href="'.route('about').'">terms</a>', $html);
    }

    public function test_the_footer_links_them_on_every_page_without_needing_configuration(): void
    {
        config(['aipolicytracker.links.privacy_policy' => null, 'aipolicytracker.links.terms_of_use' => null]);

        foreach (['/', route('policies.index'), route('about')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString(route('privacy'), $html, "no privacy link on {$url}");
            $this->assertStringContainsString(route('terms'), $html, "no terms link on {$url}");
        }
    }

    public function test_an_externally_hosted_policy_still_wins_when_one_is_configured(): void
    {
        config(['aipolicytracker.links.privacy_policy' => 'https://example-group.test/privacy']);

        $this->get('/register')->assertOk()->assertSee('https://example-group.test/privacy', false);
    }

    public function test_both_pages_are_in_the_sitemap(): void
    {
        $xml = $this->get('/sitemap-static.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.route('privacy').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('terms').'</loc>', $xml);
    }

    public function test_the_privacy_page_describes_the_account_data_that_is_actually_stored(): void
    {
        // A privacy policy listing data the application does not hold, or omitting
        // data it does, is worse than none. These are the columns that exist.
        $html = $this->get(route('privacy'))->assertOk()->getContent();

        foreach (['Organisation name', 'Marketing consent', 'Two-factor', 'Password'] as $claim) {
            $this->assertStringContainsString($claim, $html, "the policy does not mention {$claim}");
        }

        // Stated plainly rather than glossed, because it is the one field kept in full.
        $this->assertStringContainsString('IP address', $html);
        $this->assertStringContainsString('technical debt', $html, 'the unhashed sign-up address is disclosed, not hidden');
    }

    public function test_the_privacy_page_routes_a_request_to_a_person_however_it_is_configured(): void
    {
        config(['legal.privacy_contact' => 'privacy@example-group.test']);
        $this->get(route('privacy'))->assertOk()->assertSee('privacy@example-group.test', false);

        // Falling back to the general contact list rather than a form.
        config(['legal.privacy_contact' => null, 'aipolicytracker.contact_emails' => ['hello@example-group.test']]);
        $this->get(route('privacy'))->assertOk()->assertSee('hello@example-group.test', false);

        // With neither set the page still works and names a route that exists.
        config(['legal.privacy_contact' => null, 'aipolicytracker.contact_emails' => []]);
        $this->get(route('privacy'))->assertOk()->assertSee(route('contribute'), false);
    }

    public function test_the_governing_law_clause_appears_only_when_a_jurisdiction_is_named(): void
    {
        // Naming the wrong courts is worse than naming none, so the clause is
        // absent by default rather than guessed.
        config(['legal.governing_law' => null]);
        $this->get(route('terms'))->assertOk()->assertDontSee('have jurisdiction over any dispute', false);

        config(['legal.governing_law' => 'Nepal']);
        $this->get(route('terms'))->assertOk()->assertSee('the courts of Nepal', false);
    }

    public function test_a_malformed_effective_date_does_not_take_the_page_down(): void
    {
        config(['legal.effective_from' => 'not-a-date']);

        $this->get(route('privacy'))->assertOk();
        $this->get(route('terms'))->assertOk()->assertDontSee('Last updated', false);
    }

    public function test_the_terms_state_the_data_licence_and_that_this_is_not_legal_advice(): void
    {
        $html = $this->get(route('terms'))->assertOk()->getContent();

        $this->assertStringContainsString(config('aipolicytracker.data_license'), $html);
        $this->assertStringContainsString(config('aipolicytracker.data_license_url'), $html);
        $this->assertStringContainsString('not legal advice', $html);
    }

    public function test_a_signed_in_reader_reaches_both_pages_and_their_account_page_from_them(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('privacy'))->assertOk()->assertSee(route('profile.edit'), false);
        $this->actingAs($user)->get(route('terms'))->assertOk()->assertSee(route('profile.edit'), false);
    }
}
