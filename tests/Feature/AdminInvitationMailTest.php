<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Mail\AdminInvitationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInvitationMailTest extends TestCase
{
    use RefreshDatabase;

    /** The invitation names who sent it and nothing else about their account. */
    public function test_the_invitation_names_the_inviter_without_their_account_record(): void
    {
        $inviter = User::factory()->create(['name' => 'Olivia Owner', 'email' => 'olivia@example.org', 'last_login_at' => now()]);
        $invitee = User::factory()->create(['name' => 'Ivy', 'email' => 'ivy@example.org']);
        $mail = new AdminInvitationMail($invitee, $inviter, 'https://example.org/invitation/abc', AdminRole::cases()[0], 'Welcome');

        $mail->assertSeeInHtml('Olivia Owner')->assertSeeInText('Olivia Owner');
        foreach (['olivia@example.org', 'two_factor', 'last_login_at', '{&quot;id&quot;', '{"id"'] as $leak) {
            $mail->assertDontSeeInHtml($leak, false)->assertDontSeeInText($leak, false);
        }
    }
}
