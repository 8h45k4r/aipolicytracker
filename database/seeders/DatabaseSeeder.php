<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Structured policy records are loaded
     * separately by `php artisan policy:import` from the data/ directory.
     */
    public function run(): void
    {
        $this->call(AdminSeeder::class);
    }
}
