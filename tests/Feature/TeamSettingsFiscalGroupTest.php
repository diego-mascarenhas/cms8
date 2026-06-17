<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamSettingsFiscalGroupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
        ]);
    }

    private function userWithTeam(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user->fresh();
    }

    public function test_fiscal_settings_page_shows_global_platform_fields(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->get(route('team-settings.edit', ['team' => $team, 'group' => 'fiscal']))
            ->assertOk()
            ->assertSee(__('Exportación fiscal'), false)
            ->assertSee('Plataforma fiscal', false)
            ->assertSee('País fiscal del equipo', false)
            ->assertDontSee('name="cuentica[', false);
    }

    public function test_cuentica_settings_page_shows_credentials_only(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->get(route('team-settings.edit', ['team' => $team, 'group' => 'cuentica']))
            ->assertOk()
            ->assertSee(__('Cuéntica'), false)
            ->assertSee('name="cuentica[cuentica_api_token]"', false)
            ->assertDontSee('name="cuentica[fiscal_platform]"', false);
    }

    public function test_can_save_fiscal_platform_and_country(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->put(route('team-settings.update', $team), [
                'fiscal' => [
                    'fiscal_platform' => 'cuentica',
                    'fiscal_country' => 'ES',
                ],
            ])
            ->assertRedirect();

        $this->assertSame('cuentica', $team->fresh()->getSetting('fiscal_platform'));
        $this->assertSame('ES', $team->fresh()->getSetting('fiscal_country'));
    }

    public function test_settings_index_lists_fiscal_and_cuentica_cards(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->get(route('team-settings.index', $team))
            ->assertOk()
            ->assertSee(route('team-settings.edit', ['team' => $team, 'group' => 'fiscal']), false)
            ->assertSee(route('team-settings.edit', ['team' => $team, 'group' => 'cuentica']), false);
    }

    public function test_settings_index_shows_spanish_copy_when_locale_is_spanish(): void
    {
        app()->setLocale('es');

        $user = $this->userWithTeam();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->get(route('team-settings.index', $team))
            ->assertOk()
            ->assertSee(__('Team Settings'), false)
            ->assertSee(__('Configure your team settings and preferences'), false)
            ->assertSee(__('Stripe Integration'), false)
            ->assertSee(__('Password security'), false)
            ->assertSee(__('Google People & Calendar'), false)
            ->assertDontSee('Team Settings', false)
            ->assertDontSee('Configure your team settings and preferences', false);
    }
}
