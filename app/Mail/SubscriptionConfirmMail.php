<?php

namespace App\Mail;

use App\Models\Subscriber;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SubscriptionConfirmMail extends Mailable
{
    public function __construct(public Subscriber $subscriber) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your AI policy digest subscription');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.site.confirm', text: 'emails.site.confirm-text', with: [
            'confirmUrl' => route('subscribe.confirm', $this->subscriber->token),
            'unsubscribeUrl' => route('subscribe.unsubscribe', $this->subscriber->token),
        ]);
    }
}
