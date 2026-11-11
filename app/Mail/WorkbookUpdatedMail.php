<?php

namespace App\Mail;

use App\Models\ApplicabilityProfile;
use App\Models\ProfileWorkbookVersion;
use App\Services\Workbooks\ComplianceWorkbook;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

/**
 * "The records behind your system's workbook changed." One email per new version,
 * listing what was added, dropped and changed, with a link to the new file. The
 * unsubscribe link is the one every alert email carries: it stops alert emails.
 */
class WorkbookUpdatedMail extends Mailable
{
    public function __construct(public ApplicabilityProfile $profile, public ProfileWorkbookVersion $version) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Compliance workbook updated: '.$this->profile->name.' (version '.$this->version->version.')');
    }

    public function headers(): Headers
    {
        return new Headers(text: ['List-Unsubscribe' => '<'.$this->unsubscribeUrl().'>']);
    }

    public function content(): Content
    {
        $lines = [];
        foreach (['added' => 'Added', 'removed' => 'No longer screened', 'changed' => 'Changed'] as $key => $word) {
            foreach ($this->version->record_changes[$key] ?? [] as $row) {
                $lines[] = ['word' => $word, 'title' => $row['title'], 'instrument' => $row['instrument'], 'url' => route('obligations.show', $row['slug']),
                    'fields' => isset($row['fields']) ? implode(', ', ComplianceWorkbook::fieldNames($row['fields'])) : null];
            }
        }

        return new Content(view: 'emails.site.workbook-updated', text: 'emails.site.workbook-updated-text', with: [
            'name' => $this->profile->name,
            'versionNumber' => $this->version->version,
            'lines' => array_slice($lines, 0, 25),
            'more' => max(0, count($lines) - 25),
            'pageUrl' => route('profiles.show', $this->profile),
            'downloadUrl' => route('profiles.workbook.version', [$this->profile, $this->version->version]),
            'unsubscribeUrl' => $this->unsubscribeUrl(),
        ]);
    }

    private function unsubscribeUrl(): string
    {
        return URL::signedRoute('alerts.unsubscribe', ['user' => $this->profile->user_id]);
    }
}
