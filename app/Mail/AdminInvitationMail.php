<?php

namespace App\Mail;

use App\Enums\AdminRole;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** An invitation from Backend → Users: who invited them, what for, and the link to choose a password. */
class AdminInvitationMail extends Mailable
{
    public const DAYS = 7;

    public function __construct(public User $invitee, public User $inviter, public string $url, public ?AdminRole $role = null, public ?string $note = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You are invited to '.config('aipolicytracker.site_name', 'AIPolicyTracker'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.site.invitation', text: 'emails.site.invitation-text', with: [
            'name' => $this->invitee->name,
            'inviter' => $this->inviter->name ?: $this->inviter->email,
            'role' => $this->role,
            'note' => $this->note,
            'url' => $this->url,
            'days' => self::DAYS,
            'site' => config('aipolicytracker.site_name', 'AIPolicyTracker'),
            'unsubscribeUrl' => url('/'),
        ]);
    }
}
