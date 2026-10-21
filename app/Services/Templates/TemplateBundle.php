<?php

namespace App\Services\Templates;

use App\Models\TemplateVersion;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Every template in the library, in every format, as one ZIP with a README
 * listing each file's version and the licence. The archive is named after the
 * versions it holds, so it is built once per set of versions and rebuilt only
 * when a template changes.
 */
class TemplateBundle
{
    public const DIR = 'templates/bundles';

    public function __construct(private TemplateBuilder $builder) {}

    /** @return array{path: string, filename: string, versions: array<string, TemplateVersion>} */
    public function build(): array
    {
        $versions = [];
        foreach (TemplateCatalog::all() as $meta) {
            $versions[$meta['slug']] = TemplateVersion::latestFor($meta['slug']) ?? $this->builder->build($meta['slug'])['version'];
        }
        $disk = Storage::disk(TemplateBuilder::DISK);
        $stamp = substr(hash('xxh128', collect($versions)->map(fn ($v) => $v->slug.'@'.$v->version)->join('|')), 0, 12);
        $path = self::DIR.'/aipolicytracker-templates-'.$stamp.'.zip';
        $filename = 'aipolicytracker-templates-'.now()->format('Y-m-d').'.zip';
        if ($disk->exists($path)) {
            return compact('path', 'filename', 'versions');
        }

        $disk->makeDirectory(self::DIR);
        $tmp = $disk->path($path.'.tmp');
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the template bundle.');
        }
        $lines = [];
        foreach ($versions as $slug => $version) {
            foreach ($version->files ?? [] as $file) {
                if (! $disk->exists($file['path'])) {
                    // The row survived but the file did not (a deploy that replaced the disk).
                    $version = $versions[$slug] = $this->builder->build($slug, true)['version'];
                    break;
                }
            }
            foreach ($version->files ?? [] as $file) {
                $zip->addFile($disk->path($file['path']), $slug.'/'.$file['filename']);
            }
            $lines[] = '- '.(TemplateCatalog::find($slug)['title'] ?? $slug).' ('.$version->label().', '.$version->generated_at?->format('j F Y').'): '.TemplateCatalog::url($slug);
        }
        $zip->addFromString('README.txt', implode("\n", [
            'AIPolicyTracker templates',
            'Built '.now()->format('j F Y').' from '.url('/templates'),
            '',
            config('templates.licence'),
            'Informational only; not legal advice. Check each duty against the official source linked in the file.',
            '',
            'Contents ('.count($versions).' templates):',
            ...$lines,
            '',
        ]));
        $zip->close();
        rename($tmp, $disk->path($path));
        // Only the archive for the current versions is kept.
        foreach ($disk->files(self::DIR) as $old) {
            if ($old !== $path) {
                $disk->delete($old);
            }
        }

        return compact('path', 'filename', 'versions');
    }
}
