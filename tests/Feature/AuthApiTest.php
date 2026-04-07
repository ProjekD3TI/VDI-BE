<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
// use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;
    public function test_user_can_login_with_correct_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('test123123')
        ]);
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'test123123'
        ]);
        $response->assertStatus(200)->assertJsonStructure(['access_token', 'token_type']);
    }
}
