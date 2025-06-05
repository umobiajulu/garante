<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Traits\TestHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthTest extends TestCase
{
    use TestHelpers, RefreshDatabase;

    public function test_user_can_register()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'phone_number' => '1234567890'
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'user' => ['id', 'name', 'email', 'phone_number'],
                    'access_token'
                ]);
    }

    public function test_user_can_login()
    {
        $user = $this->createUser();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password'
        ]);

        $response->assertOk()
                ->assertJsonStructure([
                    'message',
                    'user',
                    'access_token'
                ]);
    }

    public function test_user_can_logout()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->postJson('/api/logout');

        $response->assertOk()
                ->assertJsonStructure(['message']);
    }

    public function test_user_can_refresh_token()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->postJson('/api/refresh');

        $response->assertOk()
                ->assertJsonStructure([
                    'message',
                    'user',
                    'access_token'
                ]);
    }

    public function test_user_can_get_profile()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->getJson('/api/me');

        $response->assertOk()
                ->assertJsonStructure([
                    'message',
                    'user'
                ]);
    }
} 