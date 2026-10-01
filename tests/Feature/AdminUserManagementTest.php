<?php

namespace Tests\Feature;

use App\Enums\AdminCapability;
use App\Enums\AdminRole;
use App\Mail\AdminInvitationMail;
use App\Models\AdminRolePermission;
use App\Models\User;
use App\Services\Admin\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
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

    /** Password confirmation is a session timestamp; set it as a recent confirmation would. */
    private function confirmed(): array
    {
        return ['auth.password_confirmed_at' => time()];
    }

    public function test_an_owner_edits_what_a_role_may_do_and_holders_follow_at_once(): void
    {
        $owner = $this->owner();
        $analyst = $this->withRole(AdminRole::Analyst);
        $this->assertFalse($analyst->hasCapability(AdminCapability::VerifyRecords));

        $analystCaps = [AdminCapability::ViewDashboard->value, AdminCapability::ViewAudit->value, AdminCapability::ViewAudience->value, AdminCapability::VerifyRecords->value];
        $this->actingAs($owner)->withSession($this->confirmed())
            ->post(route('backend.admin.users.permissions.update'), [
                'roles' => ['analyst'],
                'capabilities' => ['analyst' => $analystCaps],
            ])->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'added Verify records'));

        $this->app->forgetScopedInstances();
        $this->assertTrue($analyst->fresh()->hasCapability(AdminCapability::VerifyRecords));
        $this->assertSame('analyst', AdminRolePermission::sole()->role);
        $this->assertSame($owner->id, AdminRolePermission::sole()->updated_by);

        // Reset deletes the edit, and the defaults apply again.
        $this->actingAs($owner)->withSession($this->confirmed())
            ->post(route('backend.admin.users.permissions.reset'), ['role' => 'analyst'])->assertRedirect();
        $this->app->forgetScopedInstances();
        $this->assertFalse($analyst->fresh()->hasCapability(AdminCapability::VerifyRecords));
        $this->assertSame(0, AdminRolePermission::count());
    }

    public function test_owner_only_permissions_can_never_reach_a_role(): void
    {
        $owner = $this->owner();
        $editor = $this->withRole(AdminRole::Editor);

        // Refused by validation when submitted through the form...
        $this->actingAs($owner)->withSession($this->confirmed())
            ->post(route('backend.admin.users.permissions.update'), [
                'roles' => ['editor'],
                'capabilities' => ['editor' => [AdminCapability::ManageUsers->value]],
            ])->assertSessionHasErrors('capabilities.editor.0');

        // ...and ignored when written straight to the table.
        AdminRolePermission::create(['role' => 'editor', 'capabilities' => [AdminCapability::ManageUsers->value, AdminCapability::ManageSettings->value, AdminCapability::PublishRecords->value]]);
        $this->app->forgetScopedInstances();
        $editor = $editor->fresh();
        $this->assertFalse($editor->hasCapability(AdminCapability::ManageUsers));
        $this->assertFalse($editor->hasCapability(AdminCapability::ManageSettings));
        $this->assertTrue($editor->hasCapability(AdminCapability::PublishRecords));
        $this->actingAs($editor)->get(route('backend.admin.users.index'))->assertForbidden();
    }

    public function test_saving_the_defaults_stores_nothing(): void
    {
        $owner = $this->owner();
        $defaults = array_map(fn ($c) => $c->value, AdminRole::Reviewer->defaultCapabilities());
        $this->actingAs($owner)->withSession($this->confirmed())
            ->post(route('backend.admin.users.permissions.update'), ['roles' => ['reviewer'], 'capabilities' => ['reviewer' => $defaults]])
            ->assertSessionHas('success', 'No change.');
        $this->assertSame(0, AdminRolePermission::count());
        $this->assertFalse(app(RolePermissions::class)->isEdited(AdminRole::Reviewer));
    }

    public function test_the_permissions_matrix_renders_with_owner_rows_locked(): void
    {
        $html = $this->actingAs($this->owner())->get(route('backend.admin.users.permissions'))->assertOk()->getContent();

        $this->assertStringContainsString('name="capabilities[editor][]" value="records.publish"', $html);
        $this->assertStringNotContainsString('value="users.manage"', $html, 'owner-only rows have no checkbox');
        $this->assertStringContainsString('Editor: never (owner only)', $html);
    }

    public function test_an_invitation_creates_the_account_with_its_role_and_accepting_it_verifies_the_address(): void
    {
        Mail::fake();
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('backend.admin.users.invite'), [
            'name' => 'Rita Reviewer', 'email' => 'rita@aipolicytracker.org', 'admin_role' => 'reviewer', 'note' => 'Welcome aboard',
        ])->assertRedirect();

        $rita = User::where('email', 'rita@aipolicytracker.org')->sole();
        $this->assertSame(AdminRole::Reviewer, $rita->adminRole());
        $this->assertSame($owner->id, $rita->invited_by);
        $this->assertSame($owner->id, $rita->admin_role_granted_by);
        $this->assertTrue($rita->invitationPending());

        $url = null;
        Mail::assertSent(AdminInvitationMail::class, function (AdminInvitationMail $mail) use (&$url) {
            $url = $mail->url;

            return $mail->hasTo('rita@aipolicytracker.org') && $mail->note === 'Welcome aboard';
        });
        $this->assertStringContainsString('/invitation/', $url);

        auth()->logout();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $token = basename((string) parse_url($url, PHP_URL_PATH));
        $this->get($url)->assertOk()->assertSee('Accept your invitation');
        $this->post(route('invitation.store'), [
            'token' => $token, 'email' => $query['email'], 'password' => 'A-new-Passw0rd!', 'password_confirmation' => 'A-new-Passw0rd!',
        ])->assertRedirect(route('login'));

        $rita->refresh();
        $this->assertNotNull($rita->email_verified_at, 'choosing a password through the emailed link proves the address');
        $this->assertFalse($rita->invitationPending());

        // A used link does not work twice.
        $this->post(route('invitation.store'), [
            'token' => $token, 'email' => $query['email'], 'password' => 'Another-Passw0rd!', 'password_confirmation' => 'Another-Passw0rd!',
        ])->assertSessionHasErrors('email');
    }

    public function test_an_owner_address_or_an_existing_account_cannot_be_invited(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $existing = User::factory()->create(['email' => 'taken@aipolicytracker.org']);

        $this->actingAs($owner)->post(route('backend.admin.users.invite'), ['name' => 'X', 'email' => self::OWNER])->assertSessionHasErrors('email');
        $this->actingAs($owner)->post(route('backend.admin.users.invite'), ['name' => 'X', 'email' => $existing->email])->assertSessionHasErrors('email');
        Mail::assertNothingSent();
    }

    public function test_bulk_actions_skip_your_own_account_and_owners(): void
    {
        $owner = $this->owner();
        $second = User::factory()->create(['email' => 'second-owner@example.test']);
        config(['aipolicytracker.admin_emails' => [self::OWNER, 'second-owner@example.test']]);
        [$a, $b] = [User::factory()->create(), User::factory()->create()];

        $this->actingAs($owner)->post(route('backend.admin.users.bulk'), [
            'ids' => [$owner->id, $second->id, $a->id, $b->id], 'action' => 'role', 'admin_role' => 'analyst',
        ])->assertSessionHas('success', fn ($m) => str_contains($m, 'Role set on 2 accounts') && str_contains($m, '2 skipped'));

        $this->assertSame(AdminRole::Analyst, $a->fresh()->adminRole());
        $this->assertSame(AdminRole::Analyst, $b->fresh()->adminRole());
        $this->assertNull($owner->fresh()->adminRole());
        $this->assertNull($second->fresh()->adminRole());
        $this->assertSame($owner->id, $a->fresh()->admin_role_granted_by);

        $this->actingAs($owner)->post(route('backend.admin.users.bulk'), ['ids' => [$a->id, $owner->id], 'action' => 'suspend', 'reason' => 'Access review'])->assertRedirect();
        $this->assertTrue($a->fresh()->isSuspended());
        $this->assertSame('Access review', $a->fresh()->suspended_reason);
        $this->assertFalse($owner->fresh()->isSuspended());

        $this->actingAs($owner)->post(route('backend.admin.users.bulk'), ['ids' => [$a->id], 'action' => 'restore'])->assertRedirect();
        $this->assertFalse($a->fresh()->isSuspended());
    }

    public function test_bulk_delete_sits_behind_password_confirmation_and_spares_owners(): void
    {
        $owner = $this->owner();
        $target = User::factory()->create();

        // The suite's actingAs confirms the password, so the route's middleware is asserted directly.
        $this->assertContains('password.confirm', app('router')->getRoutes()->getByName('backend.admin.users.bulk.delete')->gatherMiddleware());
        $this->assertContains('password.confirm', app('router')->getRoutes()->getByName('backend.admin.users.permissions.update')->gatherMiddleware());

        $this->actingAs($owner)->withSession($this->confirmed())
            ->post(route('backend.admin.users.bulk.delete'), ['ids' => [$target->id, $owner->id]])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Deleted 1 account') && str_contains($m, '1 skipped'));
        $this->assertNull($target->fresh());
        $this->assertNotNull($owner->fresh());
    }

    public function test_a_granted_role_cannot_reach_any_of_the_new_pages(): void
    {
        $editor = $this->withRole(AdminRole::Editor);
        $other = User::factory()->create();

        $this->actingAs($editor)->get(route('backend.admin.users.show', $other))->assertForbidden();
        $this->actingAs($editor)->get(route('backend.admin.users.permissions'))->assertForbidden();
        $this->actingAs($editor)->get(route('backend.admin.users.export'))->assertForbidden();
        $this->actingAs($editor)->post(route('backend.admin.users.invite'), ['name' => 'X', 'email' => 'x@aipolicytracker.org'])->assertForbidden();
        $this->actingAs($editor)->post(route('backend.admin.users.bulk'), ['ids' => [$other->id], 'action' => 'suspend'])->assertForbidden();
    }

    public function test_the_account_page_shows_capabilities_sign_in_and_the_changes_made_to_it(): void
    {
        $owner = $this->owner();
        $target = User::factory()->create(['name' => 'Ana Analyst']);
        $this->actingAs($owner)->post(route('backend.admin.users.role', $target), ['admin_role' => 'analyst'])->assertRedirect();
        $target->refresh()->forceFill(['last_login_at' => now()->subDays(3)])->save();

        $html = $this->actingAs($owner)->get(route('backend.admin.users.show', $target))->assertOk()->getContent();

        $this->assertStringContainsString('Ana Analyst', $html);
        $this->assertStringContainsString('View the audit log<span class="sr-only">: allowed', $html);
        $this->assertStringContainsString('Publish records<span class="sr-only">: not allowed', $html);
        $this->assertStringContainsString('3 days ago', $html);
        $this->assertStringContainsString('role', $html);
        $this->assertStringContainsString(self::OWNER, $html, 'the role change is listed with who made it');
    }

    public function test_signing_in_records_the_time_without_touching_updated_at(): void
    {
        $user = User::factory()->create(['password' => 'Secret-passw0rd!']);
        $user->forceFill(['updated_at' => now()->subYear()])->saveQuietly();
        $updated = $user->fresh()->updated_at;

        $this->post(route('login'), ['email' => $user->email, 'password' => 'Secret-passw0rd!'])->assertRedirect();

        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertEquals($updated, $user->fresh()->updated_at);
    }

    public function test_filters_sort_and_export(): void
    {
        $owner = $this->owner();
        $noFactor = $this->withRole(AdminRole::Editor);
        $noFactor->forceFill(['name' => 'Nofactor Editor'])->save();
        $invited = User::factory()->unverified()->create(['name' => 'Pending Invitee']);
        $invited->forceFill(['invited_at' => now(), 'invited_by' => $owner->id])->save();
        $dormant = $this->withRole(AdminRole::Reviewer);
        $dormant->forceFill(['name' => 'Dormant Reviewer', 'last_login_at' => now()->subDays(200)])->save();
        User::factory()->create(['name' => '=HYPERLINK("x")']);

        $this->actingAs($owner)->get(route('backend.admin.users.index', ['status' => 'no_factor']))->assertOk()->assertSee('Nofactor Editor')->assertDontSee('Pending Invitee');
        $this->actingAs($owner)->get(route('backend.admin.users.index', ['status' => 'invited']))->assertOk()->assertSee('Pending Invitee')->assertDontSee('Nofactor Editor');
        $this->actingAs($owner)->get(route('backend.admin.users.index', ['status' => 'dormant']))->assertOk()->assertSee('Dormant Reviewer');
        $this->actingAs($owner)->get(route('backend.admin.users.index', ['sort' => 'last_login']))->assertOk();
        $this->actingAs($owner)->get(route('backend.admin.users.index', ['sort' => 'nonsense']))->assertOk();

        $csv = $this->actingAs($owner)->get(route('backend.admin.users.export'))->assertOk()->streamedContent();
        $this->assertStringStartsWith('name,email,organisation,role', $csv);
        $this->assertStringContainsString('Pending Invitee', $csv);
        $this->assertStringContainsString(',invited,', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'a cell that starts like a formula is neutralised');
        $this->assertStringNotContainsString('two_factor_secret', $csv);
    }

    public function test_re_sending_verification_and_password_reset_from_the_account_page(): void
    {
        Notification::fake();
        $owner = $this->owner();
        $unverified = User::factory()->unverified()->create();
        $verified = User::factory()->create();

        $this->actingAs($owner)->post(route('backend.admin.users.verification', $unverified))->assertSessionHas('success');
        $this->actingAs($owner)->post(route('backend.admin.users.verification', $verified))->assertStatus(422);
        $this->actingAs($owner)->post(route('backend.admin.users.password-reset', $verified))->assertSessionHas('success');
        $this->actingAs($owner)->post(route('backend.admin.users.password-reset', $owner))->assertForbidden();
    }
}
