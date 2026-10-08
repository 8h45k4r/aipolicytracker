<?php

namespace Tests\Feature;

use App\Enums\AdminCapability;
use App\Enums\AdminRole;
use App\Mail\AdminInvitationMail;
use App\Models\AdminRolePermission;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * Backend → Users and roles as an owner uses it: inviting from a side panel, honest word on
 * whether the invitation was delivered, roles changed on the account's page with a named
 * confirmation, a bulk bar whose actions carry their own inputs, and the permissions matrix.
 */
class AdminUsersInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER = 'owner@example.test';

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['aipolicytracker.admin_emails' => [self::OWNER]]);
        $this->owner = User::factory()->create(['email' => self::OWNER, 'name' => 'Olivia Owner']);
    }

    private function withRole(?AdminRole $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->forceFill(['admin_role' => $role])->save();

        return $user->refresh();
    }

    /** The markup of the element carrying $attribute="$value", for assertions scoped to one block. */
    private function block(string $html, string $attribute, string $value): string
    {
        $xpath = $this->xpath($html);
        $node = $xpath->query("//*[@{$attribute}='{$value}']")->item(0);
        $this->assertNotNull($node, "no element with {$attribute}=\"{$value}\"");

        return $node->ownerDocument->saveHTML($node);
    }

    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($doc);
    }

    public function test_the_invite_button_opens_a_side_panel_and_is_in_the_command_palette(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backend.admin.users.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<h1[^>]*>\s*Users and roles\s*</h1>#', $html, 'the heading matches the sidebar');
        $this->assertStringContainsString('<dialog id="invite-drawer" class="adm-drawer"', $html);
        $this->assertStringNotContainsString('data-open-on-load', $html, 'the panel starts closed');
        // One link: it opens the panel, is a palette action, and without JavaScript leads to the form's own page.
        $this->assertStringContainsString('href="'.route('backend.admin.users.invite.create').'" class="btn-primary" data-drawer-open="invite-drawer" data-command="Invite a user"', $html);
        $drawer = $this->block($html, 'id', 'invite-drawer');
        $this->assertStringContainsString('action="'.route('backend.admin.users.invite').'"', $drawer);
        foreach (['name', 'email', 'note'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $drawer);
        }

        // The no-JavaScript route renders the same form on a page of its own, for owners only.
        $this->get(route('backend.admin.users.invite.create'))->assertOk()
            ->assertSee('action="'.route('backend.admin.users.invite').'"', false)
            ->assertSee('Create account and send invitation');
        $this->actingAs($this->withRole(AdminRole::Editor))->get(route('backend.admin.users.invite.create'))->assertForbidden();
    }

    public function test_role_choices_are_radio_cards_listing_what_each_role_can_do_now(): void
    {
        // An owner's edit on the permissions page shows in the card, not the enum defaults.
        AdminRolePermission::create(['role' => 'analyst', 'capabilities' => [AdminCapability::ViewDashboard->value, AdminCapability::VerifyRecords->value]]);

        $html = $this->actingAs($this->owner)->get(route('backend.admin.users.invite.create'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<select id="invite-role"', $html);
        $none = $this->block($html, 'data-role-option', 'none');
        $this->assertStringContainsString('type="radio" name="admin_role" value=""', $none);
        $this->assertStringContainsString('checked', $none, 'no admin access is the default');

        foreach (AdminRole::cases() as $role) {
            $card = $this->block($html, 'data-role-option', $role->value);
            $this->assertStringContainsString('type="radio" name="admin_role" value="'.$role->value.'"', $card);
            $this->assertStringContainsString(e($role->description()), $card);
            foreach ($role->capabilities() as $capability) {
                $this->assertStringContainsString($capability->label(), $card, "{$role->value} lists {$capability->value}");
            }
        }

        $analyst = $this->block($html, 'data-role-option', 'analyst');
        $this->assertStringContainsString('Verify records', $analyst);
        $this->assertStringNotContainsString('View audit log', $analyst, 'a capability the edit removed is not listed');
        $this->assertStringContainsString('Edited by an owner', $analyst);
    }

    public function test_a_failed_invitation_reopens_the_panel_with_what_was_typed_and_lists_errors_once(): void
    {
        $this->actingAs($this->owner)->from(route('backend.admin.users.index'))
            ->post(route('backend.admin.users.invite'), ['_form' => 'invite', 'name' => 'Rita Reviewer', 'email' => 'not-an-address', 'admin_role' => 'reviewer', 'note' => 'Welcome'])
            ->assertRedirect(route('backend.admin.users.index'))->assertSessionHasErrors('email');

        $html = $this->get(route('backend.admin.users.index'))->assertOk()->getContent();
        $drawer = $this->block($html, 'id', 'invite-drawer');

        $this->assertStringContainsString('data-open-on-load', $drawer);
        $this->assertStringContainsString('value="Rita Reviewer"', $drawer);
        $this->assertStringContainsString('value="not-an-address"', $drawer);
        $this->assertStringContainsString('>Welcome</textarea>', $drawer);
        $this->assertMatchesRegularExpression('#value="reviewer"[^>]*checked#', $this->block($html, 'data-role-option', 'reviewer'));
        $this->assertStringContainsString('id="invite-email-error"', $drawer);
        $this->assertStringContainsString('aria-invalid="true"', $drawer);

        // The message appears in the toast and beside its field, and in no separate list.
        $message = e(session()->get('errors')?->first('email') ?? 'The email field must be a valid email address.');
        $this->assertSame(2, substr_count($html, $message), 'toast plus the inline field error, nothing more');
        $this->assertStringNotContainsString('list-disc pl-5 text-sm text-state-bad', $html);
    }

    public function test_errors_from_another_form_on_the_page_do_not_open_the_invite_panel(): void
    {
        $this->actingAs($this->owner)->from(route('backend.admin.users.index'))
            ->post(route('backend.admin.users.bulk'), ['ids' => ['x'], 'action' => 'role', 'admin_role' => 'editor']);

        $html = $this->get(route('backend.admin.users.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-open-on-load', $html);
    }

    public function test_an_invitation_on_a_mailer_that_delivers_nothing_says_so_and_shows_the_link_once(): void
    {
        config(['mail.default' => 'log']);
        Mail::fake();

        $response = $this->actingAs($this->owner)->post(route('backend.admin.users.invite'), ['name' => 'Ivy', 'email' => 'ivy@aipolicytracker.org']);
        $ivy = User::where('email', 'ivy@aipolicytracker.org')->sole();
        $response->assertRedirect(route('backend.admin.users.show', $ivy))
            ->assertSessionMissing('success')
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'not delivered') && str_contains($m, '"log"') && ! str_contains($m, 'sent to') && ! str_contains($m, 'emailed to'));

        $url = null;
        Mail::assertSent(AdminInvitationMail::class, function (AdminInvitationMail $mail) use (&$url) {
            $url = $mail->url;

            return true;
        });

        $html = $this->get(route('backend.admin.users.show', $ivy))->assertOk()->getContent();
        $panel = $this->block($html, 'data-invitation-link', '');
        $this->assertStringContainsString('value="'.e($url).'"', $panel, 'the link in the panel is the one that works');
        $this->assertStringContainsString('data-copy-from="invite-link"', $panel);
        $this->assertStringContainsString('Copy invitation link', $panel);
        $this->assertStringContainsString('Re-send invitation', $html);

        // Flashed: a reload, or anybody else's page, no longer carries it.
        $this->get(route('backend.admin.users.show', $ivy))->assertOk()->assertDontSee('data-invitation-link', false)->assertDontSee(e($url), false);
    }

    public function test_an_invitation_on_a_real_mailer_says_it_was_emailed_and_shows_no_link(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $this->actingAs($this->owner)->post(route('backend.admin.users.invite'), ['name' => 'Ivy', 'email' => 'ivy@aipolicytracker.org'])
            ->assertSessionHas('success', fn (string $m) => str_starts_with($m, 'Invitation emailed to ivy@aipolicytracker.org'))
            ->assertSessionMissing('invitation_link');
    }

    public function test_a_refused_delivery_is_reported_and_the_account_still_gets_a_working_link(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::shouldReceive('to->send')->andThrow(new TransportException('Connection refused'));

        $this->actingAs($this->owner)->post(route('backend.admin.users.invite'), ['name' => 'Ivy', 'email' => 'ivy@aipolicytracker.org'])
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'not delivered') && str_contains($m, 'mail server'))
            ->assertSessionHas('invitation_link', fn (array $link) => str_contains($link['url'], '/invitation/'));
        $this->assertTrue(User::where('email', 'ivy@aipolicytracker.org')->sole()->invitationPending());
    }

    public function test_re_sending_on_a_mailer_that_delivers_nothing_shows_the_new_link(): void
    {
        config(['mail.default' => 'array']);
        Mail::fake();
        $ivy = User::factory()->unverified()->create();
        $ivy->forceFill(['invited_at' => now()->subDay(), 'invited_by' => $this->owner->id])->save();

        $this->actingAs($this->owner)->from(route('backend.admin.users.show', $ivy))->post(route('backend.admin.users.invitation', $ivy))
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'not delivered'))
            ->assertSessionHas('invitation_link', fn (array $link) => $link['user'] === $ivy->id);
    }

    public function test_the_list_shows_roles_but_changes_none_and_its_figures_are_valid_markup(): void
    {
        $target = $this->withRole(AdminRole::Reviewer);
        $html = $this->actingAs($this->owner)->get(route('backend.admin.users.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('action="'.route('backend.admin.users.role', $target).'"', $html, 'no per-row role form');
        $this->assertStringNotContainsString('aria-label="Role for', $html);

        // The figures are links, so they are not a definition list (a <dl> may hold only dt, dd and div groups).
        $xpath = $this->xpath($html);
        foreach ($xpath->query('//dl') as $dl) {
            foreach ($dl->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $this->assertContains($child->tagName, ['dt', 'dd', 'div', 'script', 'template'], 'a <dl> holds a <'.$child->tagName.'>');
                }
            }
        }
        $stats = $this->block($html, 'data-user-stats', '');
        $this->assertStringContainsString('href="'.route('backend.admin.users.index', ['status' => 'no_factor']).'"', $stats);

        // Each cell names itself, so the row reads as a card on a phone.
        foreach (['Role', 'Second factor', 'Status', 'Last signed in', 'Joined'] as $label) {
            $this->assertStringContainsString('data-label="'.$label.'"', $html);
        }
        $this->assertStringContainsString('class="adm-users-table"', $html);
    }

    public function test_the_bulk_bar_gives_each_action_its_own_button_and_inputs(): void
    {
        User::factory()->create();
        $html = $this->actingAs($this->owner)->get(route('backend.admin.users.index'))->assertOk()->getContent();
        $bulk = $this->block($html, 'id', 'bulk-users');

        $this->assertMatchesRegularExpression('#<select id="bulk-role" name="admin_role"[^>]*required#', $bulk);
        $this->assertStringNotContainsString('id="bulk-action"', $bulk, 'no select deciding what the other inputs mean');
        $this->assertMatchesRegularExpression('#name="action" value="role"(?![^>]*formnovalidate)[^>]*>Set role#', $bulk, 'Set role needs its role');
        foreach (['suspend', 'restore', 'verify'] as $action) {
            $this->assertMatchesRegularExpression('#name="action" value="'.$action.'" formnovalidate#', $bulk, "{$action} does not need a role");
        }
        $this->assertStringContainsString('formaction="'.route('backend.admin.users.bulk.delete').'" formnovalidate', $bulk);
    }

    public function test_setting_a_role_in_bulk_without_choosing_one_is_a_message_not_an_error_page(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->owner)->from(route('backend.admin.users.index'))
            ->post(route('backend.admin.users.bulk'), ['ids' => [$target->id], 'action' => 'role', 'admin_role' => ''])
            ->assertStatus(302)->assertRedirect(route('backend.admin.users.index'))
            ->assertSessionHas('error', 'Choose the role to set, then apply it again.');
        $this->assertNull($target->fresh()->adminRole());

        $this->get(route('backend.admin.users.index'))->assertOk()->assertSee('data-toast="error"', false);
    }

    public function test_a_role_change_on_the_account_page_is_confirmed_by_name(): void
    {
        $target = $this->withRole(AdminRole::Reviewer, ['email' => 'rita@example.test']);
        $html = $this->actingAs($this->owner)->get(route('backend.admin.users.show', $target))->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $this->assertStringContainsString('Current role', $this->block($html, 'data-role-option', 'reviewer'));

        $editor = $xpath->query("//button[@name='admin_role' and @value='editor']")->item(0);
        $this->assertNotNull($editor);
        $this->assertSame('Change rita@example.test from Reviewer to Editor? This gives them access they do not have now.', $editor->getAttribute('data-confirm'));
        $this->assertTrue($editor->hasAttribute('data-confirm-danger'), 'raising access is styled as dangerous');
        $this->assertSame(route('backend.admin.users.role', $target), $editor->parentNode->parentNode->parentNode->getAttribute('action'));

        $none = $xpath->query("//button[@name='admin_role' and @value='']")->item(0);
        $this->assertSame('Change rita@example.test from Reviewer to No admin access?', $none->getAttribute('data-confirm'));
        $this->assertFalse($none->hasAttribute('data-confirm-danger'), 'removing access is not raising it');

        // No select-and-save left on the page for the role.
        $this->assertStringNotContainsString('<select id="role"', $html);

        $this->post(route('backend.admin.users.role', $target), ['admin_role' => 'editor'])
            ->assertSessionHas('success', 'rita@example.test changed from Reviewer to Editor. They will be asked to enrol an authenticator at next sign-in.');
        $this->assertSame(AdminRole::Editor, $target->fresh()->adminRole());
    }

    public function test_the_permissions_matrix_keeps_its_headers_in_view_and_tracks_unsaved_changes(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backend.admin.users.permissions'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<h1[^>]*>\s*Role permissions\s*</h1>#', $html);
        $this->assertStringContainsString('class="table-wrap adm-matrix bg-white"', $html);
        $this->assertStringContainsString('data-unsaved-form', $html);
        $this->assertStringContainsString('data-unsaved-status', $html);
        $this->assertStringContainsString('type="reset"', $html);
        $this->assertMatchesRegularExpression('#data-confirm="Save these permissions\?[^"]*"[^>]*data-confirm-danger#', $html);
        $this->assertContains('password.confirm', app('router')->getRoutes()->getByName('backend.admin.users.permissions.update')->gatherMiddleware());
    }
}
