<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone_no' => '9800000000',
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
            'terms_condition' => true,
        ]);

        $this->assertAuthenticated();
        // Registration lands on the intended page (or home) with a flash; verification is requested, not enforced.
        $response->assertRedirect(route('home', absolute: false));
        $this->assertNotNull(\App\Models\User::where('email', 'test@example.com')->first()->terms_accepted_at);
    }
}
