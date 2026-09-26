<?php

namespace App\Console\Commands;

use App\Models\RecordVerification;
use App\Services\Review\ReviewableTypes;
use App\Services\Review\YamlRecordPatch;
use Illuminate\Console\Command;

/**
 * Writes admin verification decisions back into the YAML records under data/ so the
 * repository stays the source of truth. Only the review fields change; the rest of
 * each file is preserved line for line.
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
            $file = ReviewableTypes::has($v->record_type) ? ReviewableTypes::file($v->record_type, $v->record_slug) : null;
            if (! $file) {
                $this->warn("No file for {$v->record_type} {$v->record_slug}");

                continue;
            }
            $set = [
                'review_status' => $v->review_status,
                'confidence_level' => $v->confidence_level,
                'last_verified_at' => $v->last_verified_at?->toDateString(),
                'reviewed_by' => $v->reviewed_by,
                'last_checked_at' => now()->toDateString(),
            ];
            if (! YamlRecordPatch::apply($file, $set, ReviewableTypes::isListFile($v->record_type) ? $v->record_slug : null)) {
                $this->warn("No entry {$v->record_slug} in ".str_replace(base_path().'/', '', $file));

                continue;
            }
            $v->update(['exported' => true]);
            $written++;
            $this->line('updated '.str_replace(base_path().'/', '', $file));
        }
        $this->info("{$written} record(s) written. Run php artisan policy:validate, then open a pull request.");

        return self::SUCCESS;
    }
}
