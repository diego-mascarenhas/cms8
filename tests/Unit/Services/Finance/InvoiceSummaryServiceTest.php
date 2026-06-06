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
            \Database\Seeders\CurrencySeeder::class,
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

    public function test_unpaid_excludes_bonificada_even_when_balance_is_positive(): void
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

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'BON-001',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'gross_amount' => 90,
            'discount' => 0,
            'total_amount' => 90,
            'balance' => 90,
            'status' => 5,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-OPEN',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'gross_amount' => 40,
            'discount' => 0,
            'total_amount' => 40,
            'balance' => 40,
            'status' => 2,
        ]);

        $stats = $this->service->buildIndexStats($team->id);

        $this->assertSame(1, $stats['unpaid']['count']);
        $this->assertSame(['EUR' => 40.0], $stats['unpaid']['totals_by_currency']);
        $this->assertSame(1, $stats['collected']['count']);
        $this->assertSame(['EUR' => 90.0], $stats['collected']['totals_by_currency']);

        $unpaidQuery = Invoice::withoutGlobalScopes()->where('team_id', $team->id);
        $this->service->applySummaryFilter($unpaidQuery, 'unpaid');
        $this->assertSame(['F-OPEN'], $unpaidQuery->pluck('number')->all());
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

    public function test_default_list_filter_hides_collected_invoices(): void
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
            'number' => 'F-OPEN',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
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
            'number' => 'F-PAID',
            'date' => now()->toDateString(),
            'due_date' => null,
            'gross_amount' => 90,
            'discount' => 0,
            'total_amount' => 90,
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

        $defaultQuery = Invoice::withoutGlobalScopes()->where('team_id', $team->id);
        $this->service->applySummaryFilter($defaultQuery, InvoiceSummaryService::DEFAULT_LIST_FILTER);
        $this->assertSame(['F-OPEN'], $defaultQuery->pluck('number')->all());

        $collectedQuery = Invoice::withoutGlobalScopes()->where('team_id', $team->id);
        $this->service->applySummaryFilter($collectedQuery, 'collected');
        $this->assertSame(['F-PAID'], $collectedQuery->pluck('number')->all());

        $creditNotesQuery = Invoice::withoutGlobalScopes()->where('team_id', $team->id);
        $this->service->applySummaryFilter($creditNotesQuery, 'credit_notes');
        $this->assertSame(['NC-001'], $creditNotesQuery->pluck('number')->all());
    }

    public function test_build_index_stats_defaults_to_eur_when_currency_id_is_missing(): void
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
        $this->assertSame('€40,00', $stats['unpaid']['amount_label']);
    }

    public function test_amount_label_converts_multi_currency_totals_to_eur(): void
    {
        Carbon::setTestNow('2026-06-06 12:00:00');

        \App\Models\ExchangeRate::query()->create([
            'base_currency' => 'ARS',
            'target_currency' => 'EUR',
            'rate' => 0.001,
            'date' => now()->toDateString(),
            'fetched_at' => now(),
        ]);

        $arsCurrencyId = (int) \App\Models\Currency::query()->where('code', 'ARS')->value('id');
        $eurCurrencyId = (int) \App\Models\Currency::query()->where('code', 'EUR')->value('id');

        $this->assertNotSame(0, $arsCurrencyId);
        $this->assertNotSame(0, $eurCurrencyId);

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
            'currency_id' => $arsCurrencyId,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-ARS',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'gross_amount' => 10000,
            'discount' => 0,
            'total_amount' => 10000,
            'balance' => 10000,
            'status' => 1,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'currency_id' => $eurCurrencyId,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-EUR',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'gross_amount' => 50,
            'discount' => 0,
            'total_amount' => 50,
            'balance' => 50,
            'status' => 1,
        ]);

        $stats = $this->service->buildIndexStats($team->id);

        $this->assertSame(2, $stats['unpaid']['count']);
        $this->assertSame(['ARS' => 10000.0, 'EUR' => 50.0], $stats['unpaid']['totals_by_currency']);
        $this->assertSame('€60,00', $stats['unpaid']['amount_label']);
    }

    public function test_collected_and_credit_notes_stats_use_rolling_thirty_day_window(): void
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

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-RECENT',
            'date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-OLD',
            'date' => now()->subDays(45)->toDateString(),
            'due_date' => now()->subDays(45)->toDateString(),
            'gross_amount' => 500,
            'discount' => 0,
            'total_amount' => 500,
            'balance' => 0,
            'status' => 2,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'NC-RECENT',
            'date' => now()->subDays(5)->toDateString(),
            'due_date' => null,
            'gross_amount' => 12.34,
            'discount' => 0,
            'total_amount' => 12.34,
            'balance' => 0,
            'status' => 4,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'NC-OLD',
            'date' => now()->subDays(60)->toDateString(),
            'due_date' => null,
            'gross_amount' => 99,
            'discount' => 0,
            'total_amount' => 99,
            'balance' => 0,
            'status' => 4,
        ]);

        $stats = $this->service->buildIndexStats($team->id);

        $this->assertSame(1, $stats['collected']['count']);
        $this->assertSame(['EUR' => 100.0], $stats['collected']['totals_by_currency']);
        $this->assertSame(1, $stats['credit_notes']['count']);
        $this->assertSame(['EUR' => 12.34], $stats['credit_notes']['totals_by_currency']);

        $collectedQuery = Invoice::withoutGlobalScopes()->where('team_id', $team->id);
        $this->service->applySummaryFilter($collectedQuery, 'collected');
        $this->assertSame(['F-RECENT'], $collectedQuery->pluck('number')->all());

        $creditNotesQuery = Invoice::withoutGlobalScopes()->where('team_id', $team->id);
        $this->service->applySummaryFilter($creditNotesQuery, 'credit_notes');
        $this->assertSame(['NC-RECENT'], $creditNotesQuery->pluck('number')->all());
    }

    public function test_resolve_list_filter_maps_legacy_all_to_default(): void
    {
        $this->assertSame('excluding_collected', $this->service->resolveListFilter('all'));
    }

    public function test_resolve_list_filter_falls_back_to_default_for_unknown_values(): void
    {
        $this->assertSame('excluding_collected', $this->service->resolveListFilter(null));
        $this->assertSame('excluding_collected', $this->service->resolveListFilter('invalid'));
    }

    public function test_excluding_collected_filter_excludes_bonificada_with_zero_balance(): void
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
            'number' => '0001-00000102',
            'date' => '2005-03-01',
            'due_date' => '2005-03-01',
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 5,
            'source_provider' => 'manual',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0001-00000541',
            'date' => '2006-11-14',
            'due_date' => '2006-11-14',
            'gross_amount' => 94.5,
            'discount' => 0,
            'total_amount' => 94.5,
            'balance' => 0.5,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        $query = Invoice::withoutGlobalScopes()->where('team_id', $team->id);
        $this->service->applySummaryFilter($query, 'excluding_collected');

        $this->assertSame(['0001-00000541'], $query->pluck('number')->all());
    }
}
