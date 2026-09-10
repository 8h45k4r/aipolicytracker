<?php

namespace Tests\Feature\Site;

use App\Models\ContributorSubmission;
use App\Models\PolicyInstrument;
use App\Models\User;
use App\Services\PolicyData\PolicyDataRepository;
use App\Services\PolicyData\PolicyImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new PolicyImporter(PolicyDataRepository::default()))->run();
    }

    public function test_homepage_renders_product_positioning_without_javascript(): void
    {
        $response = $this->get('/');
        $response->assertOk()
            ->assertSee('Track AI policy. Build compliant AI.')
            ->assertSee('<link rel="canonical" href="'.url('/').'"', false)
            ->assertSee('application/ld+json', false)
            ->assertDontSee('SOC 2');
    }

    public function test_core_public_routes_return_ok(): void
    {
        foreach ([
            '/policies', '/policies?jurisdiction=eu', '/policies?q=transparency&status=in_force',
            '/jurisdictions', '/jurisdictions/eu', '/jurisdictions/us-colorado',
            '/obligations', '/obligations?category=transparency', '/obligations/eu-ai-act-risk-management-system',
            '/compare', '/compare?j[]=eu&j[]=uk', '/compare/eu-vs-india-ai-regulation',
            '/changes', '/changes/2025', '/tools/applicability-check',
            '/tools/applicability-check?jurisdictions[]=eu&role=provider&use_case=hiring_and_hr&personal_data=yes&genai=yes',
            '/open-data', '/methodology', '/about', '/contribute', '/guides', '/guides/iso-42001-vs-eu-ai-act',
            '/eu-ai-act', '/ai-regulation-india', '/ai-policy-nepal', '/ai-governance-singapore', '/ai-regulation-australia',
            '/ai-regulation-uk', '/ai-regulation-usa', '/ai-governance-uae', '/ai-regulation-south-asia',
        ] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_policy_page_has_unique_metadata_sources_and_verification_state(): void
    {
        $response = $this->get('/policies/eu-ai-act');
        $response->assertOk()
            ->assertSee('EU AI Act: requirements, deadlines and compliance actions')
            ->assertSee('https://eur-lex.europa.eu/eli/reg/2024/1689/oj')
            ->assertSee('Human verification pending')
            ->assertSee('Informational only, not legal advice')
            ->assertSee('<link rel="canonical" href="'.url('/policies/eu-ai-act').'"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
        $this->get('/policies/eu-ai-act.json')->assertOk()->assertJsonPath('slug', 'eu-ai-act')->assertJsonPath('source.review_status', 'pending_review');
    }

    public function test_titles_are_unique_across_key_pages(): void
    {
        $titles = [];
        foreach (['/', '/policies', '/jurisdictions', '/jurisdictions/eu', '/policies/eu-ai-act', '/obligations', '/changes', '/open-data', '/methodology', '/about', '/contribute', '/eu-ai-act'] as $path) {
            preg_match('/<title>(.*?)<\/title>/s', $this->get($path)->getContent(), $m);
            $titles[$path] = $m[1] ?? '';
        }
        $this->assertCount(count($titles), array_unique($titles), 'Duplicate <title> found: '.json_encode($titles));
    }

    public function test_filtered_and_search_listings_are_noindex_while_single_filters_are_indexable(): void
    {
        $this->get('/policies?q=ai')->assertSee('noindex,follow', false);
        $this->get('/policies?jurisdiction=eu&status=in_force')->assertSee('noindex,follow', false);
        $this->get('/policies?jurisdiction=eu')->assertSee('index,follow', false)->assertSee('<link rel="canonical" href="'.url('/policies?jurisdiction=eu').'"', false);
        $this->get('/compare?j[]=eu&j[]=uk')->assertSee('noindex,follow', false);
    }

    public function test_unpublished_policy_is_not_indexable_and_returns_404(): void
    {
        PolicyInstrument::where('slug', 'eu-ai-act')->update(['published_at' => null]);
        $this->get('/policies/eu-ai-act')->assertNotFound();
        $this->get('/sitemap-policies.xml')->assertOk()->assertDontSee('/policies/eu-ai-act<');
    }

    public function test_sitemaps_robots_feeds_and_machine_readable_assets(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertSee('sitemap-policies.xml');
        foreach (['static', 'jurisdictions', 'policies', 'obligations', 'changes', 'resources'] as $s) {
            $this->get("/sitemap-{$s}.xml")->assertOk()->assertSee('<urlset', false);
        }
        $this->get('/sitemap-policies.xml')->assertSee(url('/policies/eu-ai-act'))->assertSee('<lastmod>', false);
        $this->get('/sitemap-static.xml')->assertDontSee('/backend')->assertDontSee('/map');
        $this->get('/changes/feed')->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')->assertSee('<rss', false);
        $this->get('/llms.txt')->assertOk()->assertSee('# AIPolicyTracker');
        $this->get('/llms-full.txt')->assertOk()->assertSee('EU AI Act');
        $this->get('/openapi.json')->assertOk()->assertJsonPath('openapi', '3.1.0');
        $this->assertStringContainsString('Sitemap: https://aipolicytracker.org/sitemap.xml', file_get_contents(public_path('robots.txt')));
    }

    public function test_public_api_is_read_only_and_cached(): void
    {
        $this->getJson('/api/v1')->assertOk()->assertJsonPath('version', 'v1');
        $this->getJson('/api/v1/policies?jurisdiction=eu')->assertOk()->assertJsonPath('data.0.jurisdiction', 'eu')->assertHeader('Cache-Control', 'max-age=600, public, stale-while-revalidate=3600');
        $this->getJson('/api/v1/policies/eu-ai-act')->assertOk()->assertJsonPath('data.slug', 'eu-ai-act')->assertJsonCount(19, 'data.obligations');
        $this->getJson('/api/v1/obligations?category=risk_management')->assertOk();
        $this->getJson('/api/v1/changes?since=2025-01-01')->assertOk();
        $this->getJson('/api/v1/jurisdictions/nepal')->assertOk()->assertJsonPath('data.slug', 'nepal');
        $this->getJson('/api/v1/policies/does-not-exist')->assertNotFound();
        $this->postJson('/api/v1/policies', [])->assertStatus(405);
    }

    public function test_contributor_submissions_default_to_pending_review_and_honeypot_is_silent(): void
    {
        $this->post('/contribute', ['type' => 'correction', 'subject_type' => 'policy', 'subject_slug' => 'eu-ai-act', 'summary' => 'The applies_from date should be checked against Article 113.', 'proposed_source_url' => 'https://eur-lex.europa.eu/eli/reg/2024/1689/oj'])
            ->assertRedirect(route('contribute'));
        $this->assertDatabaseHas('contributor_submissions', ['subject_slug' => 'eu-ai-act', 'status' => 'pending_review']);
        $this->post('/contribute', ['type' => 'correction', 'summary' => 'spam spam spam spam', 'website' => 'http://spam'])->assertRedirect(route('contribute'));
        $this->assertSame(1, ContributorSubmission::count());
        $this->post('/contribute', ['type' => 'correction', 'summary' => 'short'])->assertSessionHasErrors('summary');
    }

    public function test_review_queue_is_admin_only_and_can_publish(): void
    {
        config(['aipolicytracker.admin_emails' => ['admin@example.com']]);
        $submission = ContributorSubmission::create(['type' => 'correction', 'summary' => 'Please check the date of application.', 'status' => 'pending_review']);
        $this->get('/backend/review')->assertRedirect('/login');
        $member = User::factory()->create(['email' => 'member@example.com']);
        $this->actingAs($member)->get('/backend/review')->assertRedirect(route('home', absolute: false));
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $this->actingAs($admin)->get('/backend/review')->assertOk()->assertSee('Please check the date of application.');
        $this->actingAs($admin)->post('/backend/review/submissions/'.$submission->id.'/decide', ['decision' => 'approved', 'notes' => 'Verified against OJ.'])->assertRedirect();
        $this->assertDatabaseHas('reviewer_decisions', ['contributor_submission_id' => $submission->id, 'decision' => 'approved']);
        $this->actingAs($admin)->post('/backend/review/publish/policy/eu-ai-act', ['publish' => 0])->assertRedirect();
        $this->assertNull(PolicyInstrument::where('slug', 'eu-ai-act')->value('published_at'));
    }

    public function test_legacy_urls_redirect_or_are_noindex(): void
    {
        $this->get('/about-ai-policy')->assertRedirect('/about');
        $this->get('/dashboard')->assertRedirect('/map');
        $this->get('/map')->assertOk()->assertSee('noindex', false);
        $this->get('/this-page-does-not-exist')->assertNotFound()->assertSee('Page not found');
    }

    public function test_data_validation_command_passes_on_seed_data(): void
    {
        $this->artisan('policy:validate')->assertExitCode(0);
    }
}
