<?php

namespace App\Support;

/**
 * Link attributes decided from where a URL points.
 *
 * Source links on records are marked nofollow because the site does not vouch
 * for every page it cites. A change whose "official source" is one of our own
 * pages (a template release, a methodology note) was getting the same mark,
 * which told crawlers not to follow a link into the site itself.
 */
final class Links
{
    /** The rel attribute for a source link: nofollow only when it leaves the site. */
    public static function sourceRel(?string $url): string
    {
        return self::isInternal($url) ? '' : 'noopener nofollow';
    }

    public static function isInternal(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '' || str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $own = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return $host !== '' && $own !== '' && ($host === $own || $host === 'www.'.$own || 'www.'.$host === $own);
    }
}
