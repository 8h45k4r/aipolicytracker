<?php

namespace Tests\Feature;

use App\Models\ContributorSubmission;
use App\Models\Jurisdiction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Suggest a policy" form at the foot of /policies: four fields that land in the
 * review queue as a new-policy submission, never as a published record.
 */
class PolicySuggestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Jurisdiction::create(['slug' => 'eu', 'name' => 'European Union', 'published_at' => now()]);
        Jurisdiction::create(['slug' => 'atlantis', 'name' => 'Atlantis', 'published_at' => null]);
    }

    public function test_the_policies_page_shows_the_form_with_every_published_jurisdiction(): void
    {
        $this->get('/policies')->assertOk()
            ->assertSee('Suggest a policy')
            ->assertSee('action="'.route('policies.suggest').'"', false)
            ->assertSee('<option value="eu"', false)
            ->assertSee('<option value="other"', false)
            ->assertDontSee('<option value="atlantis"', false)
            ->assertSee('maxlength="280"', false)
            ->assertSee('0/280');
    }

    public function test_a_suggestion_is_queued_for_review(): void
    {
        $this->post(route('policies.suggest'), [
            'policy_name' => 'AI Liability Directive',
            'jurisdiction' => 'eu',
            'official_source_url' => 'https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX:52022PC0496',
            'notes' => 'Withdrawn in 2025; worth recording as such.',
        ])->assertRedirect(route('policies.index').'#suggest-policy')->assertSessionHas('success');

        $s = ContributorSubmission::sole();
        $this->assertSame('new_policy', $s->type);
        $this->assertSame('pending_review', $s->status instanceof \BackedEnum ? $s->status->value : $s->status);
        $this->assertSame('jurisdiction', $s->subject_type);
        $this->assertSame('eu', $s->subject_slug);
        $this->assertSame('Suggested policy: AI Liability Directive', $s->summary);
        $this->assertSame('Withdrawn in 2025; worth recording as such.', $s->details);
        $this->assertSame('https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX:52022PC0496', $s->proposed_source_url);
        $this->assertSame('AI Liability Directive', $s->payload['policy_name']);
    }

    public function test_other_jurisdiction_is_accepted_without_a_subject(): void
    {
        $this->post(route('policies.suggest'), ['policy_name' => 'Ley de IA', 'jurisdiction' => 'other', 'official_source_url' => 'https://example.gov/ley'])
            ->assertSessionHasNoErrors();
        $s = ContributorSubmission::sole();
        $this->assertNull($s->subject_type);
        $this->assertSame('other', $s->payload['jurisdiction']);
    }

    public function test_bad_input_is_refused_and_shown_on_the_form(): void
    {
        $this->post(route('policies.suggest'), ['policy_name' => 'AI', 'jurisdiction' => 'atlantis', 'official_source_url' => 'not a url', 'notes' => str_repeat('x', 281)])
            ->assertRedirect(route('policies.index').'#suggest-policy')->assertSessionHasErrorsIn('suggest', ['policy_name', 'jurisdiction', 'official_source_url', 'notes']);
        $this->assertSame(0, ContributorSubmission::count());

        $this->post(route('policies.suggest'), ['jurisdiction' => 'eu', 'official_source_url' => 'https://example.gov'])
            ->assertSessionHasErrorsIn('suggest', ['policy_name']);

        // A reader on a filtered listing goes back to the same listing.
        $this->from(route('policies.index', ['jurisdiction' => 'eu']))->post(route('policies.suggest'), ['policy_name' => 'X'])
            ->assertRedirect(route('policies.index', ['jurisdiction' => 'eu']).'#suggest-policy');

        // The errors and what was typed come back on the page, beside the form.
        $this->followingRedirects()->post(route('policies.suggest'), ['policy_name' => 'Draft AI Bill', 'jurisdiction' => 'eu', 'official_source_url' => 'nope'])
            ->assertOk()->assertSee('The official source URL field must be a valid URL.')->assertSee('value="Draft AI Bill"', false);
    }

    public function test_the_honeypot_drops_the_submission_quietly(): void
    {
        $this->post(route('policies.suggest'), ['policy_name' => 'Spam Act', 'jurisdiction' => 'eu', 'official_source_url' => 'https://spam.example', 'website' => 'http://spam.example'])
            ->assertRedirect(route('policies.index').'#suggest-policy')->assertSessionHas('success');
        $this->assertSame(0, ContributorSubmission::count());
    }
}
