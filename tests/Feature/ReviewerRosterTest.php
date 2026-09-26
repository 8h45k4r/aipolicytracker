<?php

namespace Tests\Feature;

use App\Models\ChangeEvent;
use App\Models\Control;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Services\PolicyData\PolicyDataRepository;
use App\Services\PolicyData\PolicyDataValidator;
use App\Services\PolicyData\SchemaValidator;
use App\Services\Reviewers\ReviewerRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class ReviewerRosterTest extends TestCase
{
    use RefreshDatabase;

    private string $dataDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');

        // A throwaway copy of data/ so a test can add a reviewer and mark a record verified
        // without touching the repository's own files.
        $this->dataDir = storage_path('framework/testing/data-'.uniqid());
        File::copyDirectory(base_path('data'), $this->dataDir);
        $this->useDataDir();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dataDir);
        parent::tearDown();
    }

    private function useDataDir(): void
    {
        $this->app->instance(PolicyDataRepository::class, new PolicyDataRepository($this->dataDir));
    }

    private function writeReviewer(array $overrides = []): array
    {
        $reviewer = array_replace([
            'slug' => 'jane-doe',
            'name' => 'Jane Doe',
            'role' => 'reviewer',
            'published' => true,
            'joined_on' => '2026-09-17',
            'jurisdictions' => ['european-union'],
            'interests' => [['declaration' => 'None declared.', 'declared_on' => '2026-09-17']],
        ], $overrides);
        File::ensureDirectoryExists($this->dataDir.'/reviewers');
        File::put($this->dataDir.'/reviewers/'.$reviewer['slug'].'.yaml', Yaml::dump($reviewer, 6, 2));

        return $reviewer;
    }

    private function validate(): array
    {
        $repository = new PolicyDataRepository($this->dataDir);

        return (new PolicyDataValidator($repository, new SchemaValidator($repository->schemaDir())))->run();
    }

    public function test_every_kind_the_roster_attributes_actually_carries_a_reviewer_column(): void
    {
        // This is the guard for the bug that took /reviewers down in production.
        // ReviewerRoster queried obligations for `reviewed_by`, a column that does not
        // exist. SQLite reads an unmatched double-quoted identifier as a string literal,
        // so `where "reviewed_by" is not null` silently matched every row and the suite
        // stayed green; PostgreSQL rejects it and the page returned 500. Asserting the
        // column exists catches it on either driver.
        $reflection = new \ReflectionClass(ReviewerRoster::class);
        $attributable = $reflection->getConstant('ATTRIBUTABLE');
        $this->assertNotEmpty($attributable);

        foreach ($attributable as $kind => $class) {
            $table = (new $class)->getTable();
            $this->assertTrue(
                Schema::hasColumn($table, 'reviewed_by'),
                "ReviewerRoster attributes {$kind} records to a reviewer, but {$table} has no reviewed_by column"
            );
        }

        // And the kind that does not carry one must not be attributed.
        $this->assertFalse(Schema::hasColumn('obligations', 'reviewed_by'));
        $this->assertArrayNotHasKey('obligation', $attributable);
    }

    public function test_the_corpus_total_counts_every_published_kind_not_only_the_attributable_ones(): void
    {
        $standing = app(ReviewerRoster::class)->standing();
        $obligations = Obligation::published()->count();

        $this->assertGreaterThan(0, $obligations, 'fixture must publish obligations for this to mean anything');
        $this->assertSame(
            $standing['attributable'] + $obligations,
            $standing['published'],
            'obligations belong in the corpus total even though no reviewer can be named on them'
        );
        $this->assertLessThan($standing['published'], $standing['attributable']);
    }

    public function test_the_shipped_data_directory_validates_including_the_roster(): void
    {
        $this->assertSame([], $this->validate());
    }

    public function test_a_reviewer_without_a_declaration_of_interest_fails_validation(): void
    {
        $this->writeReviewer(['interests' => []]);
        $errors = $this->validate();
        $this->assertArrayHasKey('reviewers/jane-doe.yaml', $errors);

        // "None declared." is a declaration; saying nothing is not.
        $this->writeReviewer();
        $this->assertSame([], $this->validate());
    }

    public function test_a_record_cannot_claim_verification_by_someone_who_is_not_on_the_roster(): void
    {
        $file = collect(File::allFiles($this->dataDir.'/policies'))->first();
        $record = Yaml::parseFile($file->getPathname());
        $record['review_status'] = 'verified';
        $record['last_verified_at'] = '2026-09-17';
        $record['reviewed_by'] = 'Jane Doe';
        File::put($file->getPathname(), Yaml::dump($record, 8, 2));

        $errors = $this->validate();
        $relative = ltrim(str_replace($this->dataDir, '', $file->getPathname()), '/');
        $this->assertArrayHasKey($relative, $errors, 'a verification signed by an unlisted name is rejected');
        $this->assertStringContainsString('not a published reviewer', implode(' ', $errors[$relative]));

        // Publishing the reviewer, with their declaration, is what makes the claim acceptable.
        $this->writeReviewer();
        $this->assertSame([], $this->validate());
    }

    public function test_an_unpublished_roster_entry_does_not_vouch_for_a_verification(): void
    {
        $this->writeReviewer(['published' => false]);
        $file = collect(File::allFiles($this->dataDir.'/policies'))->first();
        $record = Yaml::parseFile($file->getPathname());
        $record['review_status'] = 'verified';
        $record['last_verified_at'] = '2026-09-17';
        $record['reviewed_by'] = 'Jane Doe';
        File::put($file->getPathname(), Yaml::dump($record, 8, 2));

        $this->assertNotSame([], $this->validate());
    }

    public function test_the_count_beside_a_name_comes_from_the_records_not_the_roster_file(): void
    {
        $this->writeReviewer();
        $instrument = PolicyInstrument::published()->firstOrFail();
        $instrument->forceFill(['review_status' => 'verified', 'reviewed_by' => 'Jane Doe', 'last_verified_at' => now()])->save();

        $this->useDataDir();
        $roster = app(ReviewerRoster::class);
        $entry = $roster->published()->firstWhere('name', 'Jane Doe');
        $this->assertSame(1, $entry['verified']);
        $this->assertGreaterThanOrEqual(1, $roster->standing()['verified']);

        // A verification signed by a name nobody published is counted and surfaced, not hidden.
        PolicyInstrument::published()->where('id', '!=', $instrument->id)->firstOrFail()
            ->forceFill(['review_status' => 'verified', 'reviewed_by' => 'Someone Unlisted', 'last_verified_at' => now()])->save();
        $this->assertSame(1, app(ReviewerRoster::class)->standing()['unattributed']);
    }

    public function test_the_page_names_the_reviewer_and_publishes_their_declaration(): void
    {
        $this->writeReviewer(['interests' => [[
            'declaration' => 'Employed by a vendor of AI governance software.',
            'affects' => ['european-union'],
            'mitigation' => 'Does not verify records naming that vendor.',
        ]]]);
        $this->useDataDir();

        $response = $this->get('/reviewers');
        $response->assertOk();
        $response->assertSee('Jane Doe');
        $response->assertSee('Declared interests');
        $response->assertSee('Employed by a vendor of AI governance software.');
        $response->assertSee('Does not verify records naming that vendor.');
        $response->assertSee('nothing verified yet');
    }

    public function test_an_unpublished_entry_is_not_listed(): void
    {
        $this->writeReviewer(['published' => false]);
        $this->useDataDir();

        $response = $this->get('/reviewers');
        $response->assertOk();
        $response->assertDontSee('Jane Doe');
    }

    public function test_an_empty_roster_says_so_rather_than_implying_review_has_happened(): void
    {
        // The shipped roster is no longer empty, so the empty state is exercised against a
        // data directory that holds no reviewer: the page must still refuse to imply review.
        $empty = sys_get_temp_dir().'/roster-'.uniqid();
        mkdir($empty.'/reviewers', 0777, true);
        $this->app->bind(ReviewerRoster::class, fn () => new ReviewerRoster(new PolicyDataRepository($empty)));
        foreach ([PolicyInstrument::class, Obligation::class, Jurisdiction::class, Control::class, ChangeEvent::class] as $model) {
            $model::query()->update(['review_status' => 'pending_review', 'reviewed_by' => null, 'last_verified_at' => null]);
        }

        $response = $this->get('/reviewers');
        $response->assertOk();
        $response->assertSee('No reviewer has published a declaration yet');
        $response->assertSee('No record has yet been verified by a named reviewer');
    }

    public function test_the_roster_is_in_the_sitemap(): void
    {
        $this->get('/sitemap-static.xml')->assertOk()->assertSee(url('/reviewers'), false);
    }
}
