<?php

namespace App\Services\Social;

use Illuminate\Support\Facades\Storage;

/**
 * Draws the image a platform shows when somebody shares a page.
 *
 * Every page used to share one static PNG with counts painted into it. A number
 * inside an image cannot be kept true, and that one had stopped being true: it
 * claimed 117 jurisdictions and 182 instruments against a corpus of 212 and 186.
 * Cards are now drawn from the record, and the figures on the default card are
 * read at render time, so the same drift cannot happen again.
 *
 * Drawing needs a TrueType font. The brand faces are served from a font CDN
 * rather than vendored, so this uses whichever system face resolves first. If
 * none does, nothing is drawn and the caller falls back to the static image —
 * a host without fonts behaves as the site did before rather than serving a
 * broken image.
 */
class SocialCard
{
    /**
     * @param  array{eyebrow?: ?string, title: string, meta?: ?string, footer?: ?string}  $content
     * @return string|null the path within the cache disk, or null when no font is available
     */
    public function render(string $key, array $content): ?string
    {
        if (! $this->available()) {
            return null;
        }

        // Keyed by what goes into it, so a retitled record gets a new file and no
        // cache needs to be cleared by hand on deploy.
        $path = trim((string) config('social.cache_path'), '/').'/'.$key.'-'.substr(hash('sha256', serialize($content).'|v1'), 0, 16).'.png';
        $disk = Storage::disk(config('social.cache_disk'));

        if ($disk->exists($path)) {
            return $path;
        }

        $png = $this->draw($content);
        if ($png === null) {
            return null;
        }
        $disk->put($path, $png);

        return $path;
    }

    /** Whether a usable font was found on this host. */
    public function available(): bool
    {
        return function_exists('imagettftext')
            && $this->font('bold') !== null
            && $this->font('regular') !== null;
    }

    /** First configured face that exists on this host, or null. */
    public function font(string $weight): ?string
    {
        foreach ((array) config("social.fonts.{$weight}", []) as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @param array{eyebrow?: ?string, title: string, meta?: ?string, footer?: ?string} $content */
    private function draw(array $content): ?string
    {
        $width = (int) config('social.width', 1200);
        $height = (int) config('social.height', 630);
        $bold = $this->font('bold');
        $regular = $this->font('regular');
        if ($bold === null || $regular === null) {
            return null;
        }

        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            return null;
        }

        try {
            $palette = (array) config('social.palette');
            $colour = fn (string $name) => imagecolorallocate($image, ...array_map('intval', $palette[$name]));

            imagefilledrectangle($image, 0, 0, $width, $height, $colour('background'));
            // A band of the primary navy, with the accent as a rule beneath it, so
            // the card reads as this site at thumbnail size rather than as a
            // rectangle of text.
            imagefilledrectangle($image, 0, 0, $width, $height - 96, $colour('panel'));
            imagefilledrectangle($image, 0, $height - 96, $width, $height - 90, $colour('accent'));

            $left = 72;
            $right = $width - 72;
            $panelBottom = $height - 96;

            // Compose first, then place. Laying the block out top-down left a long
            // dead band above the rule on short titles, which at thumbnail size
            // reads as an unfinished image; measuring first lets it sit centred
            // whether the title runs to one line or four.
            $eyebrow = ! empty($content['eyebrow']) ? mb_strtoupper($this->clean($content['eyebrow'])) : null;
            $titleLines = $this->wrap($this->clean($content['title']), $bold, 54, $right - $left, 4);
            $metaLines = ! empty($content['meta']) ? $this->wrap($this->clean($content['meta']), $regular, 26, $right - $left, 2) : [];

            $blockHeight = ($eyebrow ? 58 : 0) + (count($titleLines) * 66) + ($metaLines ? 22 + count($metaLines) * 40 : 0);
            $y = max(96, (int) (($panelBottom - $blockHeight) / 2));

            if ($eyebrow !== null) {
                $y += 24;
                imagettftext($image, 22, 0, $left, $y, $colour('accent'), $bold, $eyebrow);
                $y += 34;
            }

            foreach ($titleLines as $line) {
                $y += 66;
                imagettftext($image, 54, 0, $left, $y, $colour('text'), $bold, $line);
            }

            if ($metaLines !== []) {
                $y += 22;
                foreach ($metaLines as $line) {
                    $y += 40;
                    imagettftext($image, 26, 0, $left, $y, $colour('muted'), $regular, $line);
                }
            }

            $footer = $this->clean($content['footer'] ?? config('aipolicytracker.site_name'));
            imagettftext($image, 24, 0, $left, $height - 34, $colour('muted'), $regular, $footer);

            ob_start();
            imagepng($image, null, 6);

            return (string) ob_get_clean();
        } finally {
            imagedestroy($image);
        }
    }

    /** Collapse whitespace and drop control characters that would render as boxes. */
    private function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', preg_replace('/[\x00-\x1F\x7F]/u', '', $text)) ?? '');
    }

    /**
     * Break text into lines that fit, measuring the real glyphs rather than
     * counting characters, and ellipsize once the line budget is spent.
     *
     * @return list<string>
     */
    private function wrap(string $text, string $font, int $size, int $maxWidth, int $maxLines): array
    {
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if ($this->widthOf($candidate, $font, $size) <= $maxWidth) {
                $current = $candidate;

                continue;
            }
            if ($current !== '') {
                $lines[] = $current;
            }
            $current = $word;
            if (count($lines) === $maxLines) {
                break;
            }
        }
        if ($current !== '' && count($lines) < $maxLines) {
            $lines[] = $current;
        }

        // More text than lines: trim the last one back until the ellipsis fits, so
        // a title is cut at a word rather than clipped mid-glyph by the canvas.
        if (count($lines) === $maxLines && $this->widthOf($text, $font, $size) > $maxWidth * $maxLines) {
            $last = array_pop($lines);
            while ($last !== '' && $this->widthOf($last.'…', $font, $size) > $maxWidth) {
                $last = preg_replace('/\s*\S+$/u', '', $last) ?? '';
            }
            $lines[] = rtrim($last, ' ,;:').'…';
        }

        return array_values(array_filter($lines, fn ($l) => $l !== ''));
    }

    private function widthOf(string $text, string $font, int $size): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return $box === false ? PHP_INT_MAX : (int) abs($box[2] - $box[0]);
    }
}
