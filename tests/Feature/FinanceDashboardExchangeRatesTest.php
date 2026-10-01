<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinanceDashboardExchangeRatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_links_to_exchange_rates_and_shows_the_last_update(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();
        $this->actingAs($user);

        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'ARS',
            'rate' => 1500,
            'date' => '2026-07-24',
            'fetched_at' => '2026-10-01 02:00:00',
        ]);
        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => 0.86,
            'date' => '2026-07-24',
            'fetched_at' => '2026-10-01 02:00:00',
        ]);

        $this->get(route('finance-dashboard.index'))
            ->assertOk()
            ->assertSee(route('finance-dashboard.exchange-rates'), false)
            ->assertSee('01/10/2026 04:00', false);

        $this->get(route('finance-dashboard.exchange-rates', ['year' => 2026]))
            ->assertOk()
            ->assertSee('24/07/2026', false)
            ->assertSee('1.500,0000', false)
            ->assertSee('0,860000', false)
            ->assertSee('1.744,1860', false);
    }
}
