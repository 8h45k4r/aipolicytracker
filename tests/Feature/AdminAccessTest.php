<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_reach_the_backend(): void
    {
        $this->get('/backend/dashboard')->assertRedirect('/login');
    }

    public function test_non_admin_users_are_redirected_away_from_the_backend(): void
    {
        config(['aipolicytracker.admin_emails' => ['admin@example.com']]);
        $user = User::factory()->create(['email' => 'member@example.com']);

        $this->actingAs($user)->get('/backend/dashboard')
            ->assertRedirect(route('home', absolute: false));
    }

    public function test_configured_admins_can_reach_the_backend(): void
    {
        config(['aipolicytracker.admin_emails' => ['admin@example.com']]);
        $admin = User::factory()->create(['email' => 'admin@example.com']);

        $this->actingAs($admin)->get('/backend/dashboard')->assertOk();
    }

    public function test_removed_maintenance_routes_are_gone(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/clear-cache')->assertNotFound();
        $this->actingAs($user)->get('/storage-link')->assertNotFound();
    }
}
