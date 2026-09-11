<?php

namespace App\Mail;

use App\Models\Subscriber;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Collection;

class WeeklyDigestMail extends Mailable
{
    public function __construct(public Subscriber $subscriber, public Collection $changes, public Collection $deadlines, public string $periodLabel) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'AI policy digest: '.$this->periodLabel);
    }

    public function headers(): Headers
    {
        $url = route('subscribe.unsubscribe', $this->subscriber->token);

        return new Headers(text: [
            'List-Unsubscribe' => '<'.$url.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.site.digest', text: 'emails.site.digest-text', with: [
            'unsubscribeUrl' => route('subscribe.unsubscribe', $this->subscriber->token),
        ]);
    }
}
