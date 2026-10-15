<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /** The placeholder ADMIN_PASSWORD in .env.example. Never a real account's password. */
    public const PLACEHOLDER_PASSWORD = 'change-me-to-a-long-random-password';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = config('aipolicytracker.seed_admin.email');
        $password = config('aipolicytracker.seed_admin.password');

        if (! $email || ! $password) {
            $this->command?->warn('ADMIN_EMAIL / ADMIN_PASSWORD not set; skipping admin user seeding.');

            return;
        }

        // A copied .env.example would otherwise create an administrator whose password
        // is published in the repository.
        if ($password === self::PLACEHOLDER_PASSWORD && ! app()->environment(['local', 'testing'])) {
            $this->command?->warn('ADMIN_PASSWORD is still the .env.example placeholder; skipping admin user seeding. Set a real password and seed again.');

            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => config('aipolicytracker.seed_admin.name') ?: 'Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info("Admin user ensured for {$email}. Add this address to ADMIN_EMAILS to grant backend access.");
    }
}
