<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\ChangeEvent;
use App\Models\ContributorSubmission;
use App\Models\Control;
use App\Models\PolicyInstrument;
use App\Models\RecordVerification;
use App\Models\ReviewerDecision;
use App\Models\User;
use App\Services\PolicyData\PolicyDataRepository;
use App\Services\Review\ReviewableTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * The review queue works on selections: the ticked rows, or everything the current
 * filter matches. Every reviewable kind gets the same treatment, and a decision made
 * in the queue can be written back into the file that holds the record.
 */
class ReviewQueueTest extends TestCase
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
    private function owner(): User
    {
        return User::factory()->create(['email' => 'reviewer@example.test', 'name' => 'Bhaskar Bhatt']);
    }

    public function test_the_queue_renders_every_reviewable_kind_with_its_filters(): void
    {
        $this->actingAs($this->owner());
        foreach (ReviewableTypes::keys() as $type) {
            $this->get('/backend/review?type='.$type)->assertOk()->assertSee('Select every '.ReviewableTypes::label($type).' on this page')->assertSee('Save review for selected');
            $this->get('/backend/review?type='.$type.'&review=pending_review&published=yes&q=a')->assertOk();
        }
        $this->get('/backend/review?type=policy&q=zzzznothing')->assertOk()->assertSee('No policy instruments match');
        $this->get('/backend/review?type=nonsense')->assertOk()->assertSee('Select every Policy instrument on this page');
    }

    public function test_every_reviewable_kind_can_be_verified_in_bulk(): void
    {
        $this->actingAs($this->owner());
        foreach (ReviewableTypes::keys() as $type) {
            $model = ReviewableTypes::model($type);
            $model::query()->update(['review_status' => 'pending_review', 'reviewed_by' => null, 'last_verified_at' => null]);
            $slugs = $model::query()->limit(2)->pluck('slug')->all();
            $this->assertNotEmpty($slugs, $type);

            $this->post('/backend/review/verify-many/'.$type, ['slugs' => $slugs, 'review_status' => 'verified', 'confidence_level' => 'high', 'source_opened' => 1])
                ->assertRedirect()->assertSessionHas('success');

            foreach ($slugs as $slug) {
                $row = $model::where('slug', $slug)->firstOrFail();
                $this->assertSame('verified', $row->review_status, $type);
                $this->assertSame('Bhaskar Bhatt', $row->reviewed_by, $type);
                $this->assertNotNull($row->last_verified_at, $type);
                $this->assertDatabaseHas('record_verifications', ['record_type' => $type, 'record_slug' => $slug, 'review_status' => 'verified']);
            }
        }
        // The decisions outlive a re-import for every kind, not only policies.
        $this->artisan('policy:import');
        foreach (ReviewableTypes::keys() as $type) {
            $this->assertSame(2, ReviewableTypes::model($type)::where('reviewed_by', 'Bhaskar Bhatt')->where('review_status', 'verified')->count(), $type);
        }
    }

    public function test_the_whole_filtered_set_can_be_acted_on_in_one_request(): void
    {
        Control::query()->update(['review_status' => 'pending_review', 'reviewed_by' => null]);
        Control::query()->limit(3)->update(['review_status' => 'needs_update']);
        $pending = Control::where('review_status', 'pending_review')->count();
        $this->assertGreaterThan(0, $pending);

        $this->actingAs($this->owner())
            ->post('/backend/review/verify-many/control', ['scope' => 'filtered', 'review' => 'pending_review', 'review_status' => 'verified', 'confidence_level' => 'medium', 'source_opened' => 1])
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_starts_with($m, $pending.' controls marked verified'));

        $this->assertSame(0, Control::where('review_status', 'pending_review')->count());
        $this->assertSame(3, Control::where('review_status', 'needs_update')->count(), 'the filter, not the whole table, is the selection');
        $this->assertSame($pending, RecordVerification::where('record_type', 'control')->count());
    }

    public function test_the_attestation_and_the_roster_still_gate_a_bulk_verification(): void
    {
        $slugs = ChangeEvent::query()->limit(2)->pluck('slug')->all();
        $this->actingAs($this->owner())
            ->post('/backend/review/verify-many/change', ['slugs' => $slugs, 'review_status' => 'verified', 'confidence_level' => 'high'])
            ->assertSessionHasErrors('source_opened');

        config(['aipolicytracker.admin_emails' => ['stranger@example.test']]);
        $stranger = User::factory()->create(['email' => 'stranger@example.test', 'name' => 'Not On The Roster']);
        $this->actingAs($stranger)
            ->post('/backend/review/verify-many/change', ['slugs' => $slugs, 'review_status' => 'verified', 'confidence_level' => 'high', 'source_opened' => 1])
            ->assertSessionHasErrors('source_opened');

        $this->assertSame(0, RecordVerification::count());
        $this->post('/backend/review/verify-many/nonsense', ['slugs' => $slugs, 'review_status' => 'verified', 'confidence_level' => 'high', 'source_opened' => 1])->assertNotFound();
    }

    public function test_publishing_many_switches_records_and_their_duties_together(): void
    {
        $policies = PolicyInstrument::published()->has('obligations')->limit(2)->get();
        $this->assertCount(2, $policies);
        $slugs = $policies->pluck('slug')->all();

        $this->actingAs($this->owner())
            ->post('/backend/review/publish-many/policy', ['slugs' => $slugs, 'publish' => 0])
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_starts_with($m, 'Unpublished 2 policy instruments'));
        foreach ($policies as $p) {
            $this->assertNull($p->fresh()->published_at);
            $this->assertSame(0, $p->obligations()->whereNotNull('published_at')->count());
            $this->get($p->url())->assertNotFound();
        }

        $this->post('/backend/review/publish-many/policy', ['slugs' => $slugs, 'publish' => 1])->assertRedirect();
        foreach ($policies as $p) {
            $this->assertNotNull($p->fresh()->published_at);
            $this->assertSame(0, $p->obligations()->whereNull('published_at')->count());
        }
    }

    public function test_one_decision_can_cover_many_submissions(): void
    {
        $ids = collect(range(1, 3))->map(fn ($i) => ContributorSubmission::create(['type' => 'correction', 'summary' => 'Correction '.$i, 'status' => 'pending_review'])->id)->all();
        $left = ContributorSubmission::create(['type' => 'correction', 'summary' => 'Untouched', 'status' => 'pending_review']);

        $this->actingAs($this->owner())
            ->post('/backend/review/submissions/decide-many', ['ids' => $ids, 'decision' => 'approved', 'notes' => 'Checked against the gazette', 'public_note' => 'Corrected.'])
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_starts_with($m, '3 submissions marked approved'));

        $this->assertSame(3, ContributorSubmission::whereIn('id', $ids)->where('status', 'approved')->count());
        $this->assertSame(3, ReviewerDecision::whereIn('contributor_submission_id', $ids)->where('decision', 'approved')->where('public_note', 'Corrected.')->count());
        $this->assertSame('pending_review', $left->fresh()->status);
        $this->get('/backend/admin/submissions')->assertOk()->assertSee('Decide the selected submissions');
    }

    public function test_bulk_routes_need_the_same_capability_as_the_single_ones(): void
    {
        $reviewer = User::factory()->create(['email' => 'r@example.test', 'name' => 'Bhaskar Bhatt', 'admin_role' => AdminRole::Reviewer->value, 'two_factor_confirmed_at' => now()]);
        $slugs = PolicyInstrument::query()->limit(1)->pluck('slug')->all();

        $this->actingAs($reviewer)->withSession(['admin.two_factor_passed_at' => now()->timestamp])
            ->post('/backend/review/publish-many/policy', ['slugs' => $slugs, 'publish' => 0])->assertForbidden();
        $this->assertNotNull(PolicyInstrument::where('slug', $slugs[0])->value('published_at'));
    }

    public function test_decisions_for_every_kind_are_written_back_into_the_file_that_holds_the_record(): void
    {
        $dir = storage_path('framework/testing/data-'.uniqid());
        File::copyDirectory(base_path('data'), $dir);
        $this->app->instance(PolicyDataRepository::class, new PolicyDataRepository($dir));
        try {
            $this->actingAs($this->owner());
            $picked = [];
            foreach (['control', 'change', 'transition_measure', 'jurisdiction', 'policy'] as $type) {
                $slug = ReviewableTypes::model($type)::query()->orderBy('slug')->value('slug');
                $picked[$type] = $slug;
                $this->post('/backend/review/verify-many/'.$type, ['slugs' => [$slug], 'review_status' => 'verified', 'confidence_level' => 'high', 'source_opened' => 1])->assertRedirect();
            }

            $this->artisan('policy:export-verifications')->expectsOutputToContain('5 record(s) written')->assertExitCode(0);

            foreach ($picked as $type => $slug) {
                $file = ReviewableTypes::file($type, $slug, $dir);
                $this->assertNotNull($file, $type);
                $parsed = Yaml::parseFile($file);
                $record = $type === 'change' ? collect($parsed['changes'])->firstWhere('slug', $slug) : $parsed;
                $this->assertSame('verified', $record['review_status'], $type);
                $this->assertSame('Bhaskar Bhatt', $record['reviewed_by'], $type);
                $this->assertSame(now()->toDateString(), (string) $record['last_verified_at'], $type);
            }
            $this->assertSame(0, RecordVerification::where('exported', false)->count());
        } finally {
            File::deleteDirectory($dir);
        }
    }
}
