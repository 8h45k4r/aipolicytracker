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
     * Reference data and the admin account are seeded, then the source-backed
     * policy records in data/ are imported. The legacy AiPolicyTrackerSeeder
     * holds illustrative sample data only and is no longer run by default; call
     * it explicitly with `php artisan db:seed --class=AiPolicyTrackerSeeder`.
     */
    public function run(): void
    {
        $this->call(CountrySeeder::class);
        $this->call(AdminSeeder::class);
        $this->call(StatusSeeder::class);

        $stats = (new PolicyImporter(PolicyDataRepository::default()))->run();
        $this->command?->info(sprintf('Imported %d jurisdictions and %d policies from data/.', $stats['jurisdictions'], $stats['policies']));
    }
}
