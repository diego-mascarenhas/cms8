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
use App\Services\Finance\InvoiceAnalyticsService;
use Carbon\Carbon;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubscriptionInvoiceCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_projection_uses_the_subscription_type_to_pick_a_specific_category(): void
    {
        [$user, $team, $hosting, $vpsValue] = $this->createCatalog();

        $invoice = $this->createSellInvoice($team, 'in_vps_value', 'sub_vps_value', 'vps', 'VPS Value escapegames.com.ar');
        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'category_id' => $hosting['services']->id,
            'description' => '1 × VPS Value (at €49.30 / month)',
            'quantity' => 1,
            'unit_price' => 49.30,
            'discount' => 0,
            'tax_percentage' => 0,
        ]);

        $report = app(InvoiceAnalyticsService::class)->buildYearReport((int) $team->id, 2026);
        $names = array_column($report['income_categories'], 'name');

        $this->assertContains('VPS OpenStack Value', $names);
        $this->assertNotContains('Servicios', $names);

        $this->actingAs($user)
            ->get(route('finance-dashboard.invoiced-lines', [
                'operation' => 'sell',
                'year' => 2026,
                'month' => 0,
            ]))
            ->assertOk()
            ->assertSee('VPS OpenStack Value')
            ->assertSee('data-subscription-type="vps"', false)
            ->assertSee('data-category-group="Hosting"', false);

        $this->assertNotSame($vpsValue->id, $invoice->items()->first()->category_id);
    }

    public function test_vps_type_does_not_assign_a_hosting_plan_category(): void
    {
        [$user, $team, $hosting] = $this->createCatalog();

        $invoice = $this->createSellInvoice($team, 'in_enthusiast', 'sub_enthusiast', 'vps', 'Hosting Enthusiast');
        $item = InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'category_id' => $hosting['services']->id,
            'description' => '1 × Hosting Enthusiast (at €11.99 / month)',
            'quantity' => 1,
            'unit_price' => 11.99,
            'discount' => 0,
            'tax_percentage' => 0,
        ]);

        $report = app(InvoiceAnalyticsService::class)->buildYearReport((int) $team->id, 2026);
        $names = array_column($report['income_categories'], 'name');

        $this->assertNotContains('Hosting OpenStack Enthusiast', $names);
        $this->assertNotContains('VPS OpenStack Value', $names);
        $this->assertSame($hosting['services']->id, $item->fresh()->category_id);
    }

    public function test_importer_prefers_the_subscription_plan_over_a_generic_service_category(): void
    {
        [$user, $team, $hosting, $vpsValue] = $this->createCatalog();
        $invoice = $this->createSellInvoice($team, 'in_import', 'sub_import', 'hosting', 'VPS Value client.test');

        Service::withoutGlobalScopes()->create([
            'enterprise_id' => $invoice->enterprise_id,
            'subscription_id' => StripeSubscription::query()->where('stripe_id', 'sub_import')->value('id'),
            'category_id' => $hosting['services']->id,
            'operation' => 'sell',
            'description' => 'Hosting',
            'data' => [],
            'currency_id' => 1,
            'price' => 49.30,
            'discount' => 0,
            'frequency' => 1,
            'status' => 4,
        ]);

        $sync = InvoiceSync::query()->where('external_id', 'in_import')->first();
        $sync->forceFill([
            'raw_payload' => [
                'lines' => [
                    'data' => [[
                        'description' => '1 × VPS Value (at €49.30 / month)',
                        'quantity' => 1,
                        'amount_excluding_tax' => 4930,
                    ]],
                ],
            ],
        ])->save();

        app(StripeInvoiceItemImporter::class)->syncForInvoice($invoice, $sync);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => '1 × VPS Value (at €49.30 / month)',
            'category_id' => $vpsValue->id,
        ]);
    }

    public function test_a_generic_service_category_does_not_block_a_chosen_line_category(): void
    {
        [$user, $team, $hosting, $vpsValue] = $this->createCatalog();
        $invoice = $this->createSellInvoice($team, 'in_manual', 'sub_manual', 'vps', 'VPS Value');
        $item = InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'category_id' => $hosting['services']->id,
            'description' => '1 × VPS Value (at €49.30 / month)',
            'quantity' => 1,
            'unit_price' => 49.30,
            'discount' => 0,
            'tax_percentage' => 0,
        ]);

        Service::withoutGlobalScopes()->create([
            'enterprise_id' => $invoice->enterprise_id,
            'subscription_id' => StripeSubscription::query()->where('stripe_id', 'sub_manual')->value('id'),
            'category_id' => $hosting['services']->id,
            'operation' => 'sell',
            'description' => 'Hosting',
            'data' => [],
            'currency_id' => 1,
            'price' => 49.30,
            'discount' => 0,
            'frequency' => 1,
            'status' => 4,
        ]);

        $this->actingAs($user)
            ->patchJson(route('invoice-items.category.update', $item), [
                'category_id' => $vpsValue->id,
            ])
            ->assertOk()
            ->assertJsonPath('category_id', $vpsValue->id);

        $this->assertSame($vpsValue->id, $item->fresh()->category_id);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: array{services: Category, hosting: Category, enthusiast: Category}, 3: Category}
     */
    private function createCatalog(): array
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $module = Module::query()->create([
            'name' => 'Services',
            'key' => 'services',
            'icon' => 'ti-server',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $services = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Servicios',
            'parent_id' => null,
        ]);
        Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Alquiler',
            'parent_id' => $services->id,
        ]);

        $hosting = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Hosting',
            'parent_id' => null,
        ]);
        $enthusiast = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Hosting OpenStack Enthusiast',
            'parent_id' => $hosting->id,
        ]);
        $vpsValue = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'VPS OpenStack Value',
            'parent_id' => $hosting->id,
        ]);

        return [$user, $team, [
            'services' => $services,
            'hosting' => $hosting,
            'enthusiast' => $enthusiast,
        ], $vpsValue];
    }

    private function createSellInvoice($team, string $invoiceId, string $subscriptionId, string $type, string $planName): Invoice
    {
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Cliente '.$subscriptionId,
            'type_id' => 1,
            'status_id' => 1,
        ]);

        StripeSubscription::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => $subscriptionId,
            'customer_id' => 'cus_'.$subscriptionId,
            'status' => 'active',
            'plan_name' => $planName,
            'raw_payload' => [
                'metadata' => [
                    'type' => $type,
                ],
            ],
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => strtoupper($invoiceId),
            'date' => Carbon::create(2026, 3, 10)->toDateString(),
            'due_date' => Carbon::create(2026, 3, 30),
            'gross_amount' => 49.30,
            'discount' => 0,
            'total_amount' => 49.30,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => $invoiceId,
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => $invoiceId,
            'stripe_subscription_id' => $subscriptionId,
            'status' => 'paid',
            'currency' => 'eur',
            'total' => 49.30,
            'paid' => true,
        ]);

        return $invoice;
    }
}
