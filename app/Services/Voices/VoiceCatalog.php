<?php

namespace App\Services\Voices;

use App\Services\PolicyData\PolicyDataRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * AI Voices profiles, read from data/voices (one YAML file per person, validated by
 * policy:validate against data/schema/voice.schema.json). People are chosen by the office
 * they hold, never ranked; a profile says only what an official source says about the role.
 * Read on demand and cached by the files' modification times, so a deploy that changes a
 * profile shows it at once.
 */
final class VoiceCatalog
{
    public const CATEGORIES = [
        'regulator' => 'Regulators',
        'government' => 'Governments',
        'legislator' => 'Legislators',
        'safety_institute' => 'AI safety institutes',
        'international' => 'International bodies',
        'standards' => 'Standards bodies',
        'judiciary' => 'Courts',
    ];

    public const TOPICS = [
        'policy' => 'Policy', 'regulation' => 'Regulation', 'safety' => 'Safety', 'governance' => 'Governance',
        'responsible_ai' => 'Responsible AI', 'strategy' => 'Strategy', 'risk' => 'Risk', 'incidents' => 'Incidents', 'standards' => 'Standards',
    ];

    /** @return Collection<string, array> slug => published profile, sorted by name */
    public static function all(): Collection
    {
        $dir = base_path('data/voices');
        $files = glob($dir.'/*.yaml') ?: [];
        $stamp = md5(implode('|', array_map(fn ($f) => $f.':'.filemtime($f), $files)));

        return collect(Cache::remember('voices:'.$stamp, 3600, fn () => collect(PolicyDataRepository::default()->voices())
            ->values()
            ->filter(fn ($r) => ($r['published'] ?? false) === true && ! empty($r['slug']))
            ->keyBy('slug')
            ->sortBy(fn ($r) => mb_strtolower($r['name']))
            ->all()));
    }

    public static function find(string $slug): ?array
    {
        return self::all()->get($slug);
    }

    /** First and last initials, as the cards and the share image show them without a photo. */
    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).(count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : ''));
    }

    /**
     * The profile's photo on disk, or null. A photo is kept only with an open licence and
     * its author (`photo` in the record, docs/modules/voices.md); the file sits under
     * public/images/voices so the page serves it as it is.
     */
    public static function photoPath(array $voice): ?string
    {
        $file = $voice['photo']['file'] ?? null;
        if (! is_string($file) || ! preg_match('/^[a-z0-9-]+\.(jpg|png)$/', $file)) {
            return null;
        }
        $path = public_path('images/voices/'.$file);

        return is_file($path) ? $path : null;
    }

    /** The public URL of the profile's photo, or null. */
    public static function photoUrl(array $voice): ?string
    {
        return self::photoPath($voice) ? asset('images/voices/'.$voice['photo']['file']) : null;
    }
}
