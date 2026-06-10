<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BillingIndexApiUsageWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_index_shows_api_usage_widget_for_root_user(): void
    {
        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('root');
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();

        $response = $this->actingAs($user)->get(route('billing.index'));

        $response->assertOk();
        $response->assertSee('Uso de API & Ahorro', false);
        $response->assertSee('Llamadas', false);
    }

    public function test_billing_index_shows_api_usage_widget_for_admin_user(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();

        $response = $this->actingAs($user)->get(route('billing.index'));

        $response->assertOk();
        $response->assertSee('Uso de API & Ahorro', false);
    }

    public function test_billing_index_hides_api_usage_widget_for_non_admin_roles(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();

        $response = $this->actingAs($user)->get(route('billing.index'));

        $response->assertOk();
        $response->assertDontSee('Uso de API & Ahorro', false);
    }

    public function test_billing_index_hides_affiliates_section_when_module_inactive(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        Module::query()->firstOrCreate(
            ['key' => 'affiliates'],
            [
                'name' => 'Affiliates',
                'icon' => 'affiliate',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $response = $this->actingAs($user)->get(route('billing.index'));

        $response->assertOk();
        $response->assertDontSee('Invitaciones enviadas', false);
        $response->assertDontSee('Como referidor', false);
    }

    public function test_billing_index_shows_affiliates_section_when_module_active(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        Module::query()->firstOrCreate(
            ['key' => 'affiliates'],
            [
                'name' => 'Affiliates',
                'icon' => 'affiliate',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );
        $team->enableModule('affiliates');

        $response = $this->actingAs($user)->get(route('billing.index'));

        $response->assertOk();
        $response->assertSee('Invitaciones enviadas', false);
    }
}
