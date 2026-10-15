<?php

namespace App\Console\Commands;

use App\Services\PolicyData\PolicyDataRepository;
use App\Services\PolicyData\PolicyDataValidator;
use App\Services\PolicyData\PolicyImporter;
use App\Services\PolicyData\SchemaValidator;
use App\Services\Seo\IndexNow;
use App\Support\ContentCache;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class PolicyImportCommand extends Command
{
    protected $signature = 'policy:import {--path= : Alternative data directory} {--skip-validation : Import without validating first}';

    protected $description = 'Validate and import the data/ policy records into the database (idempotent upsert)';

    public function handle(IndexNow $indexNow): int
    {
        $repository = $this->option('path') ? new PolicyDataRepository($this->option('path')) : PolicyDataRepository::default();

        if (! $this->option('skip-validation')) {
            $errors = (new PolicyDataValidator($repository, new SchemaValidator($repository->schemaDir())))->run();
            if ($errors !== []) {
                $this->error('Validation failed; run php artisan policy:validate for details. Nothing imported.');

                return self::FAILURE;
            }
        }

        // A second of slack: updated_at is stored to the second.
        $started = now()->subSecond();
        // One import at a time across every container and the scheduler: two at once
        // interleave their updates, and each unpublishes what the other has not written yet.
        // The lock lives in the shared cache store, not a file in one container's /tmp.
        try {
            $stats = Cache::lock('policy:import', 900)->block(600, fn () => (new PolicyImporter($repository))->run());
        } catch (LockTimeoutException) {
            $this->error('Another import has held the lock for ten minutes; nothing imported.');

            return self::FAILURE;
        }
        ContentCache::flush();

        $this->info(sprintf(
            'Imported %d jurisdictions, %d policies, %d obligations, %d change events, %d taxonomy terms.',
            $stats['jurisdictions'], $stats['policies'], $stats['obligations'], $stats['changes'], $stats['terms']
        ));

        // Only the pages whose records changed in this import, and only when a key is set.
        if ($indexNow->enabled()) {
            $urls = $indexNow->changedSince($started);
            if ($urls !== []) {
                $this->info('IndexNow: submitted '.$indexNow->submit($urls).' changed URLs.');
            }
        }

        return self::SUCCESS;
    }
}
