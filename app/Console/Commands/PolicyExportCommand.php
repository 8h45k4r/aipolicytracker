<?php

namespace App\Console\Commands;

use App\Services\PolicyData\OpenDataExporter;
use Illuminate\Console\Command;

class PolicyExportCommand extends Command
{
    protected $signature = 'policy:export {--out=storage/app/exports : Output directory}';

    protected $description = 'Export published policy data as a versioned JSON bundle for the open-data page';

    public function handle(OpenDataExporter $exporter): int
    {
        $dir = base_path($this->option('out'));
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error("Cannot create {$dir}");

            return self::FAILURE;
        }
        $bundle = $exporter->bundle();
        $file = $dir.'/aipolicytracker-'.now()->format('Y-m-d').'.json';
        file_put_contents($file, json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        file_put_contents($dir.'/aipolicytracker-latest.json', json_encode($bundle, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->info('Wrote '.$file.' ('.count($bundle['policies']).' policies).');

        return self::SUCCESS;
    }
}
