<?php

namespace Tests\Feature;

use App\Enums\AdminCapability;
use App\Enums\AdminRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminRolesTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER = 'owner@example.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['aipolicytracker.admin_emails' => [self::OWNER]]);
    }

    private function owner(): User
    {
        return User::factory()->create(['email' => self::OWNER]);
    }

    private function withRole(AdminRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['admin_role' => $role])->save();

        return $user->refresh();
    }

    public function test_ownership_comes_from_the_environment_and_nothing_else(): void
    {
        $owner = $this->owner();
        $editor = $this->withRole(AdminRole::Editor);

        $this->assertTrue($owner->isOwner());
        $this->assertFalse($editor->isOwner());

        // Every owner-only capability is held by no storable role. If a role ever gained one,
        // this platform would have a second route to ownership.
        foreach ([AdminCapability::ManageUsers, AdminCapability::ManageSettings, AdminCapability::ManageBilling] as $capability) {
            $this->assertTrue($owner->hasCapability($capability));
            foreach (AdminRole::cases() as $role) {
                $this->assertFalse($role->has($capability), "{$role->value} must not hold {$capability->value}.");
            }
        }
    }

    public function test_the_role_column_cannot_be_mass_assigned(): void
    {
        // A crafted registration posting admin_role=editor must not create an administrator.
        $user = User::create(['name' => 'Crafted', 'email' => 'crafted@example.test', 'password' => 'secret-password', 'admin_role' => 'editor']);

        $this->assertNull($user->refresh()->admin_role);
        $this->assertFalse($user->isAdmin());
    }

    /** @return list<array{0: string, 1: list<string>}> */
    public static function pageMatrix(): array
    {
        return [
            ['backend.admin.dashboard', ['owner', 'editor', 'reviewer', 'analyst']],
            ['backend.review.index', ['owner', 'editor', 'reviewer']],
            ['backend.admin.subscribers', ['owner', 'editor', 'analyst']],
            ['backend.admin.external', ['owner', 'editor']],
            ['backend.admin.tools.index', ['owner', 'editor']],
            ['backend.admin.audit', ['owner', 'analyst']],
            ['backend.admin.users.index', ['owner']],
            ['backend.admin.settings', ['owner']],
        ];
    }

    #[DataProvider('pageMatrix')]
    public function test_each_role_reaches_exactly_the_pages_it_is_allowed(string $route, array $allowed): void
    {
        $accounts = [
            'owner' => $this->owner(),
            'editor' => $this->withRole(AdminRole::Editor),
            'reviewer' => $this->withRole(AdminRole::Reviewer),
            'analyst' => $this->withRole(AdminRole::Analyst),
        ];

        foreach ($accounts as $name => $user) {
            $response = $this->actingAs($user)->get(route($route));
            if (in_array($name, $allowed, true)) {
                $this->assertNotSame(403, $response->getStatusCode(), "{$name} should reach {$route}.");
            } else {
                $response->assertForbidden();
            }
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_a_granted_role_cannot_reach_user_management(): void
    {
        foreach (AdminRole::cases() as $role) {
            $this->actingAs($this->withRole($role))->get(route('backend.admin.users.index'))->assertForbidden();
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_owner_cannot_be_granted_through_the_form(): void
    {
        $target = User::factory()->create();

        // "owner" is not among the storable roles, so the validator rejects it outright.
        $this->actingAs($this->owner())
            ->post(route('backend.admin.users.role', $target), ['admin_role' => 'owner'])
            ->assertSessionHasErrors('admin_role');

        $this->assertNull($target->refresh()->admin_role);
        $this->assertFalse($target->isOwner());
    }

    public function test_nobody_can_act_on_their_own_account(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('backend.admin.users.role', $owner), ['admin_role' => 'analyst'])->assertForbidden();
        $this->actingAs($owner)->post(route('backend.admin.users.suspend', $owner))->assertForbidden();
        $this->actingAs($owner)->delete(route('backend.admin.users.destroy', $owner))->assertForbidden();
    }

    public function test_an_owner_cannot_be_demoted_suspended_or_deleted_from_the_ui(): void
    {
        // A second owner, so the acting owner is not the target and the self-check is not what
        // refuses this.
        config(['aipolicytracker.admin_emails' => [self::OWNER, 'second@example.test']]);
        $acting = $this->owner();
        $other = User::factory()->create(['email' => 'second@example.test']);

        $this->actingAs($acting)->post(route('backend.admin.users.role', $other), ['admin_role' => 'analyst'])->assertForbidden();
        $this->actingAs($acting)->post(route('backend.admin.users.suspend', $other))->assertForbidden();
        $this->actingAs($acting)->delete(route('backend.admin.users.destroy', $other))->assertForbidden();

        $this->assertTrue($other->refresh()->isOwner());
        $this->assertFalse($other->isSuspended());
        $this->assertDatabaseHas('users', ['email' => 'second@example.test']);
    }

    public function test_granting_a_role_records_who_granted_it(): void
    {
        $owner = $this->owner();
        $target = User::factory()->create();

        $this->actingAs($owner)->post(route('backend.admin.users.role', $target), ['admin_role' => 'reviewer'])->assertRedirect();

        $target->refresh();
        $this->assertSame(AdminRole::Reviewer, $target->admin_role);
        $this->assertSame($owner->getKey(), $target->admin_role_granted_by);
        $this->assertNotNull($target->admin_role_granted_at);

        // Clearing it clears the provenance too, rather than leaving a stale grant behind.
        $this->actingAs($owner)->post(route('backend.admin.users.role', $target), ['admin_role' => null])->assertRedirect();
        $target->refresh();
        $this->assertNull($target->admin_role);
        $this->assertNull($target->admin_role_granted_by);
        $this->assertFalse($target->isAdmin());
    }

    public function test_suspension_removes_admin_access_from_anyone_including_an_owner(): void
    {
        $owner = $this->owner();
        $owner->forceFill(['suspended_at' => now()])->save();

        $this->assertTrue($owner->refresh()->isOwner());
        $this->assertFalse($owner->isAdmin(), 'Suspension is the stop button; it has to stop an owner too.');
        $this->assertFalse($owner->hasCapability(AdminCapability::ManageUsers));
    }

    public function test_a_suspended_account_cannot_sign_in(): void
    {
        $user = User::factory()->create(['email' => 'stopped@example.test', 'password' => bcrypt('correct-horse-battery')]);
        $user->forceFill(['suspended_at' => now()])->save();

        $this->post('/login', ['email' => 'stopped@example.test', 'password' => 'correct-horse-battery'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_suspension_reason_is_stored_and_shown(): void
    {
        $owner = $this->owner();
        $target = User::factory()->create(['email' => 'left@example.test']);

        $this->actingAs($owner)->post(route('backend.admin.users.suspend', $target), ['reason' => 'Left the organisation'])->assertRedirect();

        $this->assertSame('Left the organisation', $target->refresh()->suspended_reason);
        $this->actingAs($owner)->get(route('backend.admin.users.index'))->assertOk()->assertSee('Left the organisation');

        // Restoring clears the reason rather than leaving a stale explanation attached.
        $this->actingAs($owner)->post(route('backend.admin.users.restore', $target))->assertRedirect();
        $this->assertNull($target->refresh()->suspended_reason);
        $this->assertFalse($target->isSuspended());
    }

    public function test_the_admin_nav_offers_only_what_the_role_holds(): void
    {
        $analyst = $this->withRole(AdminRole::Analyst);
        $html = $this->actingAs($analyst)->get(route('backend.admin.dashboard'))->assertOk()->getContent();

        // Offered: the analyst holds audit.view and audience.view.
        $this->assertStringContainsString(route('backend.admin.audit'), $html);
        $this->assertStringContainsString(route('backend.admin.subscribers'), $html);
        // Not offered: a link that would answer 403 reads as a broken admin.
        $this->assertStringNotContainsString(route('backend.admin.settings'), $html);
        $this->assertStringNotContainsString(route('backend.admin.users.index'), $html);
        $this->assertStringNotContainsString(route('backend.admin.tools.index'), $html);
        // And it says what they are, so missing pages are explained rather than mysterious.
        $this->assertStringContainsString('Analyst', $html);
    }

    public function test_an_account_with_no_role_still_cannot_reach_the_backend(): void
    {
        $this->actingAs(User::factory()->create())->get(route('backend.admin.dashboard'))->assertRedirect(route('home', absolute: false));
    }
}
