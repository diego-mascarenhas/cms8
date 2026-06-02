<?php

namespace Tests\Unit\Services\Finance;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\Finance\InvoiceAnalyticsService;
use Carbon\Carbon;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceAnalyticsGrowthScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_growth_scenario_calculates_gap(): void
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $year = (int) Carbon::now()->year;

        foreach ([['sell', 12000], ['buy', 7000]] as [$op, $amount])
        {
            $invoice = Invoice::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'enterprise_id' => $enterprise->id,
                'type_id' => 1,
                'operation' => $op,
                'number' => "GS-{$op}",
                'date' => Carbon::create($year, 3, 1)->toDateString(),
                'due_date' => Carbon::create($year, 3, 31)->toDateString(),
                'gross_amount' => $amount,
                'discount' => 0,
                'total_amount' => $amount,
                'balance' => 0,
                'status' => 2,
            ]);
            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'description' => 'x',
                'quantity' => 1,
                'unit_price' => $amount,
                'discount' => 0,
            ]);
        }

        $scenario = app(InvoiceAnalyticsService::class)->buildGrowthScenario($team->id, $year, 2.0);

        $this->assertSame(2.0, $scenario['multiplier']);
        $this->assertGreaterThan(0, $scenario['avg_monthly_profit']);
        $this->assertEqualsWithDelta(
            $scenario['avg_monthly_profit'] * 2,
            $scenario['target_monthly_profit'],
            0.01,
        );
        $this->assertGreaterThan(0, $scenario['monthly_gap']);
    }
}
