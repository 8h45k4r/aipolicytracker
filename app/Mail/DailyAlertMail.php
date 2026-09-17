<?php

namespace App\Mail;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Collection;

/** Daily alert for a Pro account: changes since the last alert and upcoming dates for the records it follows. */
class DailyAlertMail extends Mailable
{
    /** @param  array<int, list<string>>  $reasons  change id => names of the saved profiles it may affect */
    public function __construct(public User $user, public Collection $changes, public Collection $deadlines, public CarbonInterface $since, public CarbonInterface $until, public array $reasons = []) {}

    public function envelope(): Envelope
    {
        $n = $this->changes->count();
        $affected = array_values(array_unique(array_merge(...array_values($this->reasons) ?: [[]])));
        $subject = match (true) {
            $n > 0 && count($affected) === 1 => $n.' '.($n === 1 ? 'change' : 'changes').' that may affect '.$affected[0],
            $n > 0 => $n.' '.($n === 1 ? 'change' : 'changes').' in the AI policies you follow',
            default => 'Application date approaching for a policy you follow',
        };

        return new Envelope(subject: $subject);
    }

    public function headers(): Headers
    {
        return new Headers(text: ['List-Unsubscribe' => '<'.route('following.index').'>']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.site.alert', text: 'emails.site.alert-text', with: [
            'periodLabel' => $this->since->format('j M').' – '.$this->until->format('j M Y'),
            'manageUrl' => route('following.index'),
            'unsubscribeUrl' => route('following.index'),
        ]);
    }
}
