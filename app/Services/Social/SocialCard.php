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

            // The card is the site's own header and hero, not a poster: white page,
            // the real wordmark top-left, the mark faintly on the right, navy text,
            // and the footer as a navy band with the cyan rule the site uses.
            imagealphablending($image, true);
            imagefilledrectangle($image, 0, 0, $width, $height, $colour('background'));
            $band = 88;
            imagefilledrectangle($image, 0, $height - $band, $width, $height, $colour('panel'));
            imagefilledrectangle($image, 0, $height - $band - 4, $width, $height - $band, $colour('accent'));
            imagefilledrectangle($image, 0, 0, $width, 1, $colour('line'));

            $left = 72;
            $right = $width - 72;
            $panelBottom = $height - $band;

            $wordmark = $this->raster((string) config('social.wordmark'));
            if ($wordmark) {
                // Scale to the same height the site header shows it at, relative to the card.
                $h = 56;
                $w = (int) round(imagesx($wordmark) * $h / imagesy($wordmark));
                imagecopyresampled($image, $wordmark, $left, 48, 0, 0, $w, $h, imagesx($wordmark), imagesy($wordmark));
                imagedestroy($wordmark);
            }
            $mark = $this->raster((string) config('social.mark'));
            if ($mark) {
                // The mark, large and quiet on the right. Its own transparency has to be
                // kept: the merge function that would fade a whole layer ignores the alpha
                // channel and paints the transparent background as a grey square, so the
                // fade is applied per pixel to the alpha itself before the copy.
                $size = 300;
                $ghost = imagecreatetruecolor($size, $size);
                imagealphablending($ghost, false);
                imagesavealpha($ghost, true);
                imagefill($ghost, 0, 0, imagecolorallocatealpha($ghost, 0, 0, 0, 127));
                imagecopyresampled($ghost, $mark, 0, 0, 0, 0, $size, $size, imagesx($mark), imagesy($mark));
                for ($gx = 0; $gx < $size; $gx++) {
                    for ($gy = 0; $gy < $size; $gy++) {
                        $pixel = imagecolorat($ghost, $gx, $gy);
                        $alpha = ($pixel >> 24) & 0x7F;
                        if ($alpha < 127) {
                            imagesetpixel($ghost, $gx, $gy, imagecolorallocatealpha($ghost, ($pixel >> 16) & 0xFF, ($pixel >> 8) & 0xFF, $pixel & 0xFF, (int) min(127, 127 - (127 - $alpha) * 0.16)));
                        }
                    }
                }
                imagecopy($image, $ghost, $width - $size - 48, 150, 0, 0, $size, $size);
                imagedestroy($ghost);
                imagedestroy($mark);
                $right = $width - $size - 96;
            }

            // Compose first, then place. Laying the block out top-down left a long
            // dead band above the rule on short titles, which at thumbnail size
            // reads as an unfinished image; measuring first lets it sit centred
            // whether the title runs to one line or four.
            $eyebrow = ! empty($content['eyebrow']) ? mb_strtoupper($this->clean($content['eyebrow'])) : null;
            $titleLines = $this->wrap($this->clean($content['title']), $bold, 54, $right - $left, 4);
            $metaLines = ! empty($content['meta']) ? $this->wrap($this->clean($content['meta']), $regular, 26, $right - $left, 2) : [];

            $blockHeight = ($eyebrow ? 58 : 0) + (count($titleLines) * 66) + ($metaLines ? 22 + count($metaLines) * 40 : 0);
            // Below the wordmark, centred in what remains above the band.
            $top = 140;
            $y = max($top, $top + (int) (($panelBottom - $top - $blockHeight) / 2) - 12);

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
            imagettftext($image, 22, 0, $left, $height - 34, $colour('footer'), $regular, $footer);
            $domain = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'aipolicytracker.org';
            imagettftext($image, 22, 0, $width - 72 - $this->widthOf($domain, $bold, 22), $height - 34, $colour('footer'), $bold, $domain);

            ob_start();
            imagepng($image, null, 6);

            return (string) ob_get_clean();
        } finally {
            imagedestroy($image);
        }
    }

    /** A brand raster, or null when the file is missing so the card still draws. */
    private function raster(string $path): ?\GdImage
    {
        if ($path === '' || ! is_file($path)) {
            return null;
        }
        $image = @imagecreatefrompng($path);
        if ($image === false) {
            return null;
        }
        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
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
