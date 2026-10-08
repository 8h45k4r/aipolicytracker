<?php

namespace Tests\Feature;

use App\Models\AlertChannel;
use App\Models\ChannelDelivery;
use App\Models\ContributorSubmission;
use App\Models\PolicyInstrument;
use App\Models\RecordVerification;
use App\Models\ReviewerDecision;
use App\Models\Subscriber;
use App\Models\User;
use App\Support\Admin\BulkAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The review queue and the other bulk bars from an operator's side: the form says up front
 * who may verify, keeps what was chosen when it comes back with an error, changes only the
 * fields chosen, asks before publishing, can act on everything the filters match and says
 * so honestly when a cap stops it short, and returns to the place on the page that was acted on.
 */
class ReviewQueueUxTest extends TestCase
{
    use RefreshDatabase;

    private function importData(): void
    {
        $this->seed();
        $this->artisan('policy:import');
    }

    /** On the published roster (data/reviewers/bhaskar-bhatt.yaml), so may sign a verification. */
    private function reviewer(): User
    {
        config(['aipolicytracker.admin_emails' => ['reviewer@example.test']]);

        return User::factory()->create(['email' => 'reviewer@example.test', 'name' => 'Bhaskar Bhatt']);
    }

    private function stranger(): User
    {
        config(['aipolicytracker.admin_emails' => ['stranger@example.test']]);

        return User::factory()->create(['email' => 'stranger@example.test', 'name' => 'Not On The Roster']);
    }

    // ---- review queue ------------------------------------------------------------

    public function test_the_page_header_matches_the_sidebar_and_submissions_are_decided_elsewhere(): void
    {
        $this->importData();
        $pending = ContributorSubmission::create(['type' => 'correction', 'summary' => 'Waiting one', 'status' => 'pending_review']);

        $html = $this->actingAs($this->reviewer())->get('/backend/review')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<h1[^>]*>Review queue</h1>#', $html);
        $this->assertStringContainsString('#submission-'.$pending->id, $html);
        // One canonical place for a decision: the queue carries no decision form of its own.
        $this->assertStringNotContainsString(route('backend.review.decide', $pending), $html);
        $this->assertStringNotContainsString('id="bulk-submissions"', $html);

        $this->assertMatchesRegularExpression('#<h1[^>]*>Independent checks</h1>#', $this->get('/backend/review/independent-checks')->assertOk()->getContent());
    }

