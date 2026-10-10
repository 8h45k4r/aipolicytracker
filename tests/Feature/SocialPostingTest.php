<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
use App\Models\SocialPost;
use App\Models\User;
use App\Services\Social\ChangePost;
use App\Services\Social\OAuth1;
use App\Services\Social\XPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * New verified changes become posts on X: a flag and headline, one sentence,
 * the link, hashtags and mentions, within 280 as X counts it. Only verified
 * news is posted, each change once, within the monthly cap; a refused post is
 * kept with X's reason and a rate-limited one is retried.
 */
class SocialPostingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['social.x' => array_merge(config('social.x'), [
            'enabled' => true, 'mode' => 'auto',
            'api_key' => 'ck-test-0000000000', 'api_secret' => 'cs-test-0000000000',
            'access_token' => '123-at-test-0000000000', 'access_secret' => 'as-test-0000000000',
            'mentions' => '@aipolicytracker @8h45k4r', 'hashtags' => '#AIPolicy', 'monthly_cap' => 40, 'min_impact' => 'routine',
        ])]);
    }

    private function change(array $overrides = []): ChangeEvent
    {
        $eu = Jurisdiction::firstOrCreate(['slug' => 'eu'], ['name' => 'European Union', 'short_name' => 'EU', 'iso_code' => 'EU', 'published_at' => now()]);
        $act = PolicyInstrument::firstOrCreate(['slug' => 'eu-ai-act'], ['jurisdiction_id' => $eu->id, 'title' => 'Artificial Intelligence Act', 'short_title' => 'EU AI Act', 'instrument_type' => 'regulation', 'status' => 'in_force', 'published_at' => now()]);

        return ChangeEvent::create(array_merge([
            'slug' => 'gpai-duties-apply-'.uniqid(),
            'jurisdiction_id' => $eu->id,
            'policy_instrument_id' => $act->id,
            'occurred_on' => now()->subDays(2)->toDateString(),
            'title' => 'General-purpose AI duties under the EU AI Act start to apply',
            'what_changed' => 'Providers of general-purpose AI models must now keep technical documentation and publish a summary of training content. The Commission can fine from August 2026.',
            'impact_level' => 'high',
            'review_status' => 'verified',
            'official_source_url' => 'https://eur-lex.europa.eu/eli/reg/2024/1689/oj',
            'published_at' => now(),
        ], $overrides));
    }

    public function test_the_post_reads_as_designed_and_fits_as_x_counts_it(): void
    {
        $change = $this->change();
        $text = ChangePost::compose($change);

        $this->assertStringStartsWith("\u{1F1EA}\u{1F1FA} General-purpose AI duties", $text);
        $this->assertStringContainsString("\n\nProviders of general-purpose AI models must now keep technical documentation and publish a summary of training content.\n\n", $text);
        $this->assertStringContainsString($change->url(), $text);
        $this->assertStringContainsString("#AIPolicy #EUAIAct\n@aipolicytracker @8h45k4r", $text);
        $this->assertStringNotContainsString('#EU ', $text, 'the place tag is dropped when the instrument tag already names it');
        $this->assertLessThanOrEqual(280, ChangePost::weight($text));

        // A link counts 23 however long it is; a flag counts 2.
        $this->assertSame(23, ChangePost::weight('https://aipolicytracker.org/changes/a-very-long-address-that-x-shortens-anyway'));
        $this->assertSame(2, ChangePost::weight("\u{1F1EA}\u{1F1FA}"));

        // Urgent changes say so; a long summary is cut at a word, never past 280.
        $long = $this->change(['impact_level' => 'urgent', 'what_changed' => str_repeat('Every provider of a high-risk system must register it before use ', 8).'.']);
        $text = ChangePost::compose($long);
        $this->assertStringContainsString('Urgent: General-purpose', $text);
        $this->assertStringContainsString('…', $text);
        $this->assertLessThanOrEqual(280, ChangePost::weight($text));
    }

    public function test_mentions_and_hashtags_come_from_settings_and_are_cleaned(): void
    {
        config(['social.x.mentions' => 'aipolicytracker, @8h45k4r @8h45k4r not-a-handle!', 'social.x.hashtags' => 'AIGovernance']);
        $this->assertSame(['@aipolicytracker', '@8h45k4r'], ChangePost::mentions());
        $this->assertSame(['#AIGovernance', '#EUAIAct'], ChangePost::hashtags($this->change()));
    }

    public function test_only_new_verified_policy_news_is_queued_and_each_change_once(): void
    {
        $news = $this->change();
        $this->change(['review_status' => 'pending_review']);
        $this->change(['occurred_on' => now()->subDays(90)->toDateString()]);
        $this->change(['slug' => 'template-dpia-v3']);
        $this->change(['published_at' => null]);

        $publisher = app(XPublisher::class);
        $this->assertSame([$news->id], $publisher->eligible()->pluck('id')->all());
        $this->assertSame(1, $publisher->queue());
        $this->assertSame(0, $publisher->queue(), 'a change is queued once');
        $this->assertSame('queued', SocialPost::sole()->status);

        config(['social.x.min_impact' => 'urgent']);
        $this->change();
        $this->assertSame(0, $publisher->queue(), 'below the minimum impact');
    }

    public function test_review_mode_holds_posts_as_drafts(): void
    {
        config(['social.x.mode' => 'review']);
        $this->change();
        app(XPublisher::class)->queue();
        $this->assertSame('draft', SocialPost::sole()->status);

        Http::fake();
        $this->assertSame(0, app(XPublisher::class)->publishDue()['posted']);
        Http::assertNothingSent();
    }

    public function test_a_queued_post_is_sent_signed_and_recorded(): void
    {
        Http::fake(['api.x.com/2/tweets' => Http::response(['data' => ['id' => '1850000000000000001', 'text' => 'x']], 201)]);
        $this->change();

        $this->artisan('social:post')->expectsOutputToContain('Queued 1, posted 1, failed 0.')->assertExitCode(0);

        $post = SocialPost::sole();
        $this->assertSame('posted', $post->status);
        $this->assertSame('1850000000000000001', $post->external_id);
        $this->assertSame('https://x.com/i/web/status/1850000000000000001', $post->externalUrl());
        Http::assertSent(function (HttpRequest $request) use ($post) {
            return $request->url() === 'https://api.x.com/2/tweets'
                && $request['text'] === $post->text
                && str_starts_with($request->header('Authorization')[0], 'OAuth ')
                && str_contains($request->header('Authorization')[0], 'oauth_signature="')
                && str_contains($request->header('Authorization')[0], 'oauth_consumer_key="ck-test-0000000000"');
        });
    }

    public function test_refusals_are_kept_with_the_reason_and_rate_limits_are_retried(): void
    {
        Http::fake(['api.x.com/2/tweets' => Http::sequence()
            ->push(['title' => 'Too Many Requests', 'detail' => 'Too Many Requests'], 429)
            ->push(['detail' => 'You are not permitted to perform this action.'], 403)]);
        $this->change();
        $publisher = app(XPublisher::class);
        $publisher->queue();

        $publisher->publishDue();
        $post = SocialPost::sole();
        $this->assertSame('queued', $post->status, 'a rate limit is tried again');
        $this->assertStringContainsString('429', $post->error);

        $publisher->publishDue();
        $post->refresh();
        $this->assertSame('failed', $post->status);
        $this->assertStringContainsString('not permitted', $post->error);
    }

    public function test_nothing_is_sent_when_off_unkeyed_or_over_the_cap(): void
    {
        Http::fake();
        $this->change();
        $publisher = app(XPublisher::class);
        $publisher->queue();

        config(['social.x.enabled' => false]);
        $this->assertSame('Posting to X is off.', $publisher->publishDue()['held']);
        $this->artisan('social:post')->expectsOutputToContain('Posting to X is off')->assertExitCode(0);

        config(['social.x.enabled' => true, 'social.x.access_secret' => null]);
        $this->assertSame('The X keys are not set.', $publisher->publishDue()['held']);

        config(['social.x.access_secret' => 'as-test-0000000000', 'social.x.monthly_cap' => 0]);
        $this->assertStringContainsString('cap of 0 posts', $publisher->publishDue()['held']);
        Http::assertNothingSent();
        $this->assertSame('queued', SocialPost::sole()->status);
    }

    public function test_the_dry_run_prints_posts_and_sends_nothing(): void
    {
        Http::fake();
        $this->change();
        $this->artisan('social:post', ['--dry-run' => true])
            ->expectsOutputToContain('1 change(s) would be posted:')
            ->expectsOutputToContain('#AIPolicy #EUAIAct')
            ->assertExitCode(0);
        $this->assertSame(0, SocialPost::count());
        Http::assertNothingSent();
    }

    public function test_the_signature_matches_the_published_oauth_example(): void
    {
        // X's own worked example of signing a request (developer docs, "Creating a signature").
        $signer = new OAuth1('xvz1evFS4wEEPTGEFPHBog', 'kAcSOqF21Fu85e7zjz7ZN2U4ZRhfV3WpwPAoE3Z7kBw', '370773112-GmHxMAgYyLbNEtIKZeRNFsMKPR9EyMZeS9weJAEb', 'LswwdoUaIvS8ltyTt5jkRh4J50vUPVVHtR2YPi5kE');
        $signature = $signer->signature('POST', 'https://api.twitter.com/1.1/statuses/update.json', [
            'status' => 'Hello Ladies + Gentlemen, a signed OAuth request!',
            'include_entities' => 'true',
            'oauth_consumer_key' => 'xvz1evFS4wEEPTGEFPHBog',
            'oauth_nonce' => 'kYjzVBB8Y0ZFabxSWbWovY3uYSQ2pTgmZeNu2VS4cg',
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => '1318622958',
            'oauth_token' => '370773112-GmHxMAgYyLbNEtIKZeRNFsMKPR9EyMZeS9weJAEb',
            'oauth_version' => '1.0',
        ]);
        $this->assertSame('hCtSmYh+iHYCEqBWrE7C7hYmtUk=', $signature);
    }

    public function test_editors_review_edit_approve_and_skip_posts_in_the_admin(): void
    {
        config(['social.x.mode' => 'review']);
        $editor = User::factory()->create();
        $editor->forceFill(['admin_role' => AdminRole::Editor])->save();
        $change = $this->change();
        app(XPublisher::class)->queue();
        $post = SocialPost::sole();

        $this->actingAs($editor)->get('/backend/admin/social')->assertOk()
            ->assertSee('Posts to X')->assertSee('Awaiting approval')->assertSee('#AIPolicy #EUAIAct')->assertSee('Approve');

        // Edited text is checked the way X counts it.
        $this->actingAs($editor)->put('/backend/admin/social/'.$post->id, ['text' => str_repeat('a', 281)])->assertSessionHasErrors('text');
        $this->actingAs($editor)->put('/backend/admin/social/'.$post->id, ['text' => 'EU AI Act GPAI duties apply. '.$change->url()])->assertSessionHasNoErrors();
        $this->assertSame('EU AI Act GPAI duties apply. '.$change->url(), $post->fresh()->text);

        $this->actingAs($editor)->post('/backend/admin/social/'.$post->id.'/approve')->assertRedirect();
        $this->assertSame('queued', $post->fresh()->status);
        $this->assertSame($editor->id, $post->fresh()->approved_by);

        $this->actingAs($editor)->post('/backend/admin/social/'.$post->id.'/skip')->assertRedirect();
        $this->assertSame('skipped', $post->fresh()->status);

        // An older change can be added by hand, as a draft.
        $old = $this->change(['occurred_on' => now()->subYear()->toDateString()]);
        $this->actingAs($editor)->post('/backend/admin/social', ['change' => $old->slug])->assertSessionHas('success');
        $this->assertSame('draft', SocialPost::where('change_event_id', $old->id)->value('status'));

        Http::fake(['api.x.com/2/tweets' => Http::response(['data' => ['id' => '42']], 201)]);
        $draft = SocialPost::where('change_event_id', $old->id)->sole();
        $this->actingAs($editor)->post('/backend/admin/social/'.$draft->id.'/send')->assertSessionHas('success', 'Posted to X.');
        $this->assertSame('posted', $draft->fresh()->status);
    }

    public function test_accounts_without_publishing_rights_cannot_reach_posts(): void
    {
        $reviewer = User::factory()->create();
        $reviewer->forceFill(['admin_role' => AdminRole::Reviewer])->save();
        $this->actingAs($reviewer)->get('/backend/admin/social')->assertForbidden();
        $this->actingAs($reviewer)->post('/backend/admin/social/queue')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/backend/admin/social')->assertRedirect();
    }

    public function test_change_pages_carry_their_own_preview_card(): void
    {
        $change = $this->change();
        $html = $this->get($change->url())->assertOk()->getContent();
        $this->assertStringContainsString('/og/change/'.$change->slug.'.png', $html);
        $this->get('/og/change/'.$change->slug.'.png')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/og/change/no-such-change.png')->assertNotFound();
    }
}
