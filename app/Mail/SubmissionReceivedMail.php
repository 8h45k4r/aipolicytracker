<?php

namespace App\Mail;

use App\Models\ContributorSubmission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Notifies admins that a public submission (correction, source, policy, reviewer application) landed in the review queue. */
class SubmissionReceivedMail extends Mailable
{
    public function __construct(public ContributorSubmission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New submission: '.(ContributorSubmission::TYPES[$this->submission->type] ?? $this->submission->type));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.site.submission', text: 'emails.site.submission-text', with: [
            'adminUrl' => route('backend.admin.submissions', ['type' => $this->submission->type, 'status' => 'pending_review']),
            'unsubscribeUrl' => route('backend.admin.settings'),
        ]);
    }
}
