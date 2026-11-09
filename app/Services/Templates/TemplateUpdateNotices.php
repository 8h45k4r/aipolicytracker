<?php

namespace App\Services\Templates;

use App\Mail\TemplateUpdateMail;
use App\Models\TemplateDownloadRequest;
use App\Models\TemplateUpdateNotice;
use App\Models\TemplateUpdateOptout;
use App\Models\TemplateVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * "Email me when this template gets a new version": who gets told, and the telling.
 *
 *  - queue():  a new version of a template (content changed, not the first build) creates
 *    one pending notice per address that asked for updates on that template and has not
 *    unsubscribed since. The unique index makes a repeated or concurrent build a no-op.
 *  - send():   claims each due notice atomically, re-checks the opt-out, mails it, and
 *    records the outcome. A failure is retried with backoff; an address that cannot be
 *    mailed, or the last failed attempt, is marked failed and never retried.
 *  - unsubscribe(): records the opt-out; pending notices for the address are suppressed.
 *
 * Only template readers who ticked the box are written to. Nobody is added to the weekly
 * digest, and the digest's own subscribers are untouched.
 */
class TemplateUpdateNotices
{
    public const MAX_ATTEMPTS = 3;

    /** Minutes to wait before attempt n+1 (after the first and second failure). */
    public const BACKOFF_MINUTES = [1 => 30, 2 => 180];

    /** @return int notices created for this version */
    public function queue(TemplateVersion $version): int
    {
        $rows = $this->eligible($version->slug)
            ->map(fn (string $email) => [
                'template_version_id' => $version->id, 'template_slug' => $version->slug, 'email' => $email,
                'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ])->values()->all();

        return $rows === [] ? 0 : DB::table('template_update_notices')->insertOrIgnore($rows);
    }

    /**
     * Addresses that asked for updates on this template, with the latest such request after
     * any unsubscribe (asking again on a later download opts back in).
     *
     * @return Collection<int, string>
     */
    public function eligible(string $slug): Collection
    {
        $consented = TemplateDownloadRequest::query()
            ->where('template_slug', $slug)->whereNotNull('marketing_consent_at')
            ->selectRaw('LOWER(email) as email, MAX(marketing_consent_at) as consented_at')
            ->groupByRaw('LOWER(email)')->get();
        $optouts = TemplateUpdateOptout::whereIn('email', $consented->pluck('email'))->pluck('unsubscribed_at', 'email');

        return $consented->filter(function ($row) use ($optouts) {
            $out = $optouts[$row->email] ?? null;

            return $out === null || Carbon::parse($row->consented_at)->gt($out);
        })->pluck('email')->values();
    }

    /** @return array{sent: int, failed: int, retry: int, suppressed: int} */
    public function send(int $limit = 200): array
    {
        $counts = ['sent' => 0, 'failed' => 0, 'retry' => 0, 'suppressed' => 0];
        $due = TemplateUpdateNotice::due()->orderBy('id')->limit($limit)->pluck('id');

        foreach ($due as $id) {
            // Claim it: only the run whose update moves it to "sending" mails it. Two runs
            // that listed the same notice cannot both win this conditional update.
            $claimed = TemplateUpdateNotice::whereKey($id)->where(fn ($q) => $q->due())
                ->update(['status' => 'sending', 'updated_at' => now()]);
            if ($claimed !== 1) {
                continue;
            }
            $notice = TemplateUpdateNotice::with('version')->find($id);
            $counts[$this->deliver($notice)]++;
        }

        return $counts;
    }

    public function unsubscribe(string $email, string $source): void
    {
        $email = strtolower($email);
        TemplateUpdateOptout::updateOrCreate(['email' => $email], ['unsubscribed_at' => now(), 'source' => $source]);
        TemplateUpdateNotice::where('email', $email)->whereIn('status', ['pending', 'sending'])->update(['status' => 'suppressed', 'updated_at' => now()]);
    }

    /** @return 'sent'|'failed'|'retry'|'suppressed' */
    private function deliver(TemplateUpdateNotice $notice): string
    {
        $meta = TemplateCatalog::find($notice->template_slug);
        if (! $notice->version || ! $meta || ! $this->eligible($notice->template_slug)->contains(strtolower($notice->email))) {
            $notice->update(['status' => 'suppressed']);

            return 'suppressed';
        }
        if (filter_var($notice->email, FILTER_VALIDATE_EMAIL) === false) {
            $notice->update(['status' => 'failed', 'attempts' => $notice->attempts + 1, 'last_error' => 'Not a valid email address.']);

            return 'failed';
        }

        try {
            Mail::to($notice->email)->send(new TemplateUpdateMail($notice, $notice->version, $meta));
            $notice->update(['status' => 'sent', 'attempts' => $notice->attempts + 1, 'sent_at' => now(), 'last_error' => null]);

            return 'sent';
        } catch (Throwable $e) {
            $attempts = $notice->attempts + 1;
            $final = $attempts >= self::MAX_ATTEMPTS;
            // The error class and message only: never the address, which is already on the row.
            $notice->update([
                'status' => $final ? 'failed' : 'pending', 'attempts' => $attempts,
                'next_attempt_at' => $final ? null : now()->addMinutes(self::BACKOFF_MINUTES[$attempts] ?? 180),
                'last_error' => mb_substr(class_basename($e).': '.$e->getMessage(), 0, 500),
            ]);
            Log::warning('Template update email failed', ['notice' => $notice->id, 'attempt' => $attempts, 'final' => $final]);

            return $final ? 'failed' : 'retry';
        }
    }
}
