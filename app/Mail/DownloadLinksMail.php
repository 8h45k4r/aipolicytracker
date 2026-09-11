<?php

namespace App\Mail;

use App\Models\ResourceDownload;
use App\Models\Tool;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

/** Sent after a free-tool download is recorded: signed links to every format plus the related guide. */
class DownloadLinksMail extends Mailable
{
    public function __construct(public ResourceDownload $download, public Tool $tool) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.$this->tool->title.' download links');
    }

    public function content(): Content
    {
        $links = $this->tool->activeFiles->map(fn ($f) => ['label' => $f->label, 'url' => URL::temporarySignedRoute('tools.file', now()->addHours(24), ['slug' => $this->tool->slug, 'download' => $this->download->id, 'file' => $f->file_name])])->all();
        $guide = collect(config('content.guides'))->only($this->tool->related_guides ?? [])->map(fn ($g, $s) => ['slug' => $s, 'h1' => $g['h1']])->first();
        $next = $this->tool->next_slug ? Tool::published()->where('slug', $this->tool->next_slug)->first() : null;

        return new Content(view: 'emails.site.download-links', text: 'emails.site.download-links-text', with: ['links' => $links, 'guide' => $guide, 'next' => $next, 'unsubscribeUrl' => route('profile.edit')]);
    }
}
