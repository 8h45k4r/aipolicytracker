<?php

namespace Tests\Feature;

use App\Models\PolicyInstrument;
use App\Models\RecordVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verification is a human act with a name on it. A reviewer may record many at
 * once, because reading a jurisdiction end to end is one act of review; what
 * cannot happen is a verification appearing without a published reviewer behind
 * it, or without the attestation that the official source was opened.
 */
class BulkVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['aipolicytracker.admin_emails' => ['reviewer@example.test']]);
        $this->seed();
        $this->artisan('policy:import');
    }

    /** The maintainer ships on the published roster, so their name is the one that may sign. */
    private function reviewer(): User
    {
        return User::factory()->create(['email' => 'reviewer@example.test', 'name' => 'Bhaskar Bhatt']);
    }

    public function test_a_reviewer_records_many_verifications_in_one_act(): void
    {
        $slugs = PolicyInstrument::published()->limit(5)->pluck('slug');
        $this->assertCount(5, $slugs);

        $this->actingAs($this->reviewer())
            ->post('/backend/review/verify-many/policy', ['slugs' => $slugs->all(), 'review_status' => 'verified', 'confidence_level' => 'high', 'source_opened' => 1])
            ->assertRedirect();

        foreach ($slugs as $slug) {
            $policy = PolicyInstrument::where('slug', $slug)->firstOrFail();
            $this->assertSame('verified', $policy->review_status);
            $this->assertSame('Bhaskar Bhatt', $policy->reviewed_by);
            $this->assertNotNull($policy->last_verified_at);
        }
        // Each one is its own stored decision, so it survives the next import and exports to data/.
        $this->assertSame(5, RecordVerification::where('review_status', 'verified')->count());
        $this->artisan('policy:import');
        // The shipped data is signed by the editorial desk; these five carry the reviewer's own name.
        $this->assertSame(5, PolicyInstrument::published()->where('review_status', 'verified')->where('reviewed_by', 'Bhaskar Bhatt')->count());
    }

    public function test_the_attestation_is_required_for_the_whole_selection(): void
    {
        $slugs = PolicyInstrument::published()->limit(3)->pluck('slug')->all();

        $this->actingAs($this->reviewer())
            ->post('/backend/review/verify-many/policy', ['slugs' => $slugs, 'review_status' => 'verified', 'confidence_level' => 'high'])
            ->assertSessionHasErrors('source_opened');

        $this->assertSame(0, RecordVerification::count());
    }

    public function test_a_name_that_is_not_on_the_published_roster_cannot_verify_anything(): void
    {
        config(['aipolicytracker.admin_emails' => ['stranger@example.test']]);
        $stranger = User::factory()->create(['email' => 'stranger@example.test', 'name' => 'Not On The Roster']);
        $slugs = PolicyInstrument::published()->limit(3)->pluck('slug')->all();

        $this->actingAs($stranger)
            ->post('/backend/review/verify-many/policy', ['slugs' => $slugs, 'review_status' => 'verified', 'confidence_level' => 'high', 'source_opened' => 1])
            ->assertSessionHasErrors('source_opened');

        $this->assertSame(0, RecordVerification::count());
        $this->assertSame(0, PolicyInstrument::published()->where('reviewed_by', 'Not On The Roster')->count());
    }

    public function test_the_public_pages_count_only_what_a_named_reviewer_confirmed(): void
    {
        $slugs = PolicyInstrument::published()->limit(4)->pluck('slug')->all();
        $total = PolicyInstrument::published()->count();
        // Start from nothing confirmed, whatever the shipped data says, so the count is this test's.
        PolicyInstrument::query()->update(['review_status' => 'pending_review', 'reviewed_by' => null, 'last_verified_at' => null]);

        $home = $this->get('/')->assertOk();
        $home->assertSee('Confirmed by a named reviewer');
        $this->assertStringContainsString('0<span class="text-brand-muted"> / '.$total, $home->getContent());

        $this->actingAs($this->reviewer())
            ->post('/backend/review/verify-many/policy', ['slugs' => $slugs, 'review_status' => 'verified', 'confidence_level' => 'high', 'source_opened' => 1])
            ->assertRedirect();

        $this->assertStringContainsString('4<span class="text-brand-muted"> / '.$total, $this->get('/')->assertOk()->getContent());
        $this->get('/reviewers')->assertOk()->assertSee('Bhaskar Bhatt');
    }
}
