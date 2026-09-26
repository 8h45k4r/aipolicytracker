<?php

namespace App\Services\Localization;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Yaml\Yaml;

/**
 * Localised versions of the site's own summaries for the priority hubs and
 * records, kept in data/translations/<locale>/*.yaml. Only our own text is
 * translated, never the legal text; every entry names a reviewer or is
 * marked unreviewed, and an unreviewed page is served noindex. The English
 * page stays canonical for search until a translation is reviewed, at which
 * point the locale page becomes its own canonical with hreflang both ways.
 */
final class Translations
{
    public const LOCALES = ['es' => ['name' => 'Español', 'hreflang' => 'es', 'og' => 'es_ES'], 'id' => ['name' => 'Bahasa Indonesia', 'hreflang' => 'id', 'og' => 'id_ID'], 'pt-BR' => ['name' => 'Português (Brasil)', 'hreflang' => 'pt-BR', 'og' => 'pt_BR']];

    /** What a translation file may carry per record: our summaries, never the legal text. */
    public const FIELDS = ['title', 'answer', 'lead', 'faq'];

    public static function isLocale(string $locale): bool
    {
        return array_key_exists($locale, self::LOCALES);
    }

    public static function pattern(): string
    {
        return implode('|', array_map('preg_quote', array_keys(self::LOCALES)));
    }

    /** @return array<string, array> record key (e.g. "hub:japan") => translation */
    public static function all(string $locale): array
    {
        return Cache::remember('translations.'.$locale, 300, function () use ($locale) {
            $dir = base_path('data/translations/'.$locale);
            $out = [];
            foreach (is_dir($dir) ? glob($dir.'/*.yaml') : [] as $file) {
                foreach ((array) Yaml::parseFile($file) as $key => $entry) {
                    if (is_array($entry)) {
                        $out[$key] = $entry;
                    }
                }
            }

            return $out;
        });
    }

    public static function for(string $locale, string $key): ?array
    {
        return self::all($locale)[$key] ?? null;
    }

    /** Translation exists and a named reviewer signed it off. */
    public static function isReviewed(?array $entry): bool
    {
        return $entry !== null && ! empty($entry['reviewed_by']) && ! empty($entry['reviewed_on']);
    }

    /** @return list<array{locale:string, key:string, reviewed:bool}> every locale that has this record */
    public static function availableFor(string $key): array
    {
        $out = [];
        foreach (array_keys(self::LOCALES) as $locale) {
            $entry = self::for($locale, $key);
            if ($entry) {
                $out[] = ['locale' => $locale, 'key' => $key, 'reviewed' => self::isReviewed($entry)];
            }
        }

        return $out;
    }
}
