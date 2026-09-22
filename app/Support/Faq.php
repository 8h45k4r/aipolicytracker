<?php

namespace App\Support;

/**
 * The per-page questions in config/faq.php, with tokens resolved.
 *
 * Tokens exist because a config file cannot read another config file: `config:cache`
 * evaluates each one in isolation, so `config('aipolicytracker.data_license')` written inside
 * config/faq.php resolves to null and the answer ships with a gap in the sentence. Values that
 * come from elsewhere are therefore written as tokens and substituted here, at render time.
 */
class Faq
{
    /** @return list<array{question: string, answer: string}> */
    public static function for(string $key): array
    {
        // Read the whole map and index it directly. Route names contain dots and config()
        // reads a dot as nesting, so config('faq.policies.index') looks for
        // faq['policies']['index'] and silently returns nothing.
        $items = config('faq', [])[$key] ?? [];

        if ($items === []) {
            return [];
        }

        $tokens = [':license' => (string) config('aipolicytracker.data_license')];

        return array_map(fn (array $item) => [
            'question' => strtr($item['question'], $tokens),
            'answer' => strtr($item['answer'], $tokens),
        ], $items);
    }
}
