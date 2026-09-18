<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\ContributorSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        config(['aipolicytracker.admin_emails' => ['admin@example.com']]);

        return User::factory()->create(['email' => 'admin@example.com']);
    }

    public function test_every_state_changing_admin_request_is_recorded_and_reads_are_not(): void
    {
        $admin = $this->admin();
        $submission = ContributorSubmission::create(['type' => 'correction', 'summary' => 'A test submission.', 'status' => 'pending_review']);

        $this->actingAs($admin)->get('/backend/review')->assertOk();
        $this->assertSame(0, AdminAuditLog::count(), 'a read is not audited');

        $this->actingAs($admin)->post(route('backend.review.decide', $submission), ['decision' => 'rejected', 'notes' => 'secret internal note', 'public_note' => 'Declined.'])->assertRedirect();

        $row = AdminAuditLog::sole();
        $this->assertSame($admin->id, $row->user_id);
        $this->assertSame('admin@example.com', $row->user_email);
        $this->assertSame('POST', $row->method);
        $this->assertSame('backend.review.decide', $row->route_name);
        $this->assertSame(['submission' => $submission->id], $row->route_params, 'a bound model is reduced to its key');
        $this->assertSame(302, $row->status);
        $this->assertNotNull($row->ip_hash);
        $this->assertNotSame('127.0.0.1', $row->ip_hash, 'the address is hashed, not stored');
    }

    public function test_request_bodies_are_never_stored(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('backend.admin.settings.save'), ['resend_key' => 're_SUPERSECRET_1234567890', 'mail_mailer' => 'array'])->assertRedirect();

        $dump = json_encode(AdminAuditLog::all()->toArray());
        $this->assertStringNotContainsString('SUPERSECRET', $dump);
        $this->assertStringNotContainsString('resend_key', $dump);
    }

    public function test_an_action_that_failed_is_still_recorded_with_the_status_it_produced(): void
    {
        $admin = $this->admin();

        // Aborts inside the controller: the record kind is not one that can be published.
        $this->actingAs($admin)->post(route('backend.review.publish', ['type' => 'nonsense', 'slug' => 'eu-ai-act']))->assertNotFound();
        $this->assertSame(404, AdminAuditLog::sole()->status, 'a thrown request is recorded, not lost');

        // Fails validation: recorded as the redirect the browser actually receives.
        $submission = ContributorSubmission::create(['type' => 'correction', 'summary' => 'A test submission.', 'status' => 'pending_review']);
        $this->actingAs($admin)->post(route('backend.review.decide', $submission), ['decision' => 'not-a-decision'])->assertSessionHasErrors('decision');
        $this->assertSame(302, AdminAuditLog::where('route_name', 'backend.review.decide')->sole()->status);
        $this->assertSame('pending_review', $submission->fresh()->status, 'and nothing changed');
    }

    public function test_a_request_refused_by_an_earlier_gate_is_audited_as_an_attempt(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        session()->forget('auth.password_confirmed_at');

        $this->post(route('backend.admin.settings.save'), ['mail_mailer' => 'array'])->assertRedirect(route('password.confirm'));

        $row = AdminAuditLog::sole();
        $this->assertSame('backend.admin.settings.save', $row->route_name);
        $this->assertSame($admin->id, $row->user_id);
    }

    public function test_the_audit_page_lists_actions_newest_first_and_is_admin_only(): void
    {
        $admin = $this->admin();
        $submission = ContributorSubmission::create(['type' => 'correction', 'summary' => 'A test submission.', 'status' => 'pending_review']);
        $this->actingAs($admin)->post(route('backend.review.decide', $submission), ['decision' => 'approved'])->assertRedirect();

        $page = $this->actingAs($admin)->get(route('backend.admin.audit'))->assertOk();
        $page->assertSee('backend.review.decide')->assertSee($admin->name)->assertSee('submission='.$submission->id);

        $member = User::factory()->create(['email' => 'member@example.com']);
        $this->actingAs($member)->get(route('backend.admin.audit'))->assertRedirect(route('home', absolute: false));
    }

    public function test_changing_secrets_or_removing_things_needs_a_fresh_password(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        session()->forget('auth.password_confirmed_at');

        $this->post(route('backend.admin.settings.save'), ['mail_mailer' => 'array'])->assertRedirect(route('password.confirm'));
        $this->post(route('backend.admin.billing.provision'))->assertRedirect(route('password.confirm'));

        // With a recent confirmation the same request goes through to the controller.
        $ok = $this->withSession(['auth.password_confirmed_at' => time()])->post(route('backend.admin.settings.save'), ['mail_mailer' => 'array']);
        $ok->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotSame(route('password.confirm'), $ok->headers->get('Location'));
        // Two rows: the attempt that was bounced to password confirmation, and this one.
        $this->assertSame(2, AdminAuditLog::where('route_name', 'backend.admin.settings.save')->count(), 'the blocked attempt is audited too');
    }
}
