<?php

namespace Tests\Unit\Services\Finance;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Team;
use App\Services\Finance\CreditNoteNumberAllocator;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditNoteNumberAllocatorTest extends TestCase
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

    public function test_allocates_correlative_numbers_per_serie(): void
    {
        $team = Team::factory()->create();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 2,
            'operation' => 'sell',
            'number' => 'CN-0005-0001',
            'date' => now()->toDateString(),
            'gross_amount' => 10,
            'total_amount' => 12.1,
            'balance' => 0,
            'status' => 4,
        ]);

        $allocator = app(CreditNoteNumberAllocator::class);

        $this->assertSame('CN-0005-0002', $allocator->next((int) $team->id, '0005'));
        $this->assertSame('0005', $allocator->seriePrefixFromInvoiceNumber('0005-0833'));
        $this->assertSame('0005', $allocator->seriePrefixFromInvoiceNumber('CN-0005-0003'));
        $this->assertTrue($allocator->isHumanoCreditNoteNumber('CN-0005-0003'));
        $this->assertFalse($allocator->isHumanoCreditNoteNumber('0005-0990'));
        $this->assertFalse($allocator->isHumanoCreditNoteNumber('0005-CN-0001'));
    }
}
