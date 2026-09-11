<?php

namespace Database\Seeders;

use App\Services\PolicyData\PolicyDataRepository;
use App\Services\PolicyData\PolicyImporter;
use Database\Seeders\backend\CountrySeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Reference data and the admin account are seeded first, then the
     * source-backed legacy map dataset (database/data/ai_policies.json) and the
     * structured policy-intelligence records in data/. Both are idempotent.
     */
    public function run(): void
    {
        $this->call(CountrySeeder::class);
        $this->call(AdminSeeder::class);
        $this->call(StatusSeeder::class);
        $this->call(AiPolicyTrackerSeeder::class);

        $stats = (new PolicyImporter(PolicyDataRepository::default()))->run();
        $this->command?->info(sprintf('Imported %d jurisdictions and %d policies from data/.', $stats['jurisdictions'], $stats['policies']));
    }
}
