<?php

namespace App\Mail;

use App\Models\ContributorSubmission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

/**
 * Tells the person who sent a submission what happened to it: received, accepted, not
 * accepted, or needs more information. Only the public note a reviewer wrote for readers
 * is included; the internal notes never leave the admin.
 */
class SubmissionOutcomeMail extends Mailable
{
    public const OUTCOMES = ['received', 'approved', 'rejected', 'needs_information'];

    public function __construct(public ContributorSubmission $submission, public string $outcome, public ?string $publicNote = null) {}

    /** @return array{0: string, 1: string} subject and heading */
    private function copy(): array
    {
        return match ($this->outcome) {
            'approved' => ['Your submission was accepted', 'A reviewer accepted your submission'],
            'rejected' => ['Your submission was not accepted', 'A reviewer reviewed your submission and did not accept it'],
            'needs_information' => ['We need more information about your submission', 'A reviewer needs more information before deciding'],
            default => ['We received your submission', 'Thank you. Your submission is in the review queue'],
        };
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->copy()[0]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.site.submitter', text: 'emails.site.submitter-text', with: [
            'heading' => $this->copy()[1],
            'summary' => Str::limit($this->submission->summary, 200),
            'publicNote' => $this->publicNote !== null && trim($this->publicNote) !== '' ? trim($this->publicNote) : null,
            'received' => $this->outcome === 'received',
            'contributeUrl' => route('contribute'),
            'corrections' => route('corrections'),
            'unsubscribeUrl' => null,
        ]);
    }
}
