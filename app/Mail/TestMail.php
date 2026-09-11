<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Branded test message sent from Admin → Settings to verify the mail transport. */
class TestMail extends Mailable
{
    public function __construct(public string $transport) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('aipolicytracker.site_name').' mail test');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.site.test', text: 'emails.site.test-text', with: ['sentAt' => now()]);
    }
}
