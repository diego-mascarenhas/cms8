<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        if (! Features::enabled(Features::registration()))
        {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_screen_cannot_be_rendered_if_support_is_disabled(): void
    {
        if (Features::enabled(Features::registration()))
        {
            $this->markTestSkipped('Registration support is enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_new_users_can_register(): void
    {
        if (! Features::enabled(Features::registration()))
        {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        config(['registration.mode' => 'free']);

        DB::table('roles')->updateOrInsert(
            ['id' => 2],
            ['name' => 'admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        );

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_new_registration_enables_hunter_plan_modules_on_personal_team(): void
    {
        if (! Features::enabled(Features::registration()))
        {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        config([
            'registration.mode' => 'free',
            'humano_pricing.registration_team_plan_slug' => 'hunter',
        ]);

        foreach (config('humano_pricing.plan_team_modules.hunter', []) as $key)
        {
            Module::query()->firstOrCreate(
                ['key' => $key],
                [
                    'name' => ucfirst(str_replace('-', ' ', $key)),
                    'icon' => 'layout',
                    'description' => 'Test',
                    'is_core' => false,
                    'status' => 1,
                ],
            );
        }

        foreach (['invoices', 'funnel', 'dashboard'] as $key)
        {
            Module::query()->firstOrCreate(
                ['key' => $key],
                [
                    'name' => ucfirst($key),
                    'icon' => 'layout',
                    'description' => 'Test',
                    'is_core' => false,
                    'status' => 1,
                ],
            );
        }

        DB::table('roles')->updateOrInsert(
            ['id' => 2],
            ['name' => 'admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        );

        $this->post('/register', [
            'name' => 'Hunter User',
            'email' => 'hunter-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ])->assertSessionDoesntHaveErrors();

        $user = User::query()->where('email', 'hunter-user@example.com')->firstOrFail();
        $team = $user->ownedTeams()->where('personal_team', true)->firstOrFail();

        $this->assertTrue($team->hasModule('today'));
        $this->assertTrue($team->hasModule('prospecting'));
        $this->assertTrue($team->hasModule('mailer'));
        $this->assertTrue($team->hasModule('templates'));
        $this->assertTrue($team->hasModule('products'));
        $this->assertTrue($team->hasModule('orders'));
        $this->assertTrue($team->hasModule('stores'));
        $this->assertTrue($team->hasModule('landings'));
        $this->assertTrue($team->hasModule('chat'));
        $this->assertFalse($team->hasModule('invoices'));
        $this->assertFalse($team->hasModule('funnel'));
        $this->assertFalse($team->hasModule('dashboard'));
    }
}
