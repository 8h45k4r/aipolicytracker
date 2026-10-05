<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caches of what the records produce (listings, counts, the API, the world map),
 * kept apart from everything else in the cache store.
 *
 * A record change used to call Cache::flush(), which on the database store also
 * erased the rate-limiter counters (sign-in, two-factor and invitation throttles),
 * the scheduler heartbeat and the AI Incident Database sync marker. Content keys now
 * carry a generation number; flush() moves to the next generation, so stale content
 * is never read again and expires on its own TTL, and nothing else is touched.
 */
final class ContentCache
{
    private const GENERATION = 'content-cache.generation';

    public static function remember(string $key, \DateTimeInterface|\DateInterval|int|null $ttl, Closure $callback): mixed
    {
        return Cache::remember(self::key($key), $ttl, $callback);
    }

    public static function flush(): void
    {
        Cache::forever(self::GENERATION, self::generation() + 1);
    }

    public static function key(string $key): string
    {
        return 'content.g'.self::generation().'.'.$key;
    }

    private static function generation(): int
    {
        return (int) Cache::get(self::GENERATION, 1);
    }
}
