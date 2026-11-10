<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Mail\AdminInvitationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
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

    /**
     * The logo and social icons travel inside the message as inline parts, so they show in
     * clients that block remote images; the footer names only the site, and an invitation
     * carries no "you subscribed" line.
     */
    public function test_the_sent_invitation_embeds_its_images_and_has_a_plain_footer(): void
    {
        $inviter = User::factory()->create(['name' => 'Olivia Owner']);
        $invitee = User::factory()->create(['email' => 'ivy@example.org']);
        Mail::to($invitee->email)->send(new AdminInvitationMail($invitee, $inviter, 'https://example.org/invitation/abc', AdminRole::cases()[0], null));

        /** @var Email $email */
        $email = Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $html = $email->getHtmlBody();
        $inline = collect($email->getAttachments())->filter(fn ($part) => $part->getDisposition() === 'inline');
        $socials = count(config('aipolicytracker.social'));

        $this->assertGreaterThan(0, $socials);
        $this->assertCount($socials + 1, $inline, 'the logo and each social icon are embedded');
        $this->assertSame($socials + 1, substr_count($html, 'src="cid:'));
        $this->assertStringNotContainsString('/brand/social/', $html, 'no remote icon links');
        $this->assertStringNotContainsString('logo-on-light.png"', $html);
        $this->assertStringNotContainsStringIgnoringCase('certifyi', $html);
        $this->assertStringNotContainsString('Unsubscribe', $html);
        $this->assertStringContainsString('aipolicytracker.org</a>', $html);
    }
}
