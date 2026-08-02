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
use App\Services\Finance\InvoiceItemCategoryBackfillService;
use Carbon\Carbon;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InvoiceItemPriorCategoryBackfillTest extends TestCase
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

    public function test_backfill_from_prior_invoice_matching_description_and_amount(): void
    {
        [$team, $enterprise, $fineCategory] = $this->seedCatalog();

        $this->createSellInvoiceWithItem(
            teamId: $team->id,
            enterpriseId: $enterprise->id,
            number: 'PRIOR-1',
            date: Carbon::create(2025, 6, 1),
            description: 'Hosting Enthusiast',
            unitPrice: 19.99,
            categoryId: $fineCategory->id,
            sourceReference: 'in_prior_1',
        );

        $target = $this->createSellInvoiceWithItem(
            teamId: $team->id,
            enterpriseId: $enterprise->id,
            number: 'STRIPE-1',
            date: Carbon::create(2026, 3, 1),
            description: '1 × Hosting Enthusiast',
            unitPrice: 19.99,
            categoryId: null,
            sourceReference: 'in_target_1',
            withSubscriptionSync: 'sub_target_1',
        );

        Artisan::call('invoices:categorize-stripe-items', [
            '--team_id' => $team->id,
            '--from-prior-invoices' => true,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'id' => $target['item']->id,
            'category_id' => $fineCategory->id,
        ]);
    }

    public function test_skips_when_amount_differs(): void
    {
        [$team, $enterprise, $fineCategory] = $this->seedCatalog();

        $this->createSellInvoiceWithItem(
            teamId: $team->id,
            enterpriseId: $enterprise->id,
            number: 'PRIOR-2',
            date: Carbon::create(2025, 6, 1),
            description: 'Hosting Enthusiast',
            unitPrice: 19.99,
            categoryId: $fineCategory->id,
            sourceReference: 'in_prior_2',
        );

        $target = $this->createSellInvoiceWithItem(
            teamId: $team->id,
            enterpriseId: $enterprise->id,
            number: 'STRIPE-2',
            date: Carbon::create(2026, 3, 1),
            description: 'Hosting Enthusiast',
            unitPrice: 29.99,
            categoryId: null,
            sourceReference: 'in_target_2',
            withSubscriptionSync: 'sub_target_2',
        );

        Artisan::call('invoices:categorize-stripe-items', [
            '--team_id' => $team->id,
            '--from-prior-invoices' => true,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'id' => $target['item']->id,
            'category_id' => null,
        ]);
    }

    public function test_linked_service_category_wins_over_prior_invoice(): void
    {
        [$team, $enterprise, $fineCategory, $module] = $this->seedCatalog();

        $otherCategory = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'VPS',
            'parent_id' => null,
        ]);

        $this->createSellInvoiceWithItem(
            teamId: $team->id,
            enterpriseId: $enterprise->id,
            number: 'PRIOR-3',
            date: Carbon::create(2025, 6, 1),
            description: 'Hosting Enthusiast',
            unitPrice: 19.99,
            categoryId: $fineCategory->id,
            sourceReference: 'in_prior_3',
        );

        $subscription = StripeSubscription::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => 'sub_conflict_1',
            'customer_id' => 'cus_conflict_1',
            'customer_name' => 'Conflict Client',
            'status' => 'active',
            'plan_name' => 'Hosting Enthusiast',
        ]);

        Service::withoutGlobalScopes()->create([
            'enterprise_id' => $enterprise->id,
            'subscription_id' => $subscription->id,
            'category_id' => $otherCategory->id,
            'operation' => 'sell',
            'description' => 'Hosting Enthusiast',
            'data' => [],
            'currency_id' => 1,
            'price' => 19.99,
            'discount' => 0,
            'frequency' => 1,
            'next_billing' => now()->addMonth(),
            'status' => 4,
        ]);

        $target = $this->createSellInvoiceWithItem(
            teamId: $team->id,
            enterpriseId: $enterprise->id,
            number: 'STRIPE-3',
            date: Carbon::create(2026, 3, 1),
            description: 'Hosting Enthusiast',
            unitPrice: 19.99,
            categoryId: null,
            sourceReference: 'in_target_3',
            withSubscriptionSync: 'sub_conflict_1',
        );

        Artisan::call('invoices:categorize-stripe-items', [
            '--team_id' => $team->id,
            '--from-prior-invoices' => true,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'id' => $target['item']->id,
            'category_id' => $otherCategory->id,
        ]);
    }

    public function test_skips_when_prior_matches_are_ambiguous(): void
    {
        [$team, $enterprise, $fineCategory, $module] = $this->seedCatalog();

        $otherCategory = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Hosting Premium',
            'parent_id' => null,
        ]);

        $this->createSellInvoiceWithItem(
            teamId: $team->id,
            enterpriseId: $enterprise->id,
            number: 'PRIOR-A',
            date: Carbon::create(2025, 6, 1),
            description: 'Hosting Enthusiast',
            unitPrice: 19.99,
            categoryId: $fineCategory->id,
            sourceReference: 'in_prior_a',
        );

        $this->createSellInvoiceWithItem(
            teamId: $team->id,
            enterpriseId: $enterprise->id,
            number: 'PRIOR-B',
            date: Carbon::create(2025, 7, 1),
            description: 'Hosting Enthusiast',
            unitPrice: 19.99,
            categoryId: $otherCategory->id,
            sourceReference: 'in_prior_b',
        );

        $target = $this->createSellInvoiceWithItem(
            teamId: $team->id,
            enterpriseId: $enterprise->id,
            number: 'STRIPE-AMB',
            date: Carbon::create(2026, 3, 1),
            description: 'Hosting Enthusiast',
            unitPrice: 19.99,
            categoryId: null,
            sourceReference: 'in_target_amb',
            withSubscriptionSync: 'sub_amb_1',
        );

        Artisan::call('invoices:categorize-stripe-items', [
            '--team_id' => $team->id,
            '--from-prior-invoices' => true,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'id' => $target['item']->id,
            'category_id' => null,
        ]);
    }

    public function test_prior_match_fills_empty_service_category(): void
    {
        [$team, $enterprise, $fineCategory] = $this->seedCatalog();

        $this->createSellInvoiceWithItem(
            teamId: $team->id,
            enterpriseId: $enterprise->id,
            number: 'PRIOR-4',
            date: Carbon::create(2025, 6, 1),
            description: 'Cloud Starter',
            unitPrice: 9.99,
            categoryId: $fineCategory->id,
            sourceReference: 'in_prior_4',
        );

        $subscription = StripeSubscription::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => 'sub_fill_1',
            'customer_id' => 'cus_fill_1',
            'customer_name' => 'Fill Client',
            'status' => 'active',
            'plan_name' => 'Cloud Starter',
        ]);

        $service = Service::withoutGlobalScopes()->create([
            'enterprise_id' => $enterprise->id,
            'subscription_id' => $subscription->id,
            'category_id' => null,
            'operation' => 'sell',
            'description' => 'Cloud Starter',
            'data' => [],
            'currency_id' => 1,
            'price' => 9.99,
            'discount' => 0,
            'frequency' => 1,
            'next_billing' => now()->addMonth(),
            'status' => 4,
        ]);

        $target = $this->createSellInvoiceWithItem(
            teamId: $team->id,
            enterpriseId: $enterprise->id,
            number: 'STRIPE-4',
            date: Carbon::create(2026, 3, 1),
            description: 'Cloud Starter',
            unitPrice: 9.99,
            categoryId: null,
            sourceReference: 'in_target_4',
            withSubscriptionSync: 'sub_fill_1',
        );

        Artisan::call('invoices:categorize-stripe-items', [
            '--team_id' => $team->id,
            '--from-prior-invoices' => true,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'id' => $target['item']->id,
            'category_id' => $fineCategory->id,
        ]);
        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'category_id' => $fineCategory->id,
        ]);
    }

    public function test_replace_generic_parent_when_prior_matches(): void
    {
        [$team, $enterprise, $fineCategory, $module] = $this->seedCatalog();

        $genericParent = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Servicios',
            'parent_id' => null,
            'status' => 1,
        ]);

        Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Child under Servicios',
            'parent_id' => $genericParent->id,
            'status' => 1,
        ]);

        $this->createSellInvoiceWithItem(
            teamId: $team->id,
            enterpriseId: $enterprise->id,
            number: 'PRIOR-5',
            date: Carbon::create(2025, 6, 1),
            description: 'Hosting Enthusiast',
            unitPrice: 19.99,
            categoryId: $fineCategory->id,
            sourceReference: 'in_prior_5',
        );

        $target = $this->createSellInvoiceWithItem(
            teamId: $team->id,
            enterpriseId: $enterprise->id,
            number: 'STRIPE-5',
            date: Carbon::create(2026, 3, 1),
            description: 'Hosting Enthusiast',
            unitPrice: 19.99,
            categoryId: $genericParent->id,
            sourceReference: 'in_target_5',
            withSubscriptionSync: 'sub_target_5',
        );

        Artisan::call('invoices:categorize-stripe-items', [
            '--team_id' => $team->id,
            '--from-prior-invoices' => true,
            '--replace-generic-parents' => true,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'id' => $target['item']->id,
            'category_id' => $fineCategory->id,
        ]);
    }

    public function test_normalize_description_strips_stripe_quantity_prefix(): void
    {
        $service = app(InvoiceItemCategoryBackfillService::class);

        $this->assertSame(
            'hosting enthusiast',
            $service->normalizeDescription('1 × Hosting Enthusiast'),
        );
    }

    /**
     * @return array{0: \App\Models\Team, 1: Enterprise, 2: Category, 3: Module}
     */
    private function seedCatalog(): array
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

        $fineCategory = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Hosting Enthusiast',
            'parent_id' => null,
            'status' => 1,
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme Hosting',
            'code' => 'cus_backfill_1',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        return [$team, $enterprise, $fineCategory, $module];
    }

    /**
     * @return array{invoice: Invoice, item: InvoiceItem}
     */
    private function createSellInvoiceWithItem(
        int $teamId,
        int $enterpriseId,
        string $number,
        Carbon $date,
        string $description,
        float $unitPrice,
        ?int $categoryId,
        string $sourceReference,
        ?string $withSubscriptionSync = null,
    ): array {
        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $enterpriseId,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => $number,
            'date' => $date->toDateString(),
            'gross_amount' => $unitPrice,
            'total_amount' => $unitPrice,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => $sourceReference,
        ]);

        $item = InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'category_id' => $categoryId,
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);

        InvoiceSync::query()->create([
            'team_id' => $teamId,
            'provider' => 'stripe',
            'external_id' => $sourceReference,
            'stripe_subscription_id' => $withSubscriptionSync,
            'status' => 'paid',
            'currency' => 'eur',
            'raw_payload' => [],
        ]);

        return ['invoice' => $invoice, 'item' => $item];
    }
}
