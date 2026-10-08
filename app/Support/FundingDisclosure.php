<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\Funder;
use Illuminate\Database\QueryException;

/**
 * What /funding discloses, read in one place: the threshold, the sponsor link and the
 * funders. A value saved in the admin wins over config/funding.php; the config stays the
 * fallback so a fresh install, or one without the table yet, still renders the page.
 */
final class FundingDisclosure
{
    /** US dollars a year above which a funder must be listed. */
    public static function threshold(): int
    {
        $stored = self::setting('funding_threshold');

        return $stored !== null && ctype_digit($stored) ? (int) $stored : (int) config('funding.disclosure_threshold');
    }

    /** The public sponsorship page, https only, or null. */
    public static function sponsorUrl(): ?string
    {
        $url = self::setting('sponsor_url') ?? config('funding.sponsor_url');
        $url = is_string($url) ? trim($url) : '';

        return $url !== '' && str_starts_with(strtolower($url), 'https://') && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * The funders the public page lists, each as name, kind, amount, period, purpose, url
     * and ended (date or null). Published rows from the table; while the table holds no
     * row at all, the config list.
     *
     * @return list<array{name: string, kind: string, amount: string, period: ?string, purpose: string, url: ?string, ended: ?string}>
     */
    public static function funders(): array
    {
        try {
            if (Funder::query()->exists()) {
                return Funder::published()->ordered()->get()->map(fn (Funder $f) => [
                    'name' => $f->name,
                    'kind' => $f->kind,
                    'amount' => $f->amount_display,
                    'period' => $f->period,
                    'purpose' => $f->purpose,
                    'url' => $f->url,
                    'ended' => $f->ends_on && $f->ends_on->isPast() ? $f->ends_on->format('F Y') : null,
                ])->all();
            }
        } catch (QueryException) {
            // No table yet (a deploy before migrate): the config list still answers.
        }

        return array_values(array_map(fn (array $f) => [
            'name' => (string) ($f['name'] ?? ''),
            'kind' => (string) ($f['kind'] ?? ''),
            'amount' => (string) ($f['amount'] ?? ''),
            'period' => $f['period'] ?? null,
            'purpose' => (string) ($f['purpose'] ?? ''),
            'url' => $f['url'] ?? null,
            'ended' => null,
        ], (array) config('funding.funders')));
    }

    private static function setting(string $key): ?string
    {
        try {
            $value = AppSetting::get($key);
        } catch (QueryException) {
            return null;
        }

        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
