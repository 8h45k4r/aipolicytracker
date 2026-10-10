<?php

namespace App\Services\Social;

use App\Enums\ImpactLevel;
use App\Models\ChangeEvent;

/**
 * The post for one change: a flag and the headline, one sentence on what
 * changed, the link, then hashtags and mentions.
 *
 *   🇪🇺 Urgent: EU AI Act general-purpose AI duties apply
 *
 *   Providers of general-purpose AI models must now keep technical documentation…
 *
 *   https://aipolicytracker.org/changes/…
 *
 *   #AIPolicy #EUAIAct
 *   (the mentions, on their own line)
 *
 * X counts every link as 23 characters and most emoji as 2, so the length is
 * measured the way X measures it, and the sentence gives way first.
 */
final class ChangePost
{
    public const LIMIT = 280;

    private const URL_WEIGHT = 23;

    public static function compose(ChangeEvent $change): string
    {
        $change->loadMissing(['jurisdiction', 'policyInstrument']);
        $head = trim(self::flag($change->jurisdiction?->iso_code).' '.(self::impact($change) === ImpactLevel::Urgent ? 'Urgent: ' : '').self::clean($change->title));
        $tail = implode("\n", array_filter([self::hashtagLine($change), self::mentionLine()]));
        $url = $change->url();

        $fixed = $head."\n\n".'%s'."\n\n".$url.($tail !== '' ? "\n\n".$tail : '');
        $room = self::LIMIT - self::weight(sprintf($fixed, ''));
        $sentence = self::sentence((string) $change->what_changed, $room - 2);

        return $sentence === ''
            ? str_replace("%s\n\n", '', $fixed)
            : sprintf($fixed, $sentence);
    }

    /** Length as X counts it: links 23, a flag 2, CJK and emoji 2, the rest 1. */
    public static function weight(string $text): int
    {
        $text = preg_replace('~https?://\S+~u', str_repeat('x', self::URL_WEIGHT), $text);
        // A flag is two regional indicators and counts as one emoji.
        $text = preg_replace('/[\x{1F1E6}-\x{1F1FF}]{2}/u', "\u{1F310}", $text);
        $weight = 0;
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            $cp = mb_ord($char);
            if ($cp === 0xFE0F || $cp === 0x200D) {
                continue;
            }
            $weight += ($cp <= 4351 || ($cp >= 8192 && $cp <= 8205) || ($cp >= 8208 && $cp <= 8223) || ($cp >= 8242 && $cp <= 8247)) ? 1 : 2;
        }

        return $weight;
    }

    /**
     * Up to three: the configured base tag, the instrument as one word
     * ("#EUAIAct"), and the place when the instrument tag does not name it.
     * A strategy gets #AIStrategy rather than a second generic tag.
     *
     * @return list<string>
     */
    public static function hashtags(ChangeEvent $change): array
    {
        $tags = [];
        foreach (preg_split('/[\s,]+/', (string) config('social.x.hashtags'), -1, PREG_SPLIT_NO_EMPTY) as $tag) {
            $tags[] = self::tag($tag);
        }
        $policy = $change->policyInstrument;
        if ($policy && $policy->instrument_type === 'strategy') {
            $tags[] = '#AIStrategy';
        }
        $instrument = $policy ? self::tag((string) $policy->short_title) : null;
        if ($instrument !== null && mb_strlen($instrument) <= 21) {
            $tags[] = $instrument;
        }
        $place = $change->jurisdiction && $change->jurisdiction->slug !== 'international'
            ? self::tag((string) ($change->jurisdiction->short_name ?: $change->jurisdiction->name))
            : null;
        if ($place !== null && ($instrument === null || ! str_starts_with(mb_strtolower($instrument), mb_strtolower($place)))) {
            $tags[] = $place;
        }

        $seen = [];
        $out = [];
        foreach (array_filter($tags) as $tag) {
            if (! isset($seen[mb_strtolower($tag)])) {
                $seen[mb_strtolower($tag)] = true;
                $out[] = $tag;
            }
        }

        return array_slice($out, 0, 3);
    }

    /** @return list<string> Valid @handles from the setting, in order. */
    public static function mentions(): array
    {
        $handles = [];
        foreach (preg_split('/[\s,]+/', (string) config('social.x.mentions'), -1, PREG_SPLIT_NO_EMPTY) as $handle) {
            $handle = '@'.ltrim($handle, '@');
            if (preg_match('/^@[A-Za-z0-9_]{1,15}$/', $handle) && ! in_array(mb_strtolower($handle), array_map('mb_strtolower', $handles), true)) {
                $handles[] = $handle;
            }
        }

        return $handles;
    }

    private static function hashtagLine(ChangeEvent $change): string
    {
        return implode(' ', self::hashtags($change));
    }

    private static function mentionLine(): string
    {
        return implode(' ', self::mentions());
    }

    /** "EU AI Act" → "#EUAIAct". Null when nothing usable is left or it is only digits. */
    private static function tag(string $text): ?string
    {
        $word = preg_replace('/[^\p{L}\p{N}_]/u', '', ltrim(trim($text), '#'));

        return $word !== '' && preg_match('/\p{L}/u', $word) ? '#'.$word : null;
    }

    /** 🇪🇺 from "EU", 🇺🇸 from "US-CO"; 🌐 when there is no country code. */
    private static function flag(?string $iso): string
    {
        $code = strtoupper(substr((string) $iso, 0, 2));
        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            return "\u{1F310}";
        }

        return mb_chr(0x1F1E6 + ord($code[0]) - 65).mb_chr(0x1F1E6 + ord($code[1]) - 65);
    }

    private static function impact(ChangeEvent $change): ImpactLevel
    {
        return ImpactLevel::tryFrom((string) $change->impact_level) ?? ImpactLevel::Routine;
    }

    /** The first sentence, cut at a word with an ellipsis if even that is too long. */
    private static function sentence(string $text, int $room): string
    {
        $text = self::clean($text);
        if ($text === '' || $room < 40) {
            return '';
        }
        $first = preg_split('/(?<=[.!?])\s+(?=[A-Z0-9"“(])/u', $text, 2)[0];
        if (self::weight($first) <= $room) {
            return $first;
        }
        $words = explode(' ', $first);
        $out = '';
        foreach ($words as $word) {
            $next = $out === '' ? $word : $out.' '.$word;
            if (self::weight($next.'…') > $room) {
                break;
            }
            $out = $next;
        }

        // An aside cut open ("a synthetic performer (a…") reads as a typo; end before it.
        if (substr_count($out, '(') > substr_count($out, ')')) {
            $out = rtrim(mb_substr($out, 0, mb_strrpos($out, '(')));
        }

        return $out === '' ? '' : rtrim($out, ' ,;:–—-').'…';
    }

    private static function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
