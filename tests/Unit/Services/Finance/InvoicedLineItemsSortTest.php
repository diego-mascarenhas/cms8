<?php

namespace Tests\Unit\Services\Finance;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\Finance\InvoicedLineItemsService;
use Carbon\Carbon;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicedLineItemsSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_items_orders_by_enterprise_name_then_date(): void
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $zeta = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Zeta Corp',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $acme = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $zetaInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $zeta->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'S-Z',
            'date' => Carbon::create(2026, 1, 1)->toDateString(),
            'due_date' => Carbon::create(2026, 1, 31),
            'gross_amount' => 10,
            'discount' => 0,
            'total_amount' => 10,
            'balance' => 0,
            'status' => 2,
        ]);

        $acmeInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $acme->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'S-A',
            'date' => Carbon::create(2026, 2, 1)->toDateString(),
            'due_date' => Carbon::create(2026, 2, 28),
            'gross_amount' => 20,
            'discount' => 0,
            'total_amount' => 20,
            'balance' => 0,
            'status' => 2,
        ]);

        $zetaItem = InvoiceItem::query()->create([
            'invoice_id' => $zetaInvoice->id,
            'description' => 'Zeta line',
            'quantity' => 1,
            'unit_price' => 10,
            'discount' => 0,
        ]);

        $acmeItem = InvoiceItem::query()->create([
            'invoice_id' => $acmeInvoice->id,
            'description' => 'Acme line',
            'quantity' => 1,
            'unit_price' => 20,
            'discount' => 0,
        ]);

        $items = app(InvoicedLineItemsService::class)->queryItems(
            teamId: (int) $team->id,
            from: Carbon::create(2026, 1, 1)->startOfDay(),
            to: Carbon::create(2026, 12, 31)->endOfDay(),
            operation: 'sell',
        );

        $this->assertSame([$acmeItem->id, $zetaItem->id], $items->pluck('id')->all());
    }
}
