<?php

namespace App\Services\PolicyData;

use Symfony\Component\Yaml\Yaml;

/**
 * Reads the canonical YAML records from the data/ directory.
 */
class PolicyDataRepository
{
    public function __construct(private readonly string $baseDir) {}

    public static function default(): self
    {
        return new self(base_path('data'));
    }

    public function baseDir(): string
    {
        return $this->baseDir;
    }

    public function schemaDir(): string
    {
        return $this->baseDir.'/schema';
    }

    /** @return array<string, array> taxonomy => list of terms */
    public function taxonomies(): array
    {
        $file = $this->baseDir.'/taxonomies/terms.yaml';

        return is_file($file) ? (array) Yaml::parseFile($file) : [];
    }

    /** @return array<string, array> relative path => record */
    public function jurisdictions(): array
    {
        return $this->parseDirectory('jurisdictions');
    }

    /** @return array<string, array> relative path => record */
    public function policies(): array
    {
        return $this->parseDirectory('policies');
    }

    /** @return array<string, array> relative path => control record */
    public function controls(): array
    {
        return $this->parseDirectory('controls');
    }

    /** @return array<string, array> relative path => reviewer roster entry */
    public function reviewers(): array
    {
        return $this->parseDirectory('reviewers');
    }

    /** @return array<string, array> relative path => file contents ({changes: [...]}) */
    public function changeFiles(): array
    {
        return $this->parseDirectory('changes');
    }

    private function parseDirectory(string $dir): array
    {
        $root = $this->baseDir.'/'.$dir;
        if (! is_dir($root)) {
            return [];
        }
        $out = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $files = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['yaml', 'yml'], true)) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        foreach ($files as $path) {
            $relative = ltrim(str_replace($this->baseDir, '', $path), '/');
            $parsed = Yaml::parseFile($path, Yaml::PARSE_DATETIME);
            $out[$relative] = $this->normaliseDates($parsed);
        }

        return $out;
    }

    /** YAML turns bare dates into DateTime objects; keep them as ISO strings for validation. */
    private function normaliseDates(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->normaliseDates($v);
            }
        }

        return $value;
    }
}
