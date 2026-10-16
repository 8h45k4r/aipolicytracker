<?php

namespace App\Services\Admin;

use App\Enums\AdminRole;
use App\Http\Controllers\Auth\InvitationController;
use App\Mail\AdminInvitationMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Sends an account invitation: a week-long link to choose a password. Shared by the admin
 * users page and `admin:invite`, so both report delivery the same way and neither claims
 * an email was sent when the mailer only keeps a copy.
 */
final class AdminInvitations
{
    /** Mailers that keep a copy and deliver nothing. */
    public const NON_DELIVERING_MAILERS = ['log', 'array'];

    /** @return array{delivered: bool, url: string, reason: ?string} */
    public static function send(User $user, User $inviter, ?AdminRole $role, ?string $note): array
    {
        $token = Password::broker(InvitationController::BROKER)->createToken($user);
        $url = route('invitation.show', ['token' => $token, 'email' => $user->email]);
        $mailer = (string) config('mail.default');

        try {
            Mail::to($user->email)->send(new AdminInvitationMail($user, $inviter, $url, $role, $note));
        } catch (TransportExceptionInterface $e) {
            report($e);

            return ['delivered' => false, 'url' => $url, 'reason' => 'the mail server refused it or could not be reached.'];
        }

        if (in_array($mailer, self::NON_DELIVERING_MAILERS, true)) {
            return ['delivered' => false, 'url' => $url, 'reason' => "this server's mailer is \"{$mailer}\", which keeps a copy and sends nothing."];
        }

        return ['delivered' => true, 'url' => $url, 'reason' => null];
    }
}
