<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * The admin sidebar for one account on one request, built from config/admin-navigation.php:
 * sections, entries the account may open, and their submenus, each with its address and
 * whether it is the page being shown. The layout only draws what this returns.
 */
class Navigation
{
    /**
     * @return array<string, list<array{label: string, url: string, icon: string, route: string, active: bool, open: bool,
     *     children: list<array{label: string, url: string, active: bool}>}>>
     */
    public static function for(User $user, Request $request): array
    {
        $out = [];
        foreach (config('admin-navigation.sections', []) as $section => $entries) {
            $items = [];
            foreach ($entries as $entry) {
                if (! empty($entry['can']) && ! $user->can($entry['can'])) {
                    continue;
                }
                $children = self::children($entry, $request);
                $here = $request->routeIs(self::pattern($entry['route']));
                $childActive = collect($children)->contains('active', true);
                $items[] = [
                    'label' => $entry['label'],
                    'url' => route($entry['route']),
                    'icon' => $entry['icon'] ?? 'dot',
                    'route' => $entry['route'],
                    'active' => $here || $childActive,
                    'open' => $here || $childActive,
                    'children' => $children,
                ];
            }
            if ($items !== []) {
                $out[$section] = $items;
            }
        }

        return $out;
    }

    /** The section and entry for the page being shown, for the top bar. */
    public static function current(array $sections): ?array
    {
        foreach ($sections as $section => $items) {
            foreach ($items as $item) {
                if ($item['active']) {
                    $child = collect($item['children'])->firstWhere('active', true);

                    return ['section' => $section, 'label' => $item['label'], 'child' => $child['label'] ?? null];
                }
            }
        }

        return null;
    }

    /**
     * A submenu entry is active when the request is its route with every one of its filter
     * values. With none of the submenu's filters in the request, the entry without filters
     * is the one shown, or else the first (a page's default view).
     */
    private static function children(array $entry, Request $request): array
    {
        $list = $entry['children'] ?? [];
        if ($list === []) {
            return [];
        }
        $keys = collect($list)->flatMap(fn ($c) => array_keys($c['params'] ?? []))->unique();
        $filtered = $keys->contains(fn ($k) => $request->query($k) !== null);
        $default = collect($list)->search(fn ($c) => ($c['route'] ?? $entry['route']) === $entry['route'] && ($c['params'] ?? []) === []);
        $default = $default === false ? 0 : $default;

        return collect($list)->values()->map(function ($c, $i) use ($entry, $request, $filtered, $default) {
            $route = $c['route'] ?? $entry['route'];
            $params = $c['params'] ?? [];
            $onRoute = $request->routeIs($route);
            $active = $onRoute && ($params !== []
                ? collect($params)->every(fn ($v, $k) => (string) $request->query($k) === (string) $v)
                : ($route !== $entry['route'] || ! $filtered));
            if (! $active && $onRoute && ! $filtered && $i === $default && $route === $entry['route']) {
                $active = true;
            }

            return ['label' => $c['label'], 'url' => route($route, $params), 'active' => $active];
        })->all();
    }

    private static function pattern(string $route): string
    {
        return preg_match('/\.index$/', $route) ? preg_replace('/\.index$/', '.*', $route) : $route.'*';
    }
}
