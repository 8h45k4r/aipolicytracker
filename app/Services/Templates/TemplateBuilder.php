<?php

namespace App\Services\Templates;

use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\TemplateVersion;
use Illuminate\Support\Facades\Storage;

/**
 * Builds a template from its definition and decides whether that is a new
 * version. The definition's content (sheets and blocks, as data) is hashed;
 * when the hash matches the latest version and its files are still on disk,
 * nothing is written. Otherwise the next version is rendered to disk, the
 * difference from the previous version is written up as a changelog, and a
 * change event is recorded so the updates hub, the feeds and the weekly
 * digest carry it (subscribers who chose the "templates" topic).
 */
final class TemplateBuilder
{
    public const DISK = 'local';

    public const CHANGE_SLUG_PREFIX = 'template-';

    public const PREVIEW_ROWS = 6;

    /** @return array{slug:string, version:TemplateVersion, changed:bool} */
    public function build(string $slug, bool $force = false): array
    {
        $meta = TemplateCatalog::find($slug) ?? throw new \InvalidArgumentException("Unknown template {$slug}");
        $definition = TemplateCatalog::definition($slug) ?? throw new \RuntimeException("No definition for template {$slug}");
        $formats = $meta['formats'] ?? ['xlsx'];

        $sheets = in_array('xlsx', $formats, true) ? $definition->sheets() : [];
        $blocks = in_array('docx', $formats, true) ? $definition->blocks() : [];
        $content = ['sheets' => $sheets, 'blocks' => $blocks];
        $hash = hash('sha256', json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $latest = TemplateVersion::latestFor($slug);
        if ($latest && ! $force && $latest->content_hash === $hash && $this->filesPresent($latest)) {
            return ['slug' => $slug, 'version' => $latest, 'changed' => false];
        }

        $number = ($latest?->version ?? 0) + 1;
        $dataset = DatasetVersion::current();
        $generatedAt = now();
        $readme = $this->readme($meta, $number, $dataset, $generatedAt);
        $dir = "templates/{$slug}/v{$number}";
        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory($dir);

        // Files in the catalogue's order, so the first format is the default download everywhere.
        $files = [];
        foreach ($formats as $format) {
            $name = "{$slug}-v{$number}.{$format}";
            if ($format === 'xlsx' && $sheets !== []) {
                (new Workbook($readme, $sheets))->save($disk->path("{$dir}/{$name}"));
            } elseif ($format === 'docx' && $blocks !== []) {
                (new Document($readme, $blocks))->save($disk->path("{$dir}/{$name}"));
            } else {
                continue;
            }
            $files[] = ['format' => $format, 'path' => "{$dir}/{$name}", 'filename' => $name, 'bytes' => $disk->size("{$dir}/{$name}")];
        }

        $stats = $this->stats($sheets, $blocks);
        $changelog = $this->changelog($latest, $stats, $dataset, $force && $latest?->content_hash === $hash);

        $version = TemplateVersion::create([
            'slug' => $slug,
            'version' => $number,
            'content_hash' => $hash,
            'dataset_version' => $dataset,
            'files' => $files,
            'stats' => $stats,
            'preview' => $this->preview($sheets, $blocks),
            'changelog' => $changelog,
            'generated_at' => $generatedAt,
        ]);

        // The first build is publication, not a change to announce; every later
        // one is a change to the records that reached a file people downloaded.
        if ($latest && $latest->content_hash !== $hash) {
            $this->recordChange($meta, $version);
        }

        return ['slug' => $slug, 'version' => $version, 'changed' => true];
    }

    /** @return array<string, array{version:TemplateVersion, changed:bool}> */
    public function buildAll(bool $force = false, ?callable $each = null): array
    {
        $out = [];
        foreach (TemplateCatalog::all()->keys() as $slug) {
            $out[$slug] = $this->build($slug, $force);
            if ($each) {
                $each($slug, $out[$slug]);
            }
        }

        return $out;
    }

    public function filesPresent(TemplateVersion $version): bool
    {
        $disk = Storage::disk(self::DISK);
        foreach ($version->files ?? [] as $f) {
            if (! $disk->exists($f['path'])) {
                return false;
            }
        }

        return $version->files !== [];
    }

    private function readme(array $meta, int $number, string $dataset, \DateTimeInterface $generatedAt): array
    {
        return [
            'title' => $meta['title'],
            'short' => $meta['short'] ?? '',
            'inside' => $meta['inside'] ?? [],
            'frameworks' => array_map(fn ($f) => TemplateCatalog::frameworkLabel($f), $meta['frameworks'] ?? []),
            'version' => 'v'.$number,
            'generated_at' => $generatedAt->format('Y-m-d'),
            'dataset_version' => $dataset,
            'url' => TemplateCatalog::url($meta['slug']),
            'licence' => config('templates.licence'),
            'disclaimer' => config('templates.disclaimer'),
        ];
    }

    /** What is in the content, in numbers: per-sheet rows, block counts, and how many rows or blocks cite a record. */
    private function stats(array $sheets, array $blocks): array
    {
        $perSheet = [];
        $cites = 0;
        foreach ($sheets as $s) {
            $perSheet[$s['name']] = count($s['rows']);
            $cites += count(array_filter($s['rows'], fn ($r) => ! empty($r['url'])));
        }
        $cites += count(array_filter($blocks, fn ($b) => $b['type'] === 'cite'));

        return ['sheets' => $perSheet, 'blocks' => count($blocks), 'headings' => count(array_filter($blocks, fn ($b) => in_array($b['type'], ['h1', 'h2', 'h3'], true))), 'citations' => $cites];
    }

    /**
     * What the page shows without opening a file: each sheet's columns and its
     * first rows, and the document's outline. Stored with the version so a page
     * view never re-runs the definition's queries.
     */
    private function preview(array $sheets, array $blocks): array
    {
        $out = ['sheets' => [], 'outline' => (new Document([], $blocks))->outline()];
        foreach ($sheets as $s) {
            $columns = array_map(fn ($c) => ['key' => $c['key'], 'label' => $c['label'], 'type' => $c['type'] ?? 'text', 'options' => isset($c['options']) ? count($c['options']) : null, 'formula' => isset($c['formula'])], $s['columns']);
            $rows = array_map(fn ($r) => array_map(fn ($c) => ($c['type'] ?? '') === 'formula' ? '=' : (is_scalar($r[$c['key']] ?? null) ? mb_substr((string) $r[$c['key']], 0, 160) : null), $s['columns']), array_slice($s['rows'], 0, self::PREVIEW_ROWS));
            $out['sheets'][] = ['name' => $s['name'], 'columns' => $columns, 'rows' => $rows, 'row_count' => count($s['rows']), 'editable_rows' => $s['editable_rows'] ?? 0, 'note' => $s['note'] ?? null];
        }

        return $out;
    }

    private function changelog(?TemplateVersion $previous, array $stats, string $dataset, bool $rebuiltUnchanged): string
    {
        if (! $previous) {
            return 'First version, built from dataset '.$dataset.'.';
        }
        if ($rebuiltUnchanged) {
            return 'Rebuilt without a content change (files regenerated from dataset '.$dataset.').';
        }
        $lines = [];
        if ($previous->dataset_version !== $dataset) {
            $lines[] = "Dataset {$previous->dataset_version} → {$dataset}.";
        }
        $before = $previous->stats['sheets'] ?? [];
        foreach ($stats['sheets'] as $name => $rows) {
            $was = $before[$name] ?? null;
            if ($was === null) {
                $lines[] = "New sheet \"{$name}\" ({$rows} rows).";
            } elseif ($was !== $rows) {
                $lines[] = sprintf('Sheet "%s": %d → %d rows (%+d).', $name, $was, $rows, $rows - $was);
            }
        }
        foreach (array_diff_key($before, $stats['sheets']) as $name => $rows) {
            $lines[] = "Sheet \"{$name}\" removed.";
        }
        if (($previous->stats['blocks'] ?? null) !== $stats['blocks']) {
            $lines[] = sprintf('Document: %d → %d blocks.', $previous->stats['blocks'] ?? 0, $stats['blocks']);
        }
        if (($previous->stats['citations'] ?? null) !== $stats['citations']) {
            $lines[] = sprintf('Record citations: %d → %d.', $previous->stats['citations'] ?? 0, $stats['citations']);
        }
        if ($lines === []) {
            $lines[] = 'Content of one or more rows changed (a duty, control, deadline or mapping was edited); the row counts are unchanged.';
        }

        return implode(' ', $lines);
    }

    /** Records the new version as a change event: routine, first-party, and verified by construction. */
    private function recordChange(array $meta, TemplateVersion $version): void
    {
        $jurisdiction = Jurisdiction::where('slug', 'international')->first();
        if (! $jurisdiction) {
            return;
        }
        ChangeEvent::updateOrCreate(['slug' => self::CHANGE_SLUG_PREFIX.$version->slug.'-v'.$version->version], [
            'jurisdiction_id' => $jurisdiction->id,
            'policy_instrument_id' => null,
            'occurred_on' => $version->generated_at->toDateString(),
            'title' => 'Template updated: '.$meta['title'].' '.$version->label(),
            'what_changed' => $version->changelog,
            'practical_impact' => 'Organisations using the '.$version->label().' file should download the new version; the README page in each file states which dataset it was built from.',
            'impact_level' => 'routine',
            'status_after' => null,
            'official_source_url' => $version->url(),
            'source_title' => $meta['title'].' '.$version->label(),
            'source_publisher' => config('aipolicytracker.site_name'),
            'source_document_date' => $version->generated_at->toDateString(),
            'source_tier' => 3,
            'review_status' => 'verified',
            'confidence_level' => 'high',
            'last_checked_at' => $version->generated_at,
            'last_verified_at' => $version->generated_at,
            'reviewed_by' => 'templates:build',
            'published_at' => $version->generated_at,
        ]);
    }
}
