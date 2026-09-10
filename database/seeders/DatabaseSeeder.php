<?php

namespace Database\Seeders;

use Database\Seeders\backend\CountrySeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * AiPolicyTrackerSeeder loads illustrative sample data only; it is not a
     * source-verified dataset. See SOURCE_ATTRIBUTION.md.
     */
    public function run(): void
    {
        $this->call(CountrySeeder::class);
        $this->call(AdminSeeder::class);
        $this->call(StatusSeeder::class);
        $this->call(AiPolicyTrackerSeeder::class);
    }
}
