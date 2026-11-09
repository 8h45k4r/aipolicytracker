<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/** One email telling one reader that a template they asked about has a new version. */
class TemplateUpdateNotice extends Model
{
    public const STATUSES = ['pending', 'sending', 'sent', 'failed', 'suppressed'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['next_attempt_at' => 'datetime', 'sent_at' => 'datetime', 'attempts' => 'integer'];
    }

    /** Minutes after which a notice left in "sending" (a run that died mid-send) is taken up again. */
    public const STALE_MINUTES = 30;

    /** Waiting to be sent now: pending and past any backoff, or abandoned mid-send. */
    public function scopeDue(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->where('status', 'pending')->where(fn ($t) => $t->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now())))
            ->orWhere(fn ($w) => $w->where('status', 'sending')->where('updated_at', '<', now()->subMinutes(self::STALE_MINUTES)));
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class, 'template_version_id');
    }

    /**
     * The unsubscribe address in the email and its List-Unsubscribe header. Signed and keyed
     * by this notice, so the address never appears in the URL and cannot be guessed or forged.
     */
    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('templates.updates.unsubscribe', ['notice' => $this->id]);
    }
}
