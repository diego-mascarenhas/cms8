<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSync;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\StripeInvoiceItemImporter;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeInvoiceItemImporterTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Enterprise $enterprise;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $this->team = $user->ownedTeams()->first();

        $this->enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $this->team->id,
            'name' => 'Stripe Customer SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);
    }

    public function test_sync_maps_stripe_lines_with_inferred_tax_percentage(): void
    {
        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $this->team->id,
            'enterprise_id' => $this->enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'STR-TEST-1',
            'date' => now()->toDateString(),
            'gross_amount' => 11.99,
            'total_amount' => 14.51,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_test_1',
        ]);

        $sync = InvoiceSync::query()->create([
            'team_id' => $this->team->id,
            'provider' => 'stripe',
            'external_id' => 'in_test_1',
            'status' => 'paid',
            'currency' => 'eur',
            'subtotal' => 11.99,
            'tax' => 2.52,
            'total' => 14.51,
            'paid' => true,
            'raw_payload' => [
                'lines' => [
                    'data' => [
                        [
                            'description' => '1 × Hosting Enthusiast',
                            'quantity' => 1,
                            'amount_excluding_tax' => 1199,
                            'unit_amount_excluding_tax' => '1199',
                            'discount_amounts' => [],
                            'tax_rates' => [],
                            'tax_amounts' => [
                                [
                                    'amount' => 252,
                                    'taxable_amount' => 1199,
                                ],
                            ],
                            'taxes' => [
                                [
                                    'amount' => 252,
                                    'taxable_amount' => 1199,
                                ],
                            ],
                        ],
                        [
                            'description' => 'Zero tax add-on',
                            'quantity' => 1,
                            'amount_excluding_tax' => 500,
                            'unit_amount_excluding_tax' => '500',
                            'discount_amounts' => [],
                            'tax_rates' => [],
                            'tax_amounts' => [],
                            'taxes' => [],
                        ],
                    ],
                ],
            ],
        ]);

        app(StripeInvoiceItemImporter::class)->syncForInvoice($invoice, $sync);

        $this->assertSame(2, $invoice->items()->count());
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => '1 × Hosting Enthusiast',
            'quantity' => 1,
            'unit_price' => 11.99,
            'tax_percentage' => 21.02,
        ]);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'Zero tax add-on',
            'unit_price' => 5.00,
            'tax_percentage' => 0,
        ]);
    }

    public function test_sync_replaces_existing_items(): void
    {
        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $this->team->id,
            'enterprise_id' => $this->enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'STR-TEST-2',
            'date' => now()->toDateString(),
            'gross_amount' => 10,
            'total_amount' => 12.1,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_test_2',
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Old line',
            'quantity' => 1,
            'unit_price' => 10,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);

        $sync = InvoiceSync::query()->create([
            'team_id' => $this->team->id,
            'provider' => 'stripe',
            'external_id' => 'in_test_2',
            'status' => 'paid',
            'currency' => 'eur',
            'raw_payload' => [
                'lines' => [
                    'data' => [
                        [
                            'description' => 'New line',
                            'quantity' => 1,
                            'amount_excluding_tax' => 1000,
                            'tax_rates' => [
                                ['percentage' => 10],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        app(StripeInvoiceItemImporter::class)->syncForInvoice($invoice, $sync);

        $this->assertSame(1, $invoice->items()->count());
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'New line',
            'tax_percentage' => 10,
        ]);
        $this->assertDatabaseMissing('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'Old line',
        ]);
    }
}
