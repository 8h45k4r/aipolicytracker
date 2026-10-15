<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\ChangeEvent;
use App\Models\Deadline;
use App\Models\DigestIssue;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\User;
use App\Services\PolicyData\PolicyDataRepository;
use App\Services\PolicyData\PolicyDataValidator;
use App\Services\PolicyData\SchemaValidator;
use App\Services\Templates\TemplateBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/** Findings of the October audit: data integrity, archive stability, tokens, stateless feeds, search. */
class AuditRoundTwoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_a_record_whose_file_left_data_is_unpublished_with_its_duties(): void
    {
        $eu = PolicyInstrument::where('slug', 'eu-ai-act')->firstOrFail();
        $orphan = $eu->replicate(['aipt_id'])->fill(['slug' => 'left-the-data-dir', 'published_at' => now()]);
        $orphan->save();
        $duty = Obligation::query()->firstOrFail()->replicate(['aipt_id'])->fill(['slug' => 'duty-left-the-data-dir', 'policy_instrument_id' => $orphan->id, 'published_at' => now()]);
        $duty->save();
        $change = ChangeEvent::query()->firstOrFail()->replicate(['aipt_id'])->fill(['slug' => 'change-left-the-data-dir', 'published_at' => now()]);
        $change->save();

        $this->artisan('policy:import')->assertSuccessful();

        $this->assertNull($orphan->fresh()->published_at);
        $this->assertNull($change->fresh()->published_at);
        $this->assertNotNull($eu->fresh()->published_at, 'records still in data/ stay published');
        $this->assertSame(0, Obligation::where('policy_instrument_id', $orphan->id)->whereNotNull('published_at')->count());
    }

    public function test_a_template_release_entry_survives_the_import(): void
    {
        $release = ChangeEvent::query()->firstOrFail()->replicate(['aipt_id'])->fill(['slug' => TemplateBuilder::CHANGE_SLUG_PREFIX.'ai-register-v2', 'published_at' => now()]);
        $release->save();

        $this->artisan('policy:import')->assertSuccessful();

        $this->assertNotNull($release->fresh()->published_at, 'written by templates:build, never read from data/');
    }

    public function test_the_validator_refuses_a_dangling_section_or_duty_reference(): void
    {
        $dir = sys_get_temp_dir().'/aip-refs-'.uniqid();
        File::copyDirectory(base_path('data'), $dir);
        try {
            $file = $dir.'/policies/eu/eu-ai-act.yaml';
            $yaml = preg_replace('/^    section: "Article 6"$/m', '    section: "Article 999"', File::get($file), 1);
            File::put($file, $yaml);
            $errors = implode("\n", array_merge(...array_values((new PolicyDataValidator(new PolicyDataRepository($dir), new SchemaValidator($dir.'/schema')))->run())));
            $this->assertStringContainsString('no section with reference "Article 999"', $errors);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_an_archived_digest_issue_keeps_its_dates_after_a_reimport(): void
    {
        $deadline = Deadline::whereHas('policyInstrument', fn ($q) => $q->published())->with('policyInstrument.jurisdiction')->firstOrFail();
        $issue = DigestIssue::forDay(now()->toDateString())->fill([
            'period_start' => now()->subWeek()->toDateString(), 'period_end' => now()->toDateString(),
            'change_ids' => [], 'deadline_ids' => [$deadline->id], 'deadlines' => DigestIssue::snapshotDeadlines(collect([$deadline])),
            'incident_count' => 0, 'recipients' => 0,
        ]);
        $issue->save();
        $title = $deadline->title;

        Deadline::query()->delete(); // what a re-import does to every deadline row
        $this->artisan('policy:import');

        $this->assertSame($title, $issue->fresh()->deadlines()->first()['title']);
        $this->get($issue->url())->assertOk()->assertSee($title);
    }

    public function test_an_invitation_and_a_password_reset_do_not_share_tokens(): void
    {
        $user = User::factory()->create();
        $invite = Password::broker('invites')->createToken($user);
        Password::broker('users')->createToken($user);

        $this->assertSame(1, DB::table('invitation_tokens')->where('email', $user->email)->count());
        $this->assertTrue(Password::broker('invites')->tokenExists($user, $invite), 'a reset does not replace the invitation');
    }

    public function test_feeds_and_sitemaps_start_no_session(): void
    {
        foreach (['/sitemap.xml', '/sitemap-policies.xml', '/llms.txt', '/changes/feed', '/openapi.json'] as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertSame([], $response->headers->getCookies(), $url.' sets no cookie');
        }
        $llms = $this->get('/llms.txt')->getContent();
        $this->assertStringNotContainsString('&amp;', $llms);
        $this->assertStringNotContainsString('&#039;', $llms);
        $this->assertStringContainsString('/id/AIPT-POL-0073', $llms);
    }

    public function test_settings_are_read_once_and_a_save_is_seen_at_once(): void
    {
        AppSetting::put('x_handle', '@first');
        DB::enableQueryLog();
        AppSetting::get('x_handle');
        AppSetting::get('contact_email');
        $this->assertCount(1, DB::getQueryLog(), 'one query for every key');
        AppSetting::put('x_handle', '@second');
        $this->assertSame('@second', AppSetting::get('x_handle'));
    }
}
