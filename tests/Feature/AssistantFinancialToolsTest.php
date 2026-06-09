<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Team;
use App\Models\User;
use App\Services\AssistantToolsService;
use Carbon\Carbon;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantFinancialToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_financial_tools_return_projection_data(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->seedMinimalInvoice($team, 2024, 5000, 2000);

        $this->actingAs($user);
        $tools = app(AssistantToolsService::class);
        $tools->setRequestContext($user->id, $team->id, null);

        $projection = $tools->execute('get_financial_projection', ['year' => 2024]);
        $this->assertStringContainsString('5000', str_replace(',', '', $projection));
        $this->assertStringContainsString('Income', $projection);

        $breakdown = $tools->execute('get_financial_category_breakdown', ['year' => 2024, 'operation' => 'buy']);
        $this->assertStringContainsString('Expenses', $breakdown);

        $scenario = $tools->execute('run_financial_growth_scenario', ['year' => 2024, 'multiplier' => 2]);
        $this->assertStringContainsString('×2', $scenario);
        $this->assertStringContainsString('gap', strtolower($scenario));
    }

    private function seedMinimalInvoice(Team $team, int $year, float $income, float $expense): void
    {
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Tool Test Co',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        foreach ([['sell', $income], ['buy', $expense]] as [$operation, $amount])
        {
            $invoice = Invoice::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'enterprise_id' => $enterprise->id,
                'type_id' => 1,
                'operation' => $operation,
                'number' => "TOOL-{$operation}-{$year}",
                'date' => Carbon::create($year, 6, 15)->toDateString(),
                'due_date' => Carbon::create($year, 7, 15)->toDateString(),
                'gross_amount' => $amount,
                'discount' => 0,
                'total_amount' => $amount,
                'balance' => 0,
                'status' => 2,
            ]);

            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'category_id' => null,
                'description' => 'Line',
                'quantity' => 1,
                'unit_price' => $amount,
                'discount' => 0,
            ]);
        }
    }
}
