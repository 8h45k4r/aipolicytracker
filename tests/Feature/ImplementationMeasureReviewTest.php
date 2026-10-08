<?php

namespace Tests\Feature;

use App\Models\EnforcementEvent;
use App\Models\ImplementationMeasure;
use App\Models\PolicyInstrument;
use App\Models\RecordVerification;
use App\Models\User;
use App\Services\PolicyData\PolicyDataRepository;
use App\Services\PolicyData\PolicyDataValidator;
use App\Services\PolicyData\SchemaValidator;
use App\Services\Review\ReviewableTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Implementation measures in the review queue and on /gaps, and enforcement events as a
 * read-only list in the review area.
 */
class ImplementationMeasureReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['aipolicytracker.admin_emails' => ['reviewer@example.test']]);
        $this->seed();
        $this->artisan('policy:import');
    }

    private function owner(): User
    {
        return User::factory()->create(['email' => 'reviewer@example.test', 'name' => 'Bhaskar Bhatt']);
    }

    private function draft(): ImplementationMeasure
    {
        return ImplementationMeasure::where('review_status', 'draft')->whereNull('official_source_url')->orderBy('slug')->firstOrFail();
    }

    private function pending(): ImplementationMeasure
    {
        return ImplementationMeasure::where('review_status', 'pending_review')->whereNotNull('official_source_url')->orderBy('slug')->firstOrFail();
    }

    public function test_the_queue_lists_implementation_measures_with_their_own_attestation(): void
    {
        $this->actingAs($this->owner());
        $draft = $this->draft();
        $pending = $this->pending();

        $this->get('/backend/review?type=implementation_measure')->assertOk()
            ->assertSee('Implementation measures')
            ->assertSee('I opened the official source of every selected measure')
            ->assertSee($draft->title)->assertSee($pending->title)
            ->assertSee($pending->kindLabel().' · '.$pending->statusLabel());

        $this->get('/backend/review?type=implementation_measure&review=draft')->assertOk()
            ->assertSee($draft->title)->assertDontSee($pending->title);
    }

    public function test_bulk_verification_records_the_sourced_measures_and_skips_a_draft_without_a_source(): void
    {
        $this->actingAs($this->owner());
        $draft = $this->draft();
        $pending = $this->pending();

        $this->post('/backend/review/verify-many/implementation_measure', ['slugs' => [$draft->slug, $pending->slug], 'review_status' => 'verified', 'confidence_level' => 'high', 'source_opened' => 1])
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, '1 Implementation measure marked verified') && str_contains($m, 'Skipped 1 with no official source URL ('.$draft->slug.')'));

        $this->assertSame('verified', $pending->fresh()->review_status);
        $this->assertSame('Bhaskar Bhatt', $pending->fresh()->reviewed_by);
        $this->assertSame('draft', $draft->fresh()->review_status);
        $this->assertDatabaseMissing('record_verifications', ['record_type' => 'implementation_measure', 'record_slug' => $draft->slug]);

        // The attestation still gates it, as for every other kind.
        $this->post('/backend/review/verify-many/implementation_measure', ['slugs' => [$pending->slug], 'review_status' => 'verified', 'confidence_level' => 'high'])
            ->assertSessionHasErrors('source_opened');

        // A single verify of the draft is refused outright and stores nothing.
        $this->post('/backend/review/verify/implementation_measure/'.$draft->slug, ['review_status' => 'pending_review', 'confidence_level' => 'low', 'source_opened' => 1])
            ->assertSessionHasErrors('review_status');
        $this->assertSame(1, RecordVerification::where('record_type', 'implementation_measure')->count());

        // The decision outlives a re-import.
        $this->artisan('policy:import');
        $this->assertSame('verified', $pending->fresh()->review_status);
    }

    public function test_publishing_switches_a_measure_on_and_off(): void
    {
        $this->actingAs($this->owner());
        $pending = $this->pending();

        $this->post('/backend/review/publish-many/implementation_measure', ['slugs' => [$pending->slug], 'publish' => 0])->assertRedirect()->assertSessionHas('success');
        $this->assertNull($pending->fresh()->published_at);
        $this->post('/backend/review/publish/implementation_measure/'.$pending->slug, ['publish' => 1])->assertRedirect();
        $this->assertNotNull($pending->fresh()->published_at);
    }

    public function test_a_decision_is_written_back_to_the_measure_file_and_the_file_still_validates(): void
    {
        $dir = storage_path('framework/testing/data-'.uniqid());
        File::copyDirectory(base_path('data'), $dir);
        $this->app->instance(PolicyDataRepository::class, new PolicyDataRepository($dir));
        try {
            $this->actingAs($this->owner());
            $pending = $this->pending();
            $this->post('/backend/review/verify-many/implementation_measure', ['slugs' => [$pending->slug], 'review_status' => 'verified', 'confidence_level' => 'high', 'source_opened' => 1])->assertRedirect();

            $this->artisan('policy:export-verifications')->expectsOutputToContain('1 record(s) written')->assertExitCode(0);

            $file = ReviewableTypes::file('implementation_measure', $pending->slug, $dir);
            $this->assertSame($dir.'/implementation/'.$pending->slug.'.yaml', $file);
            $record = Yaml::parseFile($file);
            $this->assertSame('verified', $record['review_status']);
            $this->assertSame('Bhaskar Bhatt', $record['reviewed_by']);
            $this->assertSame(now()->toDateString(), (string) $record['last_verified_at']);
            $this->assertSame($pending->title, $record['title'], 'the rest of the file is untouched');

            $repository = new PolicyDataRepository($dir);
            $errors = (new PolicyDataValidator($repository, new SchemaValidator($repository->schemaDir())))->run();
            $this->assertArrayNotHasKey('implementation/'.$pending->slug.'.yaml', $errors);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_pending_enforcement_events_are_listed_read_only_with_links_to_the_policy_and_the_correction_form(): void
    {
        $this->actingAs($this->owner());
        $this->get('/backend/review')->assertOk()->assertSee('data-pending-enforcement="0"', false)->assertSee('No enforcement events pending review');

        $policy = PolicyInstrument::published()->orderBy('slug')->firstOrFail();
        EnforcementEvent::create([
            'jurisdiction_id' => $policy->jurisdiction_id, 'policy_instrument_id' => $policy->id, 'slug' => 'test-pending-event',
            'title' => 'Test regulator order', 'review_status' => 'pending_review', 'confidence_level' => 'medium', 'published_at' => now(),
        ]);
        EnforcementEvent::create([
            'jurisdiction_id' => $policy->jurisdiction_id, 'policy_instrument_id' => $policy->id, 'slug' => 'test-verified-event',
            'title' => 'Already verified decision', 'review_status' => 'verified', 'confidence_level' => 'high', 'published_at' => now(),
        ]);

        $html = $this->get('/backend/review')->assertOk()
            ->assertSee('data-pending-enforcement="1"', false)
            ->assertSee('Test regulator order')
            ->assertDontSee('Already verified decision')
            ->assertSee($policy->url(), false)
            ->assertSee(route('contribute', ['type' => 'correction', 'subject_type' => 'policy', 'subject_slug' => $policy->slug]))
            ->getContent();
        $this->assertStringContainsString('data/policies/', $html, 'names the file the decision is made in');
    }

    public function test_gaps_lists_unchecked_implementation_measures_under_their_own_heading(): void
    {
        $draft = $this->draft();
        $pending = $this->pending();
        $pending->forceFill(['review_status' => 'verified'])->save();
        $stillPending = ImplementationMeasure::where('review_status', 'pending_review')->orderBy('slug')->firstOrFail();
        $open = ImplementationMeasure::published()->whereIn('review_status', ['draft', 'pending_review'])->count();

        $this->get('/gaps')->assertOk()
            ->assertSee('Implementation measures not yet checked')
            ->assertSee('data-gaps-implementation="'.$open.'"', false)
            ->assertSee($draft->title)
            ->assertSee($stillPending->title)
            ->assertDontSee($pending->title)
            ->assertSee(route('contribute', ['type' => 'new_source']), false);

        // A filtered queue is about one check; the separate list stays on the full page.
        $this->get('/gaps?kind=policy')->assertOk()->assertDontSee('Implementation measures not yet checked');

        ImplementationMeasure::query()->update(['review_status' => 'verified']);
        $this->get('/gaps')->assertOk()->assertDontSee('Implementation measures not yet checked');
    }
}
