<?php

namespace App\Mail;

use App\Models\TemplateUpdateNotice;
use App\Models\TemplateVersion;
use App\Services\Templates\TemplateCatalog;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * "A template you asked about has a new version." Sent once per version per address, only
 * to people who ticked the box on that template's request form. Carries RFC 8058 one-click
 * unsubscribe, which stops these emails and nothing else.
 */
class TemplateUpdateMail extends Mailable
{
    /** @param array<string, mixed> $meta the template's catalogue entry */
    public function __construct(public TemplateUpdateNotice $notice, public TemplateVersion $version, public array $meta) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New version of '.$this->meta['title'].': '.$this->version->label());
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->notice->unsubscribeUrl().'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        $related = TemplateCatalog::related($this->version->slug, 4)
            ->map(fn ($m, $slug) => ['title' => $m['title'], 'url' => TemplateCatalog::url($slug)])->values()->all();

        return new Content(view: 'emails.site.template-update', text: 'emails.site.template-update-text', with: [
            'title' => $this->meta['title'],
            'label' => $this->version->label(),
            'date' => $this->version->generated_at?->format('j F Y'),
            'changelog' => trim((string) $this->version->changelog),
            'pageUrl' => TemplateCatalog::url($this->version->slug),
            'related' => $related,
            'unsubscribeUrl' => $this->notice->unsubscribeUrl(),
        ]);
    }
}
