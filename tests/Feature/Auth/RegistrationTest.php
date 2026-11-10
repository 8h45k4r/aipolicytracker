<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserInfo;
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
        $this->assertNotNull(User::where('email', 'test@example.com')->first()->terms_accepted_at);
    }

    public function test_registration_stores_ip_hash_not_plain_address(): void
    {
        $this->post('/register', [
            'name' => 'Hash Test',
            'email' => 'hashtest@example.com',
            'phone_no' => '9800000000',
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
            'terms_condition' => true,
        ]);

        $user = User::where('email', 'hashtest@example.com')->first();
        $info = UserInfo::where('user_id', $user->id)->first();

        $this->assertNotNull($info, 'UserInfo row was created');
        $this->assertNotNull($info->ip_hash, 'ip_hash is populated');
        $this->assertSame(64, strlen($info->ip_hash), 'ip_hash is a 64-char hex string');

        $this->assertFalse(
            array_key_exists('ip_address', $info->getAttributes()),
            'ip_address column must not exist on user_infos'
        );
    }
}
