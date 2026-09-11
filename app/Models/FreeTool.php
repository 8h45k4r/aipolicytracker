<?php

namespace App\Models;

use Illuminate\Support\Collection;

/**
 * Read model over config/resources.php: the free templates, checklists, registers and
 * plans listed on /guides. Files live under resources/downloads/<slug>/ and are served only
 * through FreeToolController::file (auth + signed URL).
 */
class FreeTool
{
    public static function all(): Collection
    {
        return collect(config('resources.tools', []))->map(fn ($t, $slug) => $t + ['slug' => $slug, 'kind' => 'tool'])->values();
    }

    public static function find(string $slug): ?array
    {
        $tool = config('resources.tools.'.$slug);

        return $tool ? $tool + ['slug' => $slug, 'kind' => 'tool'] : null;
    }

    /** Editorial guides from config/content.php as cards with the same filter fields. */
    public static function guides(): Collection
    {
        $tags = config('resources.guide_tags', []);

        return collect(config('content.guides', []))->filter(fn ($g) => isset($g['summary']))->map(fn ($g, $slug) => [
            'slug' => $slug, 'kind' => 'guide', 'type' => 'guide', 'title' => $g['h1'], 'short' => $g['summary'],
            'frameworks' => $tags[$slug]['frameworks'] ?? array_values(array_filter([$g['framework'] ?? null])),
            'topics' => $tags[$slug]['topics'] ?? [], 'featured' => $tags[$slug]['featured'] ?? false, 'files' => [],
        ])->values();
    }

    /** @return array{path:string,label:string}|null */
    public static function file(string $slug, string $fileName): ?array
    {
        $tool = self::find($slug);
        foreach ($tool['files'] ?? [] as [$name, $label]) {
            if ($name === $fileName) {
                $path = resource_path('downloads/'.$slug.'/'.$name);

                return is_file($path) ? ['path' => $path, 'label' => $label] : null;
            }
        }

        return null;
    }
}
