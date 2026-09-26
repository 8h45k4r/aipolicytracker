<?php

namespace App\Services\Review;

use Symfony\Component\Yaml\Yaml;

/**
 * Sets a handful of scalar keys on a YAML record without rewriting the rest of the
 * file: comments, key order, quoting and block scalars all stay as the author left
 * them. Only lines that hold the named keys change; missing keys are appended.
 *
 * A record is either the whole file (keys at column 0) or one item of a list, found
 * by its slug, in which case the keys sit at that item's indentation.
 */
final class YamlRecordPatch
{
    /**
     * @param  array<string, scalar|null>  $set
     * @return bool false when a list item with that slug is not in the file
     */
    public static function apply(string $file, array $set, ?string $slug = null): bool
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];

        if ($slug === null) {
            [$start, $end, $indent] = [0, count($lines), ''];
        } else {
            $found = self::locate($lines, $slug);
            if ($found === null) {
                return false;
            }
            [$start, $end, $indent] = $found;
        }

        $done = [];
        for ($i = $start; $i < $end; $i++) {
            foreach ($set as $key => $value) {
                if (preg_match('/^'.preg_quote($indent, '/').preg_quote($key, '/').':(\s|$)/', $lines[$i])) {
                    $lines[$i] = $indent.self::line($key, $value);
                    $done[$key] = true;
                }
            }
        }
        $missing = [];
        foreach ($set as $key => $value) {
            if (! isset($done[$key])) {
                $missing[] = $indent.self::line($key, $value);
            }
        }
        if ($missing !== []) {
            // Inside a list item, append before the trailing blank lines so the next
            // item still starts where it did.
            $at = $end;
            while ($slug !== null && $at > $start && trim($lines[$at - 1]) === '') {
                $at--;
            }
            array_splice($lines, $at, 0, $missing);
        }

        file_put_contents($file, implode("\n", $lines)."\n");

        return true;
    }

    /**
     * The line range and key indentation of the list item whose slug matches.
     *
     * @param  list<string>  $lines
     * @return array{int, int, string}|null
     */
    private static function locate(array $lines, string $slug): ?array
    {
        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            if (! preg_match('/^(\s*)-\s+slug:\s*'.preg_quote($slug, '/').'\s*$/', $lines[$i], $m)) {
                continue;
            }
            $marker = $m[1];
            $indent = $marker.'  ';
            $end = $i + 1;
            while ($end < $count) {
                $line = $lines[$end];
                // The item ends at the next item at the same level, or at anything
                // shallower than its keys (a new top-level key, for instance).
                if (preg_match('/^'.preg_quote($marker, '/').'-\s/', $line) || (trim($line) !== '' && ! str_starts_with($line, $indent))) {
                    break;
                }
                $end++;
            }

            return [$i + 1, $end, $indent];
        }

        return null;
    }

    private static function line(string $key, mixed $value): string
    {
        return $key.': '.($value === null ? 'null' : rtrim(Yaml::dump($value)));
    }
}
