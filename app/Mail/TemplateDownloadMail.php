<?php

namespace App\Mail;

use App\Models\TemplateDownloadRequest;
use App\Models\TemplateVersion;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The template's files, as signed links that work for a week. */
class TemplateDownloadMail extends Mailable
{
    public function __construct(public TemplateDownloadRequest $downloadRequest, public TemplateVersion $version) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your download: '.($this->downloadRequest->template()['title'] ?? 'AI governance template').' '.$this->version->label());
    }

    public function content(): Content
    {
        $links = collect($this->version->files ?? [])->map(fn ($f) => [
            'label' => strtoupper($f['format']),
            'size' => number_format(($f['bytes'] ?? 0) / 1024).' KB',
            'url' => $this->downloadRequest->downloadUrl($f['format']),
        ])->all();

        return new Content(view: 'emails.site.template-download', text: 'emails.site.template-download-text', with: [
            'name' => $this->downloadRequest->name,
            'title' => $this->downloadRequest->template()['title'] ?? $this->version->slug,
            'label' => $this->version->label(),
            'links' => $links,
            'days' => TemplateDownloadRequest::LINK_DAYS,
            'pageUrl' => $this->version->url(),
            'unsubscribeUrl' => route('templates.index'),
        ]);
    }
}
