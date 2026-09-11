<?php

namespace Database\Seeders;

use App\Models\Tool;
use App\Models\ToolFile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds the initial free tools from config/resources.php and copies their files from
 * resources/downloads/ onto the private disk. Idempotent: existing slugs are left as the
 * admin edited them; only missing tools and missing files are created, and a seeded file
 * that has gone missing from the disk is restored from resources/downloads/.
 */
class ToolSeeder extends Seeder
{
    public function run(): void
    {
        $order = 0;
        foreach (config('resources.tools', []) as $slug => $t) {
            $order += 10;
            $tool = Tool::firstOrCreate(['slug' => $slug], [
                'title' => $t['title'], 'type' => $t['type'], 'short' => $t['short'], 'purpose' => $t['purpose'] ?? null,
                'fields' => $t['fields'] ?? [], 'instructions' => $t['instructions'] ?? [], 'frameworks' => $t['frameworks'] ?? [], 'topics' => $t['topics'] ?? [],
                'related_guides' => $t['related_guides'] ?? [], 'related_policies' => $t['related_policies'] ?? [], 'next_slug' => $t['next'] ?? null,
                'version' => $t['version'] ?? '1.0', 'updated_on' => $t['updated'] ?? now()->toDateString(), 'featured' => (bool) ($t['featured'] ?? false),
                'status' => 'published', 'sort_order' => $order,
            ]);
            $fileOrder = 0;
            foreach ($t['files'] ?? [] as [$name, $label]) {
                $fileOrder += 10;
                $source = resource_path('downloads/'.$slug.'/'.$name);
                if (! is_file($source)) {
                    continue;
                }
                $path = 'tools/'.$slug.'/'.$name;
                if ($existing = $tool->files()->where('file_name', $name)->first()) {
                    // Row survived but the file did not (e.g. a clean deploy replaced the storage folder): restore it.
                    if (! Storage::disk(ToolFile::DISK)->exists($existing->disk_path)) {
                        Storage::disk(ToolFile::DISK)->put($existing->disk_path, file_get_contents($source));
                    }

                    continue;
                }
                Storage::disk(ToolFile::DISK)->put($path, file_get_contents($source));
                $tool->files()->create(['file_name' => $name, 'label' => $label, 'disk_path' => $path, 'mime' => mime_content_type($source) ?: null,
                    'size' => filesize($source), 'checksum' => hash_file('sha256', $source), 'version' => $tool->version, 'is_active' => true, 'sort_order' => $fileOrder]);
            }
        }
    }
}
