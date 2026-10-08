<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The subscribers list says where each address came from and whether a verified account
 * uses it, and both can be filtered and exported.
 */
class AdminSubscriberSourceTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER = 'owner@example.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['aipolicytracker.admin_emails' => [self::OWNER]]);
        foreach (['reader@example.org' => 'subscribe-page', 'member@example.org' => 'account', 'unverified@example.org' => 'changes', 'blank@example.org' => null] as $email => $source) {
            Subscriber::create(['email' => $email, 'token' => Subscriber::newToken(), 'topics' => ['all'], 'source' => $source, 'confirmed_at' => now()]);
        }
        // Account addresses may carry capitals; subscriber addresses are lower-cased.
        User::factory()->create(['email' => 'Member@Example.org']);
        User::factory()->unverified()->create(['email' => 'unverified@example.org']);
    }

    public function test_sources_are_shown_and_filterable(): void
    {
        $owner = User::factory()->create(['email' => self::OWNER]);

        $this->actingAs($owner)->get('/backend/admin/subscribers')->assertOk()
            ->assertSee('subscribe-page')->assertSee('changes')->assertSee('<option value="account"', false);

        $this->actingAs($owner)->get('/backend/admin/subscribers?source=account')->assertOk()
            ->assertSee('member@example.org')->assertDontSee('reader@example.org');
        $this->actingAs($owner)->get('/backend/admin/subscribers?source=(none)')->assertOk()
            ->assertSee('blank@example.org')->assertDontSee('reader@example.org')->assertDontSee('member@example.org');
    }

    public function test_an_address_with_a_verified_account_is_badged_and_filterable(): void
    {
        $owner = User::factory()->create(['email' => self::OWNER]);
        $member = User::where('email', 'Member@Example.org')->firstOrFail();

        $html = $this->actingAs($owner)->get('/backend/admin/subscribers')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'data-account-badge'), 'only the verified account is badged, not the unverified one');
        $this->assertStringContainsString(route('backend.admin.users.show', $member), $html);

        $this->actingAs($owner)->get('/backend/admin/subscribers?account=yes')->assertOk()
            ->assertSee('member@example.org')->assertDontSee('reader@example.org')->assertDontSee('unverified@example.org');
        $this->actingAs($owner)->get('/backend/admin/subscribers?account=no')->assertOk()
            ->assertDontSee('member@example.org')->assertSee('unverified@example.org');

        $csv = $this->actingAs($owner)->get('/backend/admin/subscribers/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('source,has_account', $csv);
        $this->assertMatchesRegularExpression('/^member@example\.org,.*,account,yes,/m', $csv);
        $this->assertMatchesRegularExpression('/^reader@example\.org,.*,subscribe-page,no,/m', $csv);
    }

    public function test_the_badge_links_to_the_account_only_for_those_who_manage_users(): void
    {
        $analyst = User::factory()->create();
        $analyst->forceFill(['admin_role' => AdminRole::Analyst])->save();
        $member = User::where('email', 'Member@Example.org')->firstOrFail();

        $html = $this->actingAs($analyst->refresh())->get('/backend/admin/subscribers')->assertOk()->getContent();
        $this->assertStringContainsString('data-account-badge', $html);
        $this->assertStringNotContainsString(route('backend.admin.users.show', $member), $html);
    }
}
