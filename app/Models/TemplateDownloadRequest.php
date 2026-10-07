<?php

namespace App\Models;

use App\Services\Templates\TemplateCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

/** A request for a template's files, made through the form on its page. */
class TemplateDownloadRequest extends Model
{
    /** How long the emailed links work. */
    public const LINK_DAYS = 7;

    /**
     * Below this a download count says more about the page being new than about
     * the template, so it is not shown.
     */
    public const DOWNLOADS_SHOWN_FROM = 25;

    /**
     * How access works, in one line, for every surface that states it: free and no
     * account, with the links mailed to the address given. Whether that must be a
     * work address follows the same setting the WorkEmail rule reads, so the line
     * cannot promise more or less than TemplateController::requestDownload enforces.
     */
    public static function accessLabel(): string
    {
        return 'Free · no account · links emailed to your '.(config('templates.gate.require_work_email', true) ? 'work address' : 'email address');
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return ['terms_accepted_at' => 'datetime', 'marketing_consent_at' => 'datetime', 'emailed_at' => 'datetime', 'first_downloaded_at' => 'datetime'];
    }

    /** @return array{title: string, slug: string}|null */
    public function template(): ?array
    {
        return TemplateCatalog::find($this->template_slug);
    }

    /** A signed, expiring link to one format of the template, attributed to this request. */
    public function downloadUrl(string $format): string
    {
        return URL::temporarySignedRoute('templates.download', now()->addDays(self::LINK_DAYS), ['slug' => $this->template_slug, 'format' => $format, 'request' => $this->id]);
    }
}
