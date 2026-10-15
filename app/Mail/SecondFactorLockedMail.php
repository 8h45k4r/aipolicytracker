<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent to an admin when their account's second-factor check locks after too many wrong
 * codes: someone who has the password may be guessing, and the holder should know.
 */
class SecondFactorLockedMail extends Mailable
{
    public function __construct(public int $failures, public int $hours) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('aipolicytracker.site_name').': second-factor sign-in locked');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.site.second-factor-locked', text: 'emails.site.second-factor-locked-text', with: ['lockedAt' => now()]);
    }
}
