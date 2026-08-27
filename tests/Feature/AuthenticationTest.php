<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        if (Route::has('pricing') && config('custom.custom.showRegister'))
        {
            $response->assertSee(route('pricing'), false);
        }
    }

    public function test_auth_minimal_layout_hides_left_cover_on_login(): void
    {
        config(['custom.custom.authMinimalLayout' => true]);

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('col-lg-7', false);
    }

    public function test_auth_default_layout_shows_left_cover_on_login(): void
    {
        config(['custom.custom.authMinimalLayout' => false]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('col-lg-7', false);
    }

    public function test_auth_logo_is_hidden_by_default_on_related_screens(): void
    {
        config(['custom.custom.showAuthLogo' => false]);

        $this->get('/login')->assertOk()->assertDontSee('logo-light.svg', false);
        $this->get('/forgot-password')->assertOk()->assertDontSee('logo-light.svg', false);

        if (Features::enabled(Features::registration()))
        {
            $this->get('/register')->assertOk()->assertDontSee('logo-light.svg', false);
        }

        $user = User::factory()->withPersonalTeam()->unverified()->create();
        if (Features::enabled(Features::emailVerification()))
        {
            $this->actingAs($user)->get('/email/verify')->assertOk()->assertDontSee('logo-light.svg', false);
        }
        $this->actingAs($user)->get('/user/confirm-password')->assertOk()->assertDontSee('logo-light.svg', false);
    }

    public function test_auth_logo_can_be_shown_from_custom_config(): void
    {
        config(['custom.custom.showAuthLogo' => true]);

        $this->get('/login')->assertOk()->assertSee('logo-light.svg', false);

        $user = User::factory()->withPersonalTeam()->unverified()->create();
        if (Features::enabled(Features::emailVerification()))
        {
            $this->actingAs($user)->get('/email/verify')->assertOk()->assertSee('logo-light.svg', false);
        }
        $this->actingAs($user)->get('/user/confirm-password')->assertOk()->assertSee('logo-light.svg', false);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }
}
