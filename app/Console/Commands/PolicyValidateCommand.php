<?php

namespace App\Console\Commands;

use App\Services\PolicyData\PolicyDataRepository;
use App\Services\PolicyData\PolicyDataValidator;
use App\Services\PolicyData\SchemaValidator;
use Illuminate\Console\Command;

class PolicyValidateCommand extends Command
{
    protected $signature = 'policy:validate {--path= : Alternative data directory}';

    protected $description = 'Validate the data/ policy records against their JSON Schemas and cross-reference rules';

    public function handle(): int
    {
        $repository = $this->option('path') ? new PolicyDataRepository($this->option('path')) : PolicyDataRepository::default();
        $validator = new PolicyDataValidator($repository, new SchemaValidator($repository->schemaDir()));

        $errors = $validator->run();
        $files = count($repository->jurisdictions()) + count($repository->policies()) + count($repository->changeFiles()) + count($repository->reviewers()) + 1;

        if ($errors === []) {
            $this->info("OK: {$files} data files validated with no errors.");

            return self::SUCCESS;
        }

        $total = 0;
        foreach ($errors as $file => $messages) {
            $this->error($file);
            foreach ($messages as $message) {
                $this->line('  - '.$message);
                $total++;
            }
        }
        $this->newLine();
        $this->error("{$total} validation error(s) in ".count($errors).' file(s).');

        return self::FAILURE;
    }
}
