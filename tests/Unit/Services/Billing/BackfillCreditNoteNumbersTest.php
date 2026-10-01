<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Team;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillCreditNoteNumbersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
        ]);
    }

    public function test_from_july_restarts_the_series_and_leaves_earlier_notes(): void
    {
        $team = Team::factory()->create();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente',
        ]);

        $beforeQuarter = $this->creditNote($team->id, $enterprise->id, '0005-0100-CN-01', '2026-06-15', 'cn_before');
        $july = $this->creditNote($team->id, $enterprise->id, '0005-0396-CN-01', '2026-07-24', 'cn_july');
        $august = $this->creditNote($team->id, $enterprise->id, 'CN-0005-0001', '2026-08-12', 'cn_august');

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'cn_july',
            'number' => '0005-0396-CN-01',
            'status' => 'issued',
            'currency' => 'eur',
            'total' => 121,
            'paid' => true,
            'last_synced_at' => now(),
        ]);
        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'cn_august',
            'number' => '0005-0880-CN-01',
            'status' => 'issued',
            'currency' => 'eur',
            'total' => 121,
            'paid' => true,
            'last_synced_at' => now(),
        ]);

        $this->artisan('invoices:backfill-credit-note-numbers', [
            '--team_id' => $team->id,
            '--from' => '2026-07-01',
            '--to' => '2026-10-01',
        ])->assertSuccessful();

        $this->assertSame('0005-0100-CN-01', $beforeQuarter->fresh()->number);
        $this->assertSame('CN-0005-0001', $july->fresh()->number);
        $this->assertSame('CN-0005-0002', $august->fresh()->number);
        $this->assertSame('0005-0396-CN-01', InvoiceSync::query()->where('external_id', 'cn_july')->value('number'));
        $this->assertSame('0005-0880-CN-01', InvoiceSync::query()->where('external_id', 'cn_august')->value('number'));
    }

    public function test_dry_run_does_not_rewrite_numbers(): void
    {
        $team = Team::factory()->create();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente',
        ]);

        $july = $this->creditNote($team->id, $enterprise->id, '0005-0396-CN-01', '2026-07-24', 'cn_dry');

        $this->artisan('invoices:backfill-credit-note-numbers', [
            '--team_id' => $team->id,
            '--from' => '2026-07-01',
            '--to' => '2026-10-01',
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame('0005-0396-CN-01', $july->fresh()->number);
    }

    private function creditNote(int $teamId, int $enterpriseId, string $number, string $date, string $externalId): Invoice
    {
        return Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $enterpriseId,
            'type_id' => 2,
            'operation' => 'sell',
            'number' => $number,
            'date' => $date,
            'gross_amount' => 100,
            'total_amount' => 121,
            'balance' => 0,
            'status' => 4,
            'source_provider' => 'stripe',
            'source_reference_id' => $externalId,
        ]);
    }
}
