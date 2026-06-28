<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\Finance\PaymentReportingCurrencyService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamSettingsFinanceGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_can_save_finance_reporting_currency(): void
    {
        $this->seed([CurrencySeeder::class]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->put(route('team-settings.update', $team), [
                'finance' => [
                    'finance_reporting_currency' => 'EUR',
                ],
            ])
            ->assertRedirect();

        $this->assertSame(
            'EUR',
            strtoupper((string) Team::find($team->id)?->getSetting(PaymentReportingCurrencyService::SETTING_KEY)),
        );
    }
}