    public function test_someone_off_the_roster_is_told_before_the_form_not_after_it(): void
    {
        $this->importData();

        $statusSelect = fn (string $html) => preg_match('#<select name="review_status".*?</select>#s', $html, $m) ? $m[0] : '';

        $html = $this->actingAs($this->stranger())->get('/backend/review?type=policy')->assertOk()
            ->assertSee('data-roster-notice', false)
            ->assertSee('not on the published reviewer roster')
            ->assertSee(route('reviewers'), false)
            ->assertDontSee('name="source_opened"', false)
            ->getContent();
        $this->assertStringNotContainsString('value="verified"', $statusSelect($html));
        $this->assertStringContainsString('value="needs_update"', $statusSelect($html));

        // Off the roster, every other review decision still works, and needs no attestation.
        $slug = PolicyInstrument::published()->orderBy('slug')->value('slug');
        $this->post('/backend/review/verify-many/policy', ['slugs' => [$slug], 'review_status' => 'needs_update', 'confidence_level' => 'keep'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('needs_update', PolicyInstrument::where('slug', $slug)->value('review_status'));

        $html = $this->actingAs($this->reviewer())->get('/backend/review?type=policy')->assertOk()
            ->assertDontSee('data-roster-notice', false)
            ->assertSee('name="source_opened"', false)
            ->getContent();
        $this->assertStringContainsString('value="verified"', $statusSelect($html));
    }

    public function test_a_missing_attestation_is_explained_plainly_and_the_form_keeps_its_choices(): void
    {
        $this->importData();
        $slugs = PolicyInstrument::published()->orderBy('slug')->limit(2)->pluck('slug')->all();

        $this->actingAs($this->reviewer())->from('/backend/review?type=policy')
            ->post('/backend/review/verify-many/policy', ['slugs' => $slugs, 'review_status' => 'verified', 'confidence_level' => 'high', 'notes' => 'Read in full'])
            ->assertRedirect('/backend/review?type=policy#bulk-policy')
            ->assertSessionHasErrors(['source_opened' => 'To mark every selected record verified, tick the box saying you opened the official source. A record counts as verified only once a person has read its source.']);
        $this->assertSame(0, RecordVerification::count());

        // Narrowed to the first record so it is on the page whatever the queue's order.
        $html = $this->get('/backend/review?type=policy&q='.$slugs[0])->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#value="'.preg_quote($slugs[0], '#').'" form="bulk-policy" data-bulk-item[^>]* checked>#', $html, 'the ticked rows stay ticked');
        $this->assertStringContainsString('<option value="verified" selected', $html);
        $this->assertStringContainsString('<option value="high" selected', $html);
        $this->assertStringContainsString('value="Read in full"', $html);
        $this->assertStringContainsString('2 selected', $html);
    }

    public function test_keep_current_changes_only_the_field_that_was_chosen(): void
    {
        $this->importData();
        [$a, $b] = PolicyInstrument::published()->orderBy('slug')->limit(2)->get()->all();
        $a->forceFill(['review_status' => 'pending_review', 'confidence_level' => 'high', 'reviewed_by' => null, 'last_verified_at' => null])->save();
        $b->forceFill(['review_status' => 'pending_review', 'confidence_level' => 'low', 'reviewed_by' => null, 'last_verified_at' => null])->save();
        $this->actingAs($this->reviewer());

        // Verify both, keep each one's confidence.
        $this->post('/backend/review/verify-many/policy', ['slugs' => [$a->slug, $b->slug], 'review_status' => 'verified', 'confidence_level' => 'keep', 'source_opened' => 1])
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_starts_with($m, '2 policy instruments marked verified · confidence kept for 2 · by Bhaskar Bhatt.'));
        $this->assertSame(['verified', 'high'], [$a->fresh()->review_status, $a->fresh()->confidence_level]);
        $this->assertSame(['verified', 'low'], [$b->fresh()->review_status, $b->fresh()->confidence_level]);

        // Change only the confidence of a verified record: its date and signature stay.
        $b->forceFill(['last_verified_at' => '2026-01-15', 'reviewed_by' => 'AI Policy Tracker'])->save();
        $this->post('/backend/review/verify-many/policy', ['slugs' => [$b->slug], 'review_status' => 'keep', 'confidence_level' => 'medium'])
            ->assertSessionHas('success', fn ($m) => str_starts_with($m, '1 Policy instrument updated · review status kept · confidence medium'));
        $b->refresh();
        $this->assertSame(['verified', 'medium', 'AI Policy Tracker', '2026-01-15'], [$b->review_status, $b->confidence_level, $b->reviewed_by, $b->last_verified_at->toDateString()]);
        $this->assertDatabaseHas('record_verifications', ['record_slug' => $b->slug, 'review_status' => 'verified', 'confidence_level' => 'medium', 'reviewed_by' => 'AI Policy Tracker']);

        // Keeping both is not an action.
        $this->post('/backend/review/verify-many/policy', ['slugs' => [$a->slug], 'review_status' => 'keep', 'confidence_level' => 'keep'])->assertSessionHasErrors('review_status');

        // Keep current is the default of both selects.
        $html = $this->get('/backend/review?type=policy')->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, '<option value="keep">Keep current</option>'));
    }

    public function test_publishing_and_unpublishing_ask_first_with_the_count(): void
    {
        $this->importData();
        $html = $this->actingAs($this->reviewer())->get('/backend/review?type=policy')->assertOk()->getContent();

        $this->assertStringContainsString('data-confirm="Publish {n} policy instruments? They appear on the public site, API and sitemaps at once." data-confirm-label="Publish"', $html);
        $this->assertMatchesRegularExpression('#data-confirm="Unpublish \{n\} policy instruments\?[^"]*" data-confirm-label="Unpublish" data-confirm-danger#', $html);

        // The single-row switch asks too, both ways.
        $policy = PolicyInstrument::published()->orderBy('slug')->firstOrFail();
        $policy->forceFill(['published_at' => null])->save();
        $title = $policy->short_title ?: $policy->title;
        $this->get('/backend/review?type=policy&published=no')->assertOk()
            ->assertSee('data-confirm="Publish '.e($title).'? It appears on the public site, API and sitemaps at once." data-confirm-label="Publish"', false);
    }

    public function test_an_all_matching_action_says_when_the_cap_stopped_it(): void
    {
        $this->assertSame(' Applied to 25 of 27; the limit per action is 25 — run again for the rest.', BulkAction::capNote(25, 27, 25));
        $this->assertSame('', BulkAction::capNote(3, 3, 25));
        $this->assertSame('', BulkAction::capNote(25, 25, 25));
    }

    // ---- submissions ---------------------------------------------------------------

    public function test_a_decision_is_never_preselected_and_a_decided_submission_shows_its_decision(): void
    {
        $owner = $this->reviewer();
        $pending = ContributorSubmission::create(['type' => 'correction', 'summary' => 'Still waiting', 'status' => 'pending_review']);
        $done = ContributorSubmission::create(['type' => 'correction', 'summary' => 'Already decided', 'status' => 'rejected']);

        $html = $this->actingAs($owner)->get('/backend/admin/submissions')->assertOk()->getContent();
        $this->assertStringContainsString('<select id="d-'.$pending->id.'" name="decision" class="input !min-h-0 !py-1.5" required><option value="">Choose…</option>', $html);
        $this->assertStringContainsString('<select id="bulk-submissions-decision" name="decision" class="input !min-h-0 !py-1.5 !w-auto" required><option value="">Choose…</option>', $html);
        $this->assertStringContainsString('data-decided="rejected"', $html);
        $this->assertStringContainsString('Change decision', $html);
        $this->assertSame(1, substr_count($html, 'data-decided='), 'only the decided one is folded away');

        // Sent without a choice: a plain message, back at that card.
        $this->from('/backend/admin/submissions')->post(route('backend.review.decide', $pending), ['_form' => 'submission-'.$pending->id])
            ->assertRedirect('/backend/admin/submissions#submission-'.$pending->id)
            ->assertSessionHasErrors(['decision' => 'Choose a decision before recording it.']);
        $this->assertSame(0, ReviewerDecision::count());

        $this->from('/backend/admin/submissions')->post(route('backend.review.decide', $pending), ['decision' => 'approved'])
            ->assertRedirect('/backend/admin/submissions#submission-'.$pending->id);
        $this->assertSame('approved', $pending->fresh()->status);
    }

    public function test_one_decision_can_cover_every_submission_the_filters_match(): void
    {
        $owner = $this->reviewer();
        $corrections = collect(range(1, 3))->map(fn ($i) => ContributorSubmission::create(['type' => 'correction', 'summary' => 'Correction '.$i, 'status' => 'pending_review']));
        $sources = collect(range(1, 2))->map(fn ($i) => ContributorSubmission::create(['type' => 'new_source', 'summary' => 'Source '.$i, 'status' => 'pending_review']));

        $page = $this->actingAs($owner)->get('/backend/admin/submissions?type=correction&per=1')->assertOk()->getContent();
        $this->assertStringContainsString('action="'.e(route('backend.review.decide.many', ['type' => 'correction', 'per' => 1])).'"', $page, 'the bar posts the list\'s filters');

        $this->from('/backend/admin/submissions?type=correction')
            ->post(route('backend.review.decide.many', ['type' => 'correction']), ['scope' => 'filtered', 'decision' => 'needs_information'])
            ->assertRedirect('/backend/admin/submissions?type=correction#bulk-submissions')
            ->assertSessionHas('success', fn ($m) => str_starts_with($m, '3 submissions marked needs information.'));

        $this->assertSame(3, ContributorSubmission::whereIn('id', $corrections->pluck('id'))->where('status', 'needs_information')->count());
        $this->assertSame(2, ContributorSubmission::whereIn('id', $sources->pluck('id'))->where('status', 'pending_review')->count(), 'the filter, not the table, is the selection');

        // Nothing ticked and no scope: refused, with the selection kept for the retry.
        $this->post(route('backend.review.decide.many'), ['decision' => 'approved'])->assertSessionHasErrors('ids');
    }

    // ---- subscribers and alert deliveries ---------------------------------------

    public function test_subscriber_bulk_actions_can_take_every_subscriber_the_filters_match(): void
    {
        $owner = $this->reviewer();
        Subscriber::create(['email' => 'a@example.test', 'token' => 'a', 'topics' => ['all']]);
        Subscriber::create(['email' => 'b@example.test', 'token' => 'b', 'topics' => ['all']]);
        $kept = Subscriber::create(['email' => 'c@example.test', 'token' => 'c', 'topics' => ['all'], 'confirmed_at' => now()]);

        $this->actingAs($owner)->get('/backend/admin/subscribers?state=unconfirmed')->assertOk()
            ->assertSee('class="adm-bulkbar', false)
            ->assertSee('data-confirm-danger', false);

        $this->from('/backend/admin/subscribers?state=unconfirmed')
            ->post('/backend/admin/subscribers/delete-many?state=unconfirmed', ['scope' => 'filtered'])
            ->assertRedirect('/backend/admin/subscribers?state=unconfirmed#subscribers-list')
            ->assertSessionHas('success', '2 subscribers removed.');

        $this->assertSame([$kept->id], Subscriber::pluck('id')->all());
    }

    public function test_retrying_all_matching_deliveries_stops_at_the_limit_and_says_so(): void
    {
        Http::fake(['example.org/*' => Http::response('ok', 200)]);
        config(['aipolicytracker.admin_emails' => ['owner@example.org']]);
        $owner = User::factory()->create(['email' => 'owner@example.org']);
        $channel = AlertChannel::forceCreate(['user_id' => User::factory()->create()->id, 'kind' => 'webhook', 'enabled' => true, 'secret' => 's', 'endpoint' => 'https://example.org/hook']);
        foreach (range(1, 27) as $i) {
            ChannelDelivery::forceCreate(['alert_channel_id' => $channel->id, 'payload' => ['event' => 'alert.daily'], 'status' => 'failed', 'attempts' => 5, 'last_error' => 'HTTP 500']);
        }
        $sent = ChannelDelivery::forceCreate(['alert_channel_id' => $channel->id, 'payload' => ['event' => 'alert.daily'], 'status' => 'sent', 'attempts' => 1]);

        $this->actingAs($owner)->get(route('backend.admin.alerts.index', ['status' => 'failed']))->assertOk()
            ->assertSee('data-bulk-scope="bulk-deliveries"', false)
            ->assertSee('apply to all 27 unsent deliveries matching the filters (up to 25 per action)');

        $this->from(route('backend.admin.alerts.index', ['status' => 'failed']))
            ->post(route('backend.admin.alerts.deliveries.retry.many', ['status' => 'failed']), ['scope' => 'filtered'])
            ->assertRedirect(route('backend.admin.alerts.index', ['status' => 'failed']).'#deliveries')
            ->assertSessionHas('success', '25 deliveries sent, 0 failed again. Applied to 25 of 27; the limit per action is 25 — run again for the rest.');

        $this->assertSame(2, ChannelDelivery::where('status', 'failed')->count());
        $this->assertSame(1, $sent->fresh()->attempts, 'a sent delivery is never part of the selection');
    }
}
