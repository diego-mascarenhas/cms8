<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/user/confirm-password');

        $response->assertStatus(200);
    }

    public function test_password_can_be_confirmed(): void
    {
        // Without a team the web middleware bounces every request to the no-team page, which would
        // make these assertions pass regardless of the password.
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->post('/user/confirm-password', [
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertNotNull(session('auth.password_confirmed_at'));
    }

    public function test_password_is_not_confirmed_with_invalid_password(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->post('/user/confirm-password', [
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors();
    }
}
