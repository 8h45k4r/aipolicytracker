<?php

namespace App\Console\Commands;

use App\Services\Consultations\FederalRegisterConsultations;
use Illuminate\Console\Command;
use Throwable;

/** Collects open AI comment periods from official sources (docs/modules/consultations.md). */
class SyncConsultationsCommand extends Command
{
    protected $signature = 'consultations:sync';

    protected $description = 'Collect open AI consultations and comment deadlines from official sources';

    public function handle(FederalRegisterConsultations $federalRegister): int
    {
        if (! config('consultations.enabled')) {
            $this->info('Consultation collection is switched off (CONSULTATIONS_SYNC).');

            return self::SUCCESS;
        }
        try {
            $c = $federalRegister->sync();
        } catch (Throwable $e) {
            $this->error('Federal Register: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info("Federal Register: {$c['seen']} documents with an open comment period, {$c['kept']} about AI; {$c['created']} new, {$c['updated']} updated, {$c['reopened']} confirmed entries back to review because their date moved.");

        return self::SUCCESS;
    }
}
