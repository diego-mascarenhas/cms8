<?php

namespace Tests\Feature;

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

    /**
     * The widget is unreachable rather than hidden: billing itself is gated on admin/root
     * ({@see \App\Models\User::canAccessBilling}), so a user without either role never gets the page.
     */
    public function test_billing_index_is_denied_for_non_admin_roles(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();

        $this->actingAs($user)
            ->get(route('billing.index'))
            ->assertDeniedForBrowser();
    }

    public function test_billing_index_hides_affiliates_section_when_team_was_referred(): void
    {
        $user = $this->billingAdmin();
        $team = $user->ownedTeams()->first();
        $team->forceFill(['referred_by' => 'cus_some_referrer'])->save();

        $response = $this->actingAs($user)->get(route('billing.index'));

        $response->assertOk();
        $response->assertDontSee('Invitaciones enviadas', false);
        $response->assertDontSee('Como referidor', false);
    }

    public function test_billing_index_shows_affiliates_section_without_affiliates_module(): void
    {
        $user = $this->billingAdmin();

        $response = $this->actingAs($user)->get(route('billing.index'));

        $response->assertOk();
        $response->assertSee('Invitaciones enviadas', false);
    }

    private function billingAdmin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();

        return $user;
    }
}
