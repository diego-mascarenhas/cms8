<?php

namespace Tests\Unit\Services\Finance;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Finance\InvoiceSummaryService;
use Carbon\Carbon;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceSummaryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);
        $this->service = app(InvoiceSummaryService::class);
    }

    public function test_build_index_stats_groups_unpaid_collected_credit_notes_and_overdue(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-001',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 1,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-002',
            'date' => now()->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'gross_amount' => 50,
            'discount' => 0,
            'total_amount' => 50,
            'balance' => 50,
            'status' => 2,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-003',
            'date' => now()->toDateString(),
            'due_date' => now()->subDays(1)->toDateString(),
            'gross_amount' => 200,
            'discount' => 0,
            'total_amount' => 200,
            'balance' => 0,
            'status' => 2,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'NC-001',
            'date' => now()->toDateString(),
            'due_date' => null,
            'gross_amount' => 30,
            'discount' => 0,
            'total_amount' => 30,
            'balance' => 0,
            'status' => 4,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-004',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'gross_amount' => 80,
            'discount' => 0,
            'total_amount' => 80,
            'balance' => 80,
            'status' => 3,
        ]);

        $stats = $this->service->buildIndexStats($team->id);

        $this->assertSame(2, $stats['unpaid']['count']);
        $this->assertSame(['EUR' => 150.0], $stats['unpaid']['totals_by_currency']);

        $this->assertSame(1, $stats['credit_notes']['count']);
        $this->assertSame(['EUR' => 30.0], $stats['credit_notes']['totals_by_currency']);

        $this->assertSame(1, $stats['collected']['count']);
        $this->assertSame(['EUR' => 200.0], $stats['collected']['totals_by_currency']);

        $this->assertSame(1, $stats['overdue']['count']);
        $this->assertSame(['EUR' => 50.0], $stats['overdue']['totals_by_currency']);
    }

    public function test_apply_summary_filter_limits_query_results(): void
    {
        Carbon::setTestNow('2026-06-06 12:00:00');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $unpaidId = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-UNPAID',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 1,
        ])->id;

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'NC-002',
            'date' => now()->toDateString(),
            'due_date' => null,
            'gross_amount' => 30,
            'discount' => 0,
            'total_amount' => 30,
            'balance' => 0,
            'status' => 4,
        ]);

        $unpaidQuery = Invoice::withoutGlobalScopes()->where('team_id', $team->id);
        $this->service->applySummaryFilter($unpaidQuery, 'unpaid');
        $this->assertSame([$unpaidId], $unpaidQuery->pluck('id')->all());

        $creditNotesQuery = Invoice::withoutGlobalScopes()->where('team_id', $team->id);
        $this->service->applySummaryFilter($creditNotesQuery, 'credit_notes');
        $this->assertSame(['NC-002'], $creditNotesQuery->pluck('number')->all());
    }

    public function test_build_index_stats_defaults_to_eur_when_currency_column_is_missing(): void
    {
        Carbon::setTestNow('2026-06-06 12:00:00');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Global Corp',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-010',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'gross_amount' => 40,
            'discount' => 0,
            'total_amount' => 40,
            'balance' => 40,
            'status' => 1,
        ]);

        $stats = $this->service->buildIndexStats($team->id);

        $this->assertSame(1, $stats['unpaid']['count']);
        $this->assertSame(['EUR' => 40.0], $stats['unpaid']['totals_by_currency']);
        $this->assertSame('€40.00', $stats['unpaid']['amount_label']);
    }
}
