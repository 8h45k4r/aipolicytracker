<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sent to the old address when an account's email changes, so a takeover does not go unnoticed. */
class EmailChangedMail extends Mailable
{
    public function __construct(public string $name, public string $newAddress) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'The email address on your account was changed');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.site.email-changed', text: 'emails.site.email-changed-text', with: [
            'name' => $this->name,
            'masked' => self::mask($this->newAddress),
            'contactUrl' => route('contribute'),
        ]);
    }

    /** "j***@example.org": enough to recognise, not enough to hand an address to whoever reads the old inbox. */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}
