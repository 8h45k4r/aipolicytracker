<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * The next free permanent id of a kind, read from data/ (not the database), so a
 * contributor adding a record can give it an id that has never been used.
 */
class NextRecordIdCommand extends Command
{
    protected $signature = 'policy:next-id {kind : POL, OBL, CHG or CTL}';

    protected $description = 'Print the next unused permanent record id (AIPT-POL-0241 and so on)';

    public function handle(): int
    {
        $kind = strtoupper((string) $this->argument('kind'));
        if (! in_array($kind, ['POL', 'OBL', 'CHG', 'CTL'], true)) {
            $this->error('Kind must be POL, OBL, CHG or CTL.');

            return self::INVALID;
        }
        $max = 0;
        foreach (File::allFiles(base_path('data')) as $file) {
            if ($file->getExtension() !== 'yaml') {
                continue;
            }
            preg_match_all('/AIPT-'.$kind.'-(\d+)/', $file->getContents(), $m);
            foreach ($m[1] as $n) {
                $max = max($max, (int) $n);
            }
        }
        $this->line(sprintf('AIPT-%s-%04d', $kind, $max + 1));

        return self::SUCCESS;
    }
}
