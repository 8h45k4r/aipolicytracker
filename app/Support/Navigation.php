<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reads config/navigation.php for the header, the mobile menu and the section hubs, so
 * the three cannot disagree about what a group contains.
 */
final class Navigation
{
    /** @return array<string, array<string, mixed>> */
    public static function groups(): array
    {
        return config('navigation.primary', []);
    }

    /** @return array<string, mixed>|null */
    public static function group(string $key): ?array
    {
        return self::groups()[$key] ?? null;
    }

    public static function url(array $item): string
    {
        return route($item['route'], $item['params'] ?? []);
    }

    /** Every entry in the group, in order. */
    public static function items(array $group): Collection
    {
        return collect($group['sections'])->flatMap(fn ($s) => $s['items']);
    }

    /** The five to seven entries the menu shows. */
    public static function menu(array $group): Collection
    {
        return self::items($group)->filter(fn ($i) => ! empty($i['menu']))->values();
    }

    /** True when the group lists pages its menu leaves out, so the menu links to the hub. */
    public static function hasMore(array $group): bool
    {
        return self::items($group)->count() > self::menu($group)->count();
    }

    /**
     * The group the page at $path (no leading slash) belongs to. A group's 'paths' prefixes
     * decide first, so a record page under /policies/ reads as Policies even when another
     * group links to that very record; an exact link match only settles pages no prefix
     * claims, such as a top-level landing listed in one menu.
     */
    public static function currentGroup(string $path, string $url): ?string
    {
        $path = trim($path, '/');

        foreach (self::groups() as $key => $group) {
            if ($path === 'explore/'.$key) {
                return $key;
            }
        }

        foreach (self::groups() as $key => $group) {
            foreach ($group['paths'] ?? [] as $prefix) {
                if (Str::is($prefix, $path) || Str::is($prefix.'/*', $path)) {
                    return $key;
                }
            }
        }

        foreach (self::groups() as $key => $group) {
            if (self::items($group)->contains(fn ($item) => self::url($item) === $url)) {
                return $key;
            }
        }

        return null;
    }
}
