<?php

namespace Tests\Unit\Services;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\User;
use App\Services\ExpenseDuplicateDocumentService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseDuplicateDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExpenseDuplicateDocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);

        $this->service = app(ExpenseDuplicateDocumentService::class);
    }

    public function test_find_duplicate_matches_buy_invoice_for_same_supplier_case_insensitively(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = (int) $user->ownedTeams()->first()->id;

        $supplier = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'type_id' => 2,
            'status_id' => 1,
            'name' => 'Proveedor SL',
            'email' => 'proveedor@test.test',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $supplier->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'ABC-123',
            'date' => '2026-06-01',
            'due_date' => '2026-06-01',
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        $duplicate = $this->service->findDuplicate($teamId, $supplier->id, ' abc-123 ');

        $this->assertInstanceOf(Invoice::class, $duplicate);
        $this->assertSame('ABC-123', $duplicate->number);
    }

    public function test_find_duplicate_ignores_sell_invoices(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = (int) $user->ownedTeams()->first()->id;

        $supplier = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'type_id' => 2,
            'status_id' => 1,
            'name' => 'Proveedor SL',
            'email' => 'proveedor@test.test',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $supplier->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'VENTA-001',
            'date' => '2026-06-01',
            'due_date' => '2026-06-01',
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        $this->assertNull($this->service->findDuplicate($teamId, $supplier->id, 'VENTA-001'));
    }
}
