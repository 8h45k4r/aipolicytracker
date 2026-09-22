<?php

namespace Tests\Feature\Site;

use App\Mail\DownloadLinksMail;
use App\Mail\SubmissionReceivedMail;
use App\Models\ContributorSubmission;
use App\Models\ExternalIncident;
use App\Models\ExternalIncidentReport;
use App\Models\ExternalRisk;
use App\Models\Obligation;
use App\Models\PageView;
use App\Models\PolicyInstrument;
use App\Models\RecordVerification;
use App\Models\ResourceDownload;
use App\Models\Tool;
use App\Models\User;
use App\Services\PolicyData\PolicyDataRepository;
use App\Services\PolicyData\PolicyImporter;
use App\Services\Social\SocialCard;
use Database\Seeders\ToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        (new PolicyImporter(PolicyDataRepository::default()))->run();
        $this->seed(ToolSeeder::class);
    }

    public function test_social_card_declares_its_image_with_size_and_alt_text(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        foreach ([
            // The homepage now points at a card drawn from the corpus rather than
            // the static file, whose counts had gone stale inside the image.
            '<meta property="og:image" content="'.route('social.card', ['kind' => 'site', 'slug' => 'default']),
            '<meta property="og:image:width" content="1200">',
            '<meta property="og:image:height" content="630">',
            '<meta property="og:image:type" content="image/png">',
            '<meta name="twitter:card" content="summary_large_image">',
        ] as $tag) {
            $this->assertStringContainsString($tag, $html);
        }
        $this->assertStringContainsString('og:image:alt', $html);
        $this->assertStringContainsString('AI governance intelligence, from regulation to evidence', $html);

        // The declared size describes the image that is actually served, whether
        // that is a drawn card or the static file the site falls back to.
        if (app(SocialCard::class)->available()) {
            $bytes = $this->get(route('social.card', ['kind' => 'site', 'slug' => 'default']))->assertOk()->getContent();
            [$width, $height] = getimagesizefromstring($bytes);
        } else {
            $file = public_path(ltrim(config('aipolicytracker.default_og_image'), '/'));
            $this->assertFileExists($file);
            [$width, $height] = getimagesize($file);
        }
        $this->assertSame([(int) config('social.width'), (int) config('social.height')], [$width, $height], 'the declared og:image size must match the image served');
    }

    public function test_homepage_renders_product_positioning_without_javascript(): void
    {
        $response = $this->get('/');
        $response->assertOk()
            ->assertSee('From regulation to evidence.')
            ->assertSee('AI policy, verified at the source.')
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
            ->assertSee('Source-linked')
            ->assertSee('Informational only, not legal advice')
            ->assertSee('<link rel="canonical" href="'.url('/policies/eu-ai-act').'"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
        // The verification state is asserted against the record rather than a literal. Pinning
        // it to a string made an editorial decision -- moving a record to needs_update after its
        // dates came into question -- fail a metadata test that has nothing to do with it. The
        // point here is that the JSON exposes the record's real review state, whatever it is.
        $policy = PolicyInstrument::where('slug', 'eu-ai-act')->firstOrFail();
        $this->get('/policies/eu-ai-act.json')->assertOk()
            ->assertJsonPath('slug', 'eu-ai-act')
            ->assertJsonPath('source.review_status', $policy->review_status);
        $this->assertContains($policy->review_status, ['draft', 'pending_review', 'verified', 'needs_update']);
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

    public function test_login_page_is_server_rendered_with_password_toggle_and_about_lists_maintainer_links(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in to the admin')->assertSee('data-password-toggle', false)->assertDontSee('Register')->assertSee('brand/logo-on-dark.svg');
        $this->get('/about')->assertOk()->assertSee('Follow on X (Twitter)')->assertSee('Get connected on LinkedIn')->assertSee('https://bhaskar.com.np/');
        $this->get('/')->assertOk()->assertSee('Maintained by');
    }

    public function test_correction_form_is_prefilled_from_the_record_and_captures_field_context(): void
    {
        $this->get('/contribute?type=correction&subject_type=policy&subject_slug=eu-ai-act&field=in_force_on')
            ->assertOk()
            ->assertSee('Record you are correcting')
            ->assertSee('Which field is wrong?')
            ->assertSee('value="in_force_on"', false)
            ->assertSee('eur-lex.europa.eu');
        // Unknown records fall back to the generic form rather than a 404.
        $this->get('/contribute?subject_type=policy&subject_slug=does-not-exist')->assertOk()->assertSee('Record slug (from the URL)');

        $this->post('/contribute', ['type' => 'correction', 'subject_type' => 'policy', 'subject_slug' => 'eu-ai-act', 'field' => 'in_force_on', 'current_value' => '2024-08-01', 'proposed_value' => '2024-08-02', 'summary' => 'In force on for the EU AI Act is wrong.'])
            ->assertRedirect(route('contribute'));
        $submission = ContributorSubmission::latest('id')->first();
        $this->assertSame('in_force_on', $submission->payload['field']);
        $this->assertSame('2024-08-02', $submission->payload['proposed_value']);
        $this->assertSame(route('policies.show', 'eu-ai-act'), $submission->payload['record_url']);
        $this->assertNotEmpty($submission->payload['record_official_source_url']);
    }

    public function test_subscribe_and_saved_pages_render_and_record_pages_carry_follow_and_save_controls(): void
    {
        $this->get('/subscribe')->assertOk()->assertSee('Follow AI policy changes by email')->assertSee('name="topics[]"', false)->assertSee('European Union');
        $this->get('/saved')->assertOk()->assertSee('Saved records')->assertSee('data-saved-list', false);
        $this->get('/policies/eu-ai-act')->assertOk()->assertSee('data-save="policy:eu-ai-act"', false)->assertSee('name="topics[]" value="eu-ai-act"', false);
        $this->get('/jurisdictions/eu')->assertOk()->assertSee('data-save="jurisdiction:eu"', false);
        $this->get('/')->assertOk()->assertSee(route('subscribe.show'))->assertSee(route('saved'));
        $this->get('/dashboard')->assertRedirect('/');
    }

    public function test_guides_page_filters_and_free_tools_gate_downloads_behind_a_free_account(): void
    {
        $this->get('/guides')->assertOk()->assertSee('Free tools and templates')->assertSee('AI System Inventory Template')->assertSee('EU AI Act readiness for AI startups')->assertSee('index,follow');
        $this->get('/guides?type=template')->assertOk()->assertSee('AI System Inventory Template')->assertDontSee('EU AI Act Readiness Checklist')->assertSee('noindex,follow');
        $this->get('/guides?framework=eu-ai-act&topic=incident')->assertOk()->assertSee('AI Incident Response Checklist')->assertDontSee('AI Risk Register Template');
        $this->get('/guides?q=zzzz-nothing')->assertOk()->assertSee('No guides or tools match');

        $this->get('/guides/tools/ai-system-inventory-template')->assertOk()->assertSee('Preview: fields in the template')->assertSee('System ID')->assertSee('Create a free account to download')->assertSee(route('tools.gate', 'ai-system-inventory-template'));
        $this->get('/guides/tools/does-not-exist')->assertNotFound();
        $this->assertSame(0, PageView::where('path', '/guides/tools/does-not-exist')->count(), '404s are not counted');
        $this->get('/guides/tools/eu-ai-act-readiness-checklist')->assertOk()->assertSee('DOCX');
        $this->assertSame(10, Tool::published()->count());
        $this->get('/guides/tools/global-ai-regulatory-applicability-matrix')->assertOk()->assertSee('Supervisor')->assertSee('Formats: XLSX, CSV, Markdown, DOCX');
        $this->get('/guides/tools/ai-vendor-due-diligence-questionnaire')->assertOk()->assertSee('Evidence to request');
        $this->get('/guides/tools/ai-system-inventory-template/download')->assertOk()->assertSee('Continue with email')->assertSessionHas('url.intended');
        $this->post('/guides/tools/ai-system-inventory-template/download', ['terms' => 1])->assertRedirect(route('login'));
        $this->get('/register')->assertOk()->assertSee('Create free account')->assertSee('name="marketing_consent"', false);

        $user = User::factory()->create();
        $this->actingAs($user)->post('/guides/tools/ai-system-inventory-template/download', [])->assertSessionHasErrors('terms');
        $this->assertSame(0, ResourceDownload::count());
        Mail::fake();
        $response = $this->actingAs($user)->post('/guides/tools/ai-system-inventory-template/download', ['terms' => 1, 'updates' => 1, 'name' => $user->name, 'organization_name' => 'Example Ltd']);
        Mail::assertSent(DownloadLinksMail::class, fn ($m) => $m->hasTo($user->email) && str_contains($m->render(), 'Download XLSX'));
        $this->assertSame(1, PageView::where('path', '/guides/tools/ai-system-inventory-template')->sum('views'), 'tool page view counted once');
        $download = ResourceDownload::first();
        $response->assertRedirect(route('tools.ready', ['ai-system-inventory-template', $download]));
        $this->assertNotNull($user->fresh()->terms_accepted_at);
        $this->assertNotNull($user->fresh()->marketing_consent_at);
        $ready = $this->actingAs($user)->get(route('tools.ready', ['ai-system-inventory-template', $download]))->assertOk()->assertSee('Your download is ready')->assertSee('Download XLSX');
        preg_match('/href="([^"]*\/file\/[^"]*\.csv[^"]*)"/', $ready->getContent(), $m);
        $this->assertNotEmpty($m, 'signed CSV link present');
        $this->actingAs($user)->get(html_entity_decode($m[1]))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=ai-system-inventory-template.csv');
        $this->assertNotNull($download->fresh()->downloaded_at);
        $this->actingAs($user)->get(route('tools.file', ['slug' => 'ai-system-inventory-template', 'download' => $download, 'file' => 'ai-system-inventory-template.csv']))->assertStatus(403); // unsigned
        // A seeded file missing from the disk (clean deploy) is served from the bundled copy and restored; the seeder also restores it.
        Storage::disk('local')->delete('tools/ai-system-inventory-template/ai-system-inventory-template.csv');
        $this->actingAs($user)->get(html_entity_decode($m[1]))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=ai-system-inventory-template.csv');
        Storage::disk('local')->assertExists('tools/ai-system-inventory-template/ai-system-inventory-template.csv');
        Storage::disk('local')->delete('tools/ai-system-inventory-template/ai-system-inventory-template.csv');
        $this->seed(ToolSeeder::class);
        Storage::disk('local')->assertExists('tools/ai-system-inventory-template/ai-system-inventory-template.csv');
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('tools.ready', ['ai-system-inventory-template', $download]))->assertNotFound();
        $this->get('/ai-risk/incidents')->assertOk()->assertSee('min-w-0', false);
        // Multi-select filters and the archived state.
        $this->get('/guides?framework[]=eu-ai-act&framework[]=nist-ai-rmf&topic[]=incident')->assertOk()->assertSee('AI Incident Response Checklist')->assertSee('noindex,follow')->assertSee('data-multi-select', false)->assertSee('2 selected')->assertDontSee('multiple size=', false);
        Tool::where('slug', 'ai-system-inventory-template')->update(['status' => 'archived']);
        $this->get('/guides/tools/ai-system-inventory-template')->assertNotFound();
        $this->get('/guides')->assertOk()->assertDontSee('AI System Inventory Template');
    }

    public function test_admin_can_create_edit_upload_and_archive_tools(): void
    {
        config(['aipolicytracker.admin_emails' => ['editor@example.test']]);
        $admin = User::factory()->create(['email' => 'editor@example.test']);
        $this->get('/backend/admin/tools')->assertRedirect(route('login'));
        $this->actingAs($admin)->get('/backend/admin/tools')->assertOk()->assertSee('AI Risk Register Template')->assertSee('New tool')->assertSee('How to add a tool and upload its files');
        $this->actingAs($admin)->post('/backend/admin/tools', ['title' => 'Vendor AI Due Diligence Questionnaire', 'slug' => 'vendor-ai-due-diligence', 'type' => 'checklist', 'status' => 'draft', 'short' => 'Questions to ask an AI vendor before signing.', 'version' => '1.0', 'fields_text' => "Vendor | Legal name\nModel | Model and version", 'instructions_text' => 'Send before contract signature.', 'frameworks' => ['eu-ai-act'], 'topics' => ['governance']])
            ->assertRedirect();
        $tool = Tool::where('slug', 'vendor-ai-due-diligence')->firstOrFail();
        $this->assertSame([['Vendor', 'Legal name'], ['Model', 'Model and version']], $tool->fields);
        $this->get('/guides/tools/vendor-ai-due-diligence')->assertNotFound(); // draft
        // Publishing without a file is refused.
        $this->actingAs($admin)->put('/backend/admin/tools/'.$tool->id, ['title' => $tool->title, 'slug' => $tool->slug, 'type' => 'checklist', 'status' => 'published', 'short' => $tool->short, 'version' => '1.0'])->assertSessionHasErrors('status');
        $upload = UploadedFile::fake()->createWithContent('Vendor Questionnaire.csv', "Vendor,Model\nInformational only\n");
        $this->actingAs($admin)->post('/backend/admin/tools/'.$tool->id.'/files', ['file' => $upload])->assertRedirect();
        $file = $tool->files()->first();
        $this->assertSame('vendor-questionnaire.csv', $file->file_name);
        $this->assertSame('CSV', $file->label);
        Storage::disk('local')->assertExists($file->disk_path);
        $this->actingAs($admin)->put('/backend/admin/tools/'.$tool->id, ['title' => $tool->title, 'slug' => $tool->slug, 'type' => 'checklist', 'status' => 'published', 'short' => $tool->short, 'version' => '1.1', 'featured' => 1, 'fields_text' => "Vendor | Legal name\nModel | Model and version", 'frameworks' => ['eu-ai-act']])->assertRedirect();
        $this->get('/guides/tools/vendor-ai-due-diligence')->assertOk()->assertSee('Vendor AI Due Diligence Questionnaire')->assertSee('Legal name')->assertSee('Formats: CSV');
        $this->get('/guides?type=checklist')->assertOk()->assertSee('Vendor AI Due Diligence Questionnaire');
        $this->actingAs($admin)->get('/backend/admin/tools/'.$tool->id.'/files/'.$file->id)->assertOk();
        $this->actingAs($admin)->delete('/backend/admin/tools/'.$tool->id)->assertRedirect(route('backend.admin.tools.index'));
        $this->assertSame('archived', $tool->fresh()->status);
        $this->get('/guides/tools/vendor-ai-due-diligence')->assertNotFound();
        $this->actingAs($admin)->delete('/backend/admin/tools/'.$tool->id.'/files/'.$file->id)->assertRedirect();
        Storage::disk('local')->assertMissing($file->disk_path);
    }

    public function test_security_headers_include_a_nonce_based_csp_and_hide_server_version(): void
    {
        config(['aipolicytracker.google_analytics_id' => 'G-TEST']); // renders the inline analytics bootstrap
        $r = $this->get('/')->assertOk()->assertHeaderMissing('X-Powered-By')->assertHeader('X-Content-Type-Options', 'nosniff');
        $csp = $r->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        preg_match("/'nonce-([^']+)'/", $csp, $m);
        $this->assertNotEmpty($m, 'CSP carries a script nonce');
        $this->assertStringContainsString('nonce="'.$m[1].'"', $r->getContent(), 'inline script uses the same nonce');
        $this->get('/login')->assertOk()->assertHeader('Content-Security-Policy');
    }

    public function test_admin_verification_survives_reimport_and_exports_to_yaml(): void
    {
        config(['aipolicytracker.admin_emails' => ['editor@example.test']]);
        $admin = User::factory()->create(['email' => 'editor@example.test', 'name' => 'Editor One']);
        $this->post('/backend/review/verify/policy/eu-ai-act', ['review_status' => 'verified', 'confidence_level' => 'high'])->assertRedirect(route('login'));
        // Verified requires the source-opened confirmation.
        $this->actingAs($admin)->post('/backend/review/verify/policy/eu-ai-act', ['review_status' => 'verified', 'confidence_level' => 'high'])->assertSessionHasErrors('source_opened');
        $this->actingAs($admin)->post('/backend/review/verify/policy/eu-ai-act', ['review_status' => 'verified', 'confidence_level' => 'high', 'source_opened' => 1, 'notes' => 'Checked OJ L 2024/1689.'])->assertRedirect();
        $policy = PolicyInstrument::where('slug', 'eu-ai-act')->first();
        $this->assertSame('verified', $policy->review_status);
        $this->assertSame('Editor One', $policy->reviewed_by);
        $this->assertNotNull($policy->last_verified_at);
        // A re-import from data/ (which still says pending_review) keeps the decision.
        (new PolicyImporter(PolicyDataRepository::default()))->run();
        $this->assertSame('verified', $policy->fresh()->review_status);
        $this->actingAs($admin)->get('/backend/review')->assertOk()->assertSee('not yet written back to data/');
        // Export writes the fields into a copy of the YAML file.
        $src = collect(glob(base_path('data/policies/*/eu-ai-act.yaml')))->first();
        $backup = file_get_contents($src);
        // A verification is only valid once the person who signed it is on the published
        // roster with a declaration of interest, so the export is accompanied by one here
        // exactly as it would be in a real pull request.
        $roster = base_path('data/reviewers/editor-one.yaml');
        try {
            $this->artisan('policy:export-verifications')->assertExitCode(0);
            $yaml = Yaml::parseFile($src);
            $this->assertSame('verified', $yaml['review_status']);
            $this->assertSame('Editor One', $yaml['reviewed_by']);
            $this->assertNotEmpty($yaml['last_verified_at']);
            // Without the roster entry the data check rejects the signature.
            $this->artisan('policy:validate')->assertExitCode(1);
            file_put_contents($roster, Yaml::dump([
                'slug' => 'editor-one', 'name' => 'Editor One', 'role' => 'editor', 'published' => true,
                'interests' => [['declaration' => 'None declared.']],
            ], 6, 2));
            $this->artisan('policy:validate')->assertExitCode(0);
        } finally {
            file_put_contents($src, $backup);
            @unlink($roster);
        }
        $this->assertTrue(RecordVerification::where('record_slug', 'eu-ai-act')->value('exported'));
    }

    public function test_account_pages_are_server_rendered_in_the_site_theme(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('Reset your password')->assertSee('brand/logo-on-dark.svg');
        $this->get('/reset-password/sometoken?email=a@example.org')->assertOk()->assertSee('Choose a new password')->assertSee('name="token"', false);
        $this->get('/profile')->assertRedirect(route('login'));
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get('/verify-email')->assertOk()->assertSee('Verify your email address');
        $this->actingAs($user)->get('/confirm-password')->assertOk()->assertSee('Confirm your password');
        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Your downloads')->assertSee('Delete account')->assertSee($user->email);
        $this->actingAs($user)->patch('/profile', ['name' => 'Renamed', 'email' => $user->email, 'organization_name' => 'Acme', 'marketing_consent' => 1])->assertRedirect(route('profile.edit'));
        $this->assertSame('Acme', $user->fresh()->organization_name);
        $this->assertNotNull($user->fresh()->marketing_consent_at);
        $this->assertStringContainsString('1200', (string) getimagesize(public_path('og-default.png'))[0]);
    }

    public function test_home_persona_paths_and_policy_risk_crosswalk_render(): void
    {
        $this->get('/')->assertOk()->assertSee('Start from who you are')->assertSee('Compliance or CISO')->assertSee(route('tools.applicability'))->assertSee(route('controls.index'))->assertSee(route('audiences.show', 'deployers'));
        $this->get('/policies/eu-ai-act')->assertOk()->assertSee('AI risks this instrument addresses')->assertSee(route('risk.domain', 1));
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
        $this->get('/dashboard')->assertRedirect('/');
        $this->get('/map')->assertRedirect('/');
        $this->get('/news')->assertRedirect('/changes');
        $this->get('/timeline')->assertRedirect('/changes');
        $this->get('/news/9e91d870-7893-41a9-9429-4749e8838881')->assertRedirect('/changes');
        $this->get('/aipolicytracker/single-view/a109df5b-11b7-42ff-8665-5c7b6c10c53e')->assertRedirect('/policies');
        $this->get('/this-page-does-not-exist')->assertNotFound()->assertSee('Page not found');
    }

    public function test_data_validation_command_passes_on_seed_data(): void
    {
        $this->artisan('policy:validate')->assertExitCode(0);
    }

    /**
     * Gate 1 evidence: every foreign key in the policy-intelligence module resolves
     * (total rows equals rows whose reference exists).
     */
    public function test_policy_intelligence_interlinks_resolve(): void
    {
        $links = [
            ['policy_instruments', 'jurisdiction_id', 'jurisdictions'],
            ['policy_versions', 'policy_instrument_id', 'policy_instruments'],
            ['policy_sections', 'policy_instrument_id', 'policy_instruments'],
            ['obligations', 'policy_instrument_id', 'policy_instruments'],
            ['obligations', 'policy_section_id', 'policy_sections'],
            ['applicability_rules', 'policy_instrument_id', 'policy_instruments'],
            ['applicability_rules', 'obligation_id', 'obligations'],
            ['taxonomy_assignments', 'taxonomy_term_id', 'taxonomy_terms'],
            ['deadlines', 'policy_instrument_id', 'policy_instruments'],
            ['deadlines', 'obligation_id', 'obligations'],
            ['enforcement_events', 'jurisdiction_id', 'jurisdictions'],
            ['procurement_rules', 'jurisdiction_id', 'jurisdictions'],
            ['procurement_rules', 'policy_instrument_id', 'policy_instruments'],
            ['framework_mappings', 'obligation_id', 'obligations'],
            ['evidence_artifacts', 'obligation_id', 'obligations'],
            ['change_events', 'jurisdiction_id', 'jurisdictions'],
            ['change_events', 'policy_instrument_id', 'policy_instruments'],
            ['source_documents', 'policy_instrument_id', 'policy_instruments'],
            ['jurisdictions', 'parent_jurisdiction_id', 'jurisdictions'],
        ];
        foreach ($links as [$table, $column, $target]) {
            $total = DB::table($table)->whereNotNull($column)->count();
            $resolved = DB::table($table)->whereNotNull($column)->whereExists(fn ($q) => $q->select(DB::raw(1))->from("{$target} as target_ref")->whereColumn('target_ref.id', "{$table}.{$column}"))->count();
            $this->assertSame($total, $resolved, "{$table}.{$column} → {$target}: {$resolved} of {$total} resolve");
        }
        $this->assertGreaterThan(0, DB::table('taxonomy_assignments')->count());
        $morphs = DB::table('taxonomy_assignments')->count();
        $resolvedMorphs = DB::table('taxonomy_assignments')->where(fn ($q) => $q
            ->where(fn ($w) => $w->where('assignable_type', PolicyInstrument::class)->whereExists(fn ($e) => $e->select(DB::raw(1))->from('policy_instruments')->whereColumn('policy_instruments.id', 'taxonomy_assignments.assignable_id')))
            ->orWhere(fn ($w) => $w->where('assignable_type', Obligation::class)->whereExists(fn ($e) => $e->select(DB::raw(1))->from('obligations')->whereColumn('obligations.id', 'taxonomy_assignments.assignable_id'))))->count();
        $this->assertSame($morphs, $resolvedMorphs, 'taxonomy_assignments morphs resolve');
    }

    public function test_ai_risk_and_incident_pages_render_with_attribution(): void
    {
        $this->get('/ai-risk')->assertOk()->assertSee('Discrimination')->assertSee('CC BY 4.0')->assertSee('incidentdatabase.ai');
        $this->get('/ai-risk/1')->assertOk()->assertSee('Unfair discrimination')->assertSee('MIT AI Risk Navigator');
        $this->get('/ai-risk/9')->assertNotFound();
        $this->get('/ai-risk/incidents')->assertOk()->assertSee('CC BY-SA 4.0')->assertSee('Incidents per year')->assertSee('https://incidentdatabase.ai/cite/');
        $this->get('/sitemap-static.xml')->assertSee(url('/ai-risk'))->assertSee(url('/ai-risk/incidents'));
        $this->get('/llms.txt')->assertSee('/ai-risk');
    }

    public function test_external_sync_commands_rebuild_summaries_from_local_files(): void
    {
        $this->assertFileExists(base_path('data/external/aiid_summary.json'));
        $this->assertFileExists(base_path('data/external/mit_ai_risk_domains.json'));
        $mit = json_decode(file_get_contents(base_path('data/external/mit_ai_risk_domains.json')), true);
        $this->assertCount(7, $mit['domains']);
        $this->assertSame('CC BY 4.0', $mit['license']);
        $aiid = json_decode(file_get_contents(base_path('data/external/aiid_summary.json')), true);
        $this->assertSame('CC BY-SA 4.0', $aiid['license']);
        $this->assertGreaterThan(1000, $aiid['totals']['incidents']);
    }

    public function test_research_browse_pages_and_exports_work_with_imported_external_rows(): void
    {
        $this->artisan('external:import')->assertExitCode(0);
        $this->assertGreaterThan(1000, ExternalIncident::count());
        $this->assertGreaterThan(2000, ExternalRisk::count());
        $this->assertGreaterThan(5000, ExternalIncidentReport::count());
        $this->assertSame(0, ExternalIncidentReport::whereNotIn('incident_id', ExternalIncident::select('incident_id'))->count(), 'every report resolves to an incident');
        $this->artisan('external:import')->assertExitCode(0); // idempotent re-run
        $this->assertSame(ExternalRisk::count(), count(json_decode(file_get_contents(base_path('data/external/mit_risks.json')), true)['risks']));

        $this->get('/ai-risk')->assertOk()->assertSee('Risk entries by entity');
        $this->get('/ai-risk/1')->assertOk()->assertSee('risk entries')->assertSee('Browse and export these incidents')->assertSee(route('risk.subdomain', [1, '1.1']));
        $this->get('/ai-risk')->assertOk()->assertSee('Harm is rising, and its shape is changing')->assertSee('Where harm is recorded versus where rules exist')->assertSee('Most frequently named deployers')->assertSee('policy milestones')->assertSee('What to do with this, depending on who you are')->assertSee('Explore: domains and subdomains')->assertSee('data-chart-export="png"', false)->assertSee('data-tip=', false)->assertSee('top quarter')->assertSee(route('risk.subdomain', [2, '2.1']));
        $this->get('/ai-risk/2/2.1')->assertOk()->assertSee('Compromise of privacy')->assertSee('Causal entity (risk entries)')->assertSee('Frameworks covering this subdomain')->assertSee('Recent incidents');
        $this->get('/ai-risk/2/9.9')->assertNotFound();
        $this->get('/sitemap-static.xml')->assertOk()->assertSee(route('risk.subdomain', [2, '2.1']));
        $this->get('/ai-risk/risks?domain=2&entity=Human')->assertOk()->assertSee('Privacy')->assertSee('noindex', false);
        $this->get('/ai-risk/risks?q=zzzz-no-such-term')->assertOk()->assertSee('No risks match');
        $this->get('/ai-risk/frameworks')->assertOk()->assertSee('Risk entries');
        $this->get('/ai-risk/incidents/browse?year=2024')->assertOk()->assertSee('incidentdatabase.ai/cite/');
        $incident = ExternalIncident::whereNotNull('mit_subdomain')->where('mit_subdomain', '!=', '')->orderByDesc('report_count')->first();
        $this->get('/ai-risk/incidents/'.$incident->incident_id)->assertOk()->assertSee($incident->title)->assertSee('news report')->assertSee('Classification (MIT AI Risk Repository taxonomy)')->assertSee('data-save="incident:'.$incident->incident_id.'"', false)->assertSee('News reports (');
        $this->get('/ai-risk/incidents/999999999')->assertNotFound();
        $this->get('/ai-risk/incidents/'.$incident->incident_id)->assertSee(route('contribute', ['type' => 'correction', 'subject_type' => 'incident', 'subject_slug' => $incident->incident_id]));
        $this->get('/contribute?type=correction&subject_type=incident&subject_slug='.$incident->incident_id)->assertOk()->assertSee('Record you are correcting')->assertSee('incidentdatabase.ai/cite/'.$incident->incident_id);
        $risk = ExternalRisk::where('level', 'Risk Sub-Category')->whereNotNull('subdomain')->first();
        $this->get($risk->url())->assertOk()->assertSee($risk->risk_subcategory ?: $risk->risk_category)->assertSee('Real-world incidents in this subdomain')->assertSee($risk->quick_ref);
        $dup = ExternalRisk::where('ev_id', 'like', '%#%')->first();
        if ($dup) {
            $this->get($dup->url())->assertOk();
        }
        $csv = $this->get('/ai-risk/incidents/export.csv?year=2024')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('CC BY-SA 4.0', $csv->streamedContent());
        $this->assertStringContainsString('incident_id,occurred_on,title', $csv->streamedContent());
        $json = $this->get('/ai-risk/risks/export.json?domain=3')->assertOk()->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $this->assertStringContainsString('"attribution"', $json->streamedContent());
        $this->get('/ai-risk/risks/export.xml')->assertNotFound();
    }

    public function test_html_is_not_cached_but_api_is_and_submissions_notify_admins(): void
    {
        $this->get('/')->assertOk()->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, private');
        $this->get('/api/v1/policies')->assertOk()->assertHeader('Cache-Control', 'max-age=600, public, stale-while-revalidate=3600');

        config(['aipolicytracker.admin_emails' => ['editor@example.test']]);
        Mail::fake();
        $this->post('/contribute', ['type' => 'correction', 'summary' => 'The in-force date for the EU AI Act is wrong.', 'proposed_source_url' => 'https://eur-lex.europa.eu/eli/reg/2024/1689/oj'])->assertRedirect();
        Mail::assertSent(SubmissionReceivedMail::class, fn ($m) => $m->hasTo('editor@example.test'));
    }
}
