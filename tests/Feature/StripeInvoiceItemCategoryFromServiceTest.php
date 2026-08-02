<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSync;
use App\Models\Module;
use App\Models\Service;
use App\Models\StripeSubscription;
use App\Models\User;
use App\Services\Billing\StripeInvoiceItemImporter;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StripeInvoiceItemCategoryFromServiceTest extends TestCase
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

    public function test_importer_copies_category_from_linked_service(): void
    {
        [$invoice, $sync, $category] = $this->createStripeInvoiceWithLinkedService();

        app(StripeInvoiceItemImporter::class)->syncForInvoice($invoice, $sync);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => '1 × Hosting Enthusiast',
            'category_id' => $category->id,
        ]);
    }

    public function test_backfill_command_assigns_category_from_service(): void
    {
        [$invoice, $sync, $category] = $this->createStripeInvoiceWithLinkedService();

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'category_id' => null,
            'description' => 'Legacy uncategorized line',
            'quantity' => 1,
            'unit_price' => 11.99,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);

        Artisan::call('invoices:categorize-stripe-items', [
            '--team_id' => $invoice->team_id,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'Legacy uncategorized line',
            'category_id' => $category->id,
        ]);
    }

    /**
     * @return array{0: Invoice, 1: InvoiceSync, 2: Category}
     */
    private function createStripeInvoiceWithLinkedService(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $module = Module::query()->firstOrCreate(
            ['key' => 'services'],
            [
                'name' => 'Services',
                'icon' => 'ti-server',
                'description' => null,
                'is_core' => false,
                'status' => 1,
            ],
        );

        $category = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Hosting',
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Stripe Customer SL',
            'code' => 'cus_cat_1',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $subscription = StripeSubscription::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => 'sub_cat_1',
            'customer_id' => 'cus_cat_1',
            'customer_name' => 'Stripe Customer SL',
            'status' => 'active',
            'plan_name' => 'Hosting Enthusiast',
        ]);

        Service::withoutGlobalScopes()->create([
            'enterprise_id' => $enterprise->id,
            'subscription_id' => $subscription->id,
            'category_id' => $category->id,
            'operation' => 'sell',
            'description' => 'Hosting Enthusiast',
            'data' => [],
            'currency_id' => 1,
            'price' => 11.99,
            'discount' => 0,
            'frequency' => 1,
            'next_billing' => now()->addMonth(),
            'status' => 4,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'STR-CAT-1',
            'date' => now()->toDateString(),
            'gross_amount' => 11.99,
            'total_amount' => 14.51,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_cat_1',
        ]);

        $sync = InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_cat_1',
            'stripe_subscription_id' => 'sub_cat_1',
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
                    ],
                ],
            ],
        ]);

        return [$invoice, $sync, $category];
    }
}
