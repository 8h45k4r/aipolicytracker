<?php

namespace App\Console\Commands;

use App\Models\RecordVerification;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * Writes admin verification decisions back into the YAML records under data/ so the
 * repository stays the source of truth. Only the review fields change; formatting of
 * the rest of the file is preserved by line-level replacement.
 */
class PolicyExportVerificationsCommand extends Command
{
    protected $signature = 'policy:export-verifications {--all : Export every stored verification, not only unexported ones}';

    protected $description = 'Write admin verification decisions (review status, confidence, date, reviewer) into data/ YAML files';

    public function handle(): int
    {
        $query = $this->option('all') ? RecordVerification::query() : RecordVerification::where('exported', false);
        $written = 0;
        foreach ($query->cursor() as $v) {
            $file = $v->record_type === 'policy'
                ? collect(glob(base_path('data/policies/*/'.$v->record_slug.'.yaml')))->first()
                : base_path('data/jurisdictions/'.$v->record_slug.'.yaml');
            if (! $file || ! is_file($file)) {
                $this->warn("No file for {$v->record_type} {$v->record_slug}");

                continue;
            }
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            $set = [
                'review_status' => $v->review_status,
                'confidence_level' => $v->confidence_level,
                'last_verified_at' => $v->last_verified_at?->toDateString(),
                'reviewed_by' => $v->reviewed_by,
                'last_checked_at' => now()->toDateString(),
            ];
            $done = [];
            foreach ($lines as $i => $line) {
                foreach ($set as $key => $value) {
                    if (preg_match('/^'.$key.':/', $line)) {
                        $lines[$i] = $key.': '.($value === null ? 'null' : Yaml::dump($value));
                        $done[$key] = true;
                    }
                }
            }
            foreach ($set as $key => $value) {
                if (! isset($done[$key])) {
                    $lines[] = $key.': '.($value === null ? 'null' : Yaml::dump($value));
                }
            }
            file_put_contents($file, implode("\n", $lines)."\n");
            $v->update(['exported' => true]);
            $written++;
            $this->line('updated '.str_replace(base_path().'/', '', $file));
        }
        $this->info("{$written} record(s) written. Run php artisan policy:validate, then open a pull request.");

        return self::SUCCESS;
    }
}
