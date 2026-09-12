<?php

namespace App\Mail;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sent once when a renewal payment fails and the subscription goes on hold; access continues for the grace period. */
class SubscriptionPaymentFailedMail extends Mailable
{
    public function __construct(public User $user, public Subscription $subscription) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Action needed: your '.config('aipolicytracker.site_name').' payment did not go through');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.site.payment-failed', text: 'emails.site.payment-failed-text', with: [
            'graceDays' => (int) config('billing.on_hold_grace_days', 7),
            'accountUrl' => route('profile.edit'),
            'unsubscribeUrl' => route('profile.edit'),
        ]);
    }
}
