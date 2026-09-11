<?php

namespace App\Console\Commands;

use App\Services\PolicyData\PolicyDataRepository;
use App\Services\PolicyData\PolicyDataValidator;
use App\Services\PolicyData\PolicyImporter;
use App\Services\PolicyData\SchemaValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PolicyImportCommand extends Command
{
    protected $signature = 'policy:import {--path= : Alternative data directory} {--skip-validation : Import without validating first}';

    protected $description = 'Validate and import the data/ policy records into the database (idempotent upsert)';

    public function handle(): int
    {
        $repository = $this->option('path') ? new PolicyDataRepository($this->option('path')) : PolicyDataRepository::default();

        if (! $this->option('skip-validation')) {
            $errors = (new PolicyDataValidator($repository, new SchemaValidator($repository->schemaDir())))->run();
            if ($errors !== []) {
                $this->error('Validation failed; run php artisan policy:validate for details. Nothing imported.');

                return self::FAILURE;
            }
        }

        $stats = (new PolicyImporter($repository))->run();
        Cache::flush();

        $this->info(sprintf(
            'Imported %d jurisdictions, %d policies, %d obligations, %d change events, %d taxonomy terms.',
            $stats['jurisdictions'], $stats['policies'], $stats['obligations'], $stats['changes'], $stats['terms']
        ));

        return self::SUCCESS;
    }
}
