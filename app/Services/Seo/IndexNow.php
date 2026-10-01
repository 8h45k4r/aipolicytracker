<?php

namespace App\Services\Seo;

use App\Models\ChangeEvent;
use App\Models\Control;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\TransitionMeasure;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Submits changed URLs to IndexNow (https://www.indexnow.org).
 *
 * "Changed" is read from updated_at, which only moves when a record's content changes
 * (the import leaves an unchanged row untouched), so a deploy that changes nothing
 * submits nothing and one corrected obligation submits that page and its listings.
 * A failure is logged and never fails the import or command that asked.
 */
final class IndexNow
{
    /** The protocol's limit per request. */
    public const BATCH = 10000;

    /** Record types with public pages, newest content first. */
    private const MODELS = [PolicyInstrument::class, Obligation::class, Control::class, Jurisdiction::class, ChangeEvent::class, TransitionMeasure::class];

    public function enabled(): bool
    {
        return self::validKey((string) config('services.indexnow.key'));
    }

    public static function validKey(string $key): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9-]{8,128}$/', $key);
    }

    /**
     * Public URLs of records changed since the given time, plus the listings that show
     * them, when anything changed at all.
     *
     * @return list<string>
     */
    public function changedSince(\DateTimeInterface $since): array
    {
        $urls = [];
        foreach (self::MODELS as $model) {
            $model::published()->where('updated_at', '>=', $since)->get()->each(function ($record) use (&$urls) {
                $urls[] = $record->url();
            });
        }
        if ($urls !== []) {
            array_push($urls, route('home'), route('updates.index'), route('changes.index'));
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param  iterable<string>  $urls
     * @return int how many URLs were accepted for submission
     */
    public function submit(iterable $urls): int
    {
        if (! $this->enabled()) {
            return 0;
        }
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        // Only this site's own URLs: IndexNow rejects the batch if any URL is on another host.
        $own = collect($urls)->filter(fn ($u) => parse_url((string) $u, PHP_URL_HOST) === $host)->unique()->values();
        if ($own->isEmpty() || ! $host) {
            return 0;
        }

        $key = (string) config('services.indexnow.key');
        $sent = 0;
        foreach ($own->chunk(self::BATCH) as $batch) {
            try {
                $response = Http::timeout(15)->acceptJson()->post((string) config('services.indexnow.endpoint'), [
                    'host' => $host,
                    'key' => $key,
                    'keyLocation' => url($key.'.txt'),
                    'urlList' => $batch->values()->all(),
                ]);
                // 200 and 202 both mean accepted; anything else is worth a line in the log.
                if ($response->successful()) {
                    $sent += $batch->count();
                } else {
                    Log::warning('IndexNow rejected a submission', ['status' => $response->status(), 'urls' => $batch->count()]);
                }
            } catch (\Throwable $e) {
                Log::warning('IndexNow submission failed', ['error' => $e->getMessage()]);
            }
        }

        return $sent;
    }
}
