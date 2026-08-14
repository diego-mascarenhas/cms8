<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\EnterpriseBillingAddress;
use App\Models\EnterpriseTaxStatusType;
use App\Models\Invoice;
use App\Models\InvoiceType;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use App\Services\Finance\EnterpriseVatRateResolver;
use App\Services\Finance\ProjectDepositInvoiceService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTaxStatusTypeSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Stripe\StripeClient;
use Tests\TestCase;

class ProjectDepositInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            EnterpriseTaxStatusTypeSeeder::class,
            ProjectStatusSeeder::class,
        ]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Currency::query()->firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'symbol' => '€', 'status' => true]);
        InvoiceType::query()->firstOrCreate(['name' => 'Standard']);
    }

    #[Test]
    public function vat_resolver_applies_default_iva_for_spanish_domestic_client(): void
    {
        $enterprise = $this->makeEnterpriseWithBilling([
            'identification_number' => 'B12345674',
            'country' => 'ES',
            'tax_status' => 'VAT registered',
        ]);

        $result = app(EnterpriseVatRateResolver::class)->resolve($enterprise);

        $this->assertTrue($result['applies']);
        $this->assertSame(21.0, $result['percent']);
    }

    #[Test]
    public function vat_resolver_exempts_tax_exempt_status(): void
    {
        $enterprise = $this->makeEnterpriseWithBilling([
            'identification_number' => 'B12345674',
            'country' => 'ES',
            'tax_status' => 'Exempt from tax',
        ]);

        $result = app(EnterpriseVatRateResolver::class)->resolve($enterprise);

        $this->assertFalse($result['applies']);
        $this->assertSame(0.0, $result['percent']);
    }

    #[Test]
    public function approved_project_show_offers_deposit_invoice_banner(): void
    {
        [$user, $project] = $this->createApprovedProject();

        $this->actingAs($user)
            ->get(route('project.show', $project->id))
            ->assertOk()
            ->assertSee(__('Internal Name for Collaborators'), false)
            ->assertSee('Dashboard Innovación — 4 secciones', false)
            ->assertSee(__('Invoice deposit'), false)
            ->assertSee(__('Approved budget — invoice the 30% deposit'), false)
            ->assertSee('deposit-invoice-description', false)
            ->assertSee(__('Invoice description'), false)
            ->assertDontSee('projectDepositInvoiceModal', false);
    }

    #[Test]
    public function deposit_invoice_is_created_via_stripe_and_stays_approved_until_paid(): void
    {
        [$user, $project] = $this->createApprovedProject();
        $project->client->setStripeCustomerId('cus_test_deposit')->save();
        $user->currentTeam->setSetting('stripe_secret', 'sk_test_fake');

        $invoiceItems = Mockery::mock();
        $invoiceItems->shouldReceive('all')->once()->andReturn((object) ['data' => []]);
        $invoiceItems->shouldReceive('create')->once()->andReturn((object) ['id' => 'ii_1']);

        $taxRates = Mockery::mock();
        $taxRates->shouldReceive('all')->once()->andReturn((object) ['data' => []]);
        $taxRates->shouldReceive('create')->once()->andReturn((object) ['id' => 'txr_21']);

        $customers = Mockery::mock();
        $customers->shouldReceive('retrieve')->once()->andReturn((object) [
            'invoice_settings' => (object) ['default_payment_method' => null],
            'default_source' => null,
        ]);

        $paymentMethods = Mockery::mock();
        $paymentMethods->shouldReceive('all')->once()->andReturn((object) ['data' => []]);

        $draftInvoice = (object) [
            'id' => 'inv_test_deposit_001',
            'number' => null,
            'status' => 'draft',
            'total' => 0,
            'amount_paid' => 0,
            'amount_due' => 0,
            'hosted_invoice_url' => null,
        ];
        $stripeInvoice = (object) [
            'id' => 'inv_test_deposit_001',
            'number' => 'INV-DEP-001',
            'status' => 'open',
            'total' => 199650,
            'amount_paid' => 0,
            'amount_due' => 199650,
            'hosted_invoice_url' => 'https://invoice.stripe.com/i/test',
        ];

        $invoices = Mockery::mock();
        $invoices->shouldReceive('all')->once()->andReturn((object) ['data' => []]);
        $invoices->shouldReceive('create')->once()->withArgs(function (array $payload): bool
        {
            return ($payload['collection_method'] ?? null) === 'send_invoice'
                && ($payload['days_until_due'] ?? null) === 15
                && ($payload['pending_invoice_items_behavior'] ?? null) === 'exclude';
        })->andReturn($draftInvoice);
        $invoices->shouldReceive('finalizeInvoice')->once()->with('inv_test_deposit_001')->andReturn($stripeInvoice);
        $invoices->shouldReceive('pay')->never();

        $client = Mockery::mock(StripeClient::class);
        $client->invoiceItems = $invoiceItems;
        $client->taxRates = $taxRates;
        $client->customers = $customers;
        $client->paymentMethods = $paymentMethods;
        $client->invoices = $invoices;

        $this->bindFakeDepositService($client);

        $this->actingAs($user)
            ->post(route('project.invoice-deposit', $project->id), [
                'description' => 'Adelanto 30% Dashboard Innovación',
            ])
            ->assertRedirect(route('project.show', $project->id))
            ->assertSessionHas('success')
            ->assertSessionHas('deposit_invoice_url', 'https://invoice.stripe.com/i/test');

        $invoice = Invoice::withoutGlobalScopes()
            ->where('source_reference_id', 'inv_test_deposit_001')
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame('stripe', $invoice->source_provider);
        $this->assertGreaterThan(0, (float) $invoice->balance);
        $this->assertSame('Adelanto 30% Dashboard Innovación', $invoice->items()->first()?->description);
        $this->assertSame(21.0, (float) $invoice->items()->first()?->tax_percentage);

        $fresh = $project->fresh();
        $this->assertSame(ProjectStatus::STATUS_APPROVED, (int) $fresh->status_id);
        $this->assertSame('inv_test_deposit_001', data_get($fresh->data, 'deposit_invoice.stripe_invoice_id'));
        $this->assertFalse((bool) data_get($fresh->data, 'deposit_invoice.charged'));
    }

    #[Test]
    public function deposit_invoice_charges_automatically_when_customer_has_payment_method(): void
    {
        [$user, $project] = $this->createApprovedProject();
        $project->client->setStripeCustomerId('cus_test_deposit_pm')->save();
        $user->currentTeam->setSetting('stripe_secret', 'sk_test_fake');

        $openInvoice = (object) [
            'id' => 'inv_test_deposit_002',
            'number' => 'INV-DEP-002',
            'status' => 'open',
            'total' => 199650,
            'amount_paid' => 0,
            'amount_due' => 199650,
            'hosted_invoice_url' => 'https://invoice.stripe.com/i/test2',
        ];
        $paidInvoice = (object) [
            'id' => 'inv_test_deposit_002',
            'number' => 'INV-DEP-002',
            'status' => 'paid',
            'total' => 199650,
            'amount_paid' => 199650,
            'amount_due' => 0,
            'hosted_invoice_url' => 'https://invoice.stripe.com/i/test2',
        ];
        $draftInvoice = (object) [
            'id' => 'inv_test_deposit_002',
            'number' => null,
            'status' => 'draft',
            'total' => 0,
            'amount_paid' => 0,
            'amount_due' => 0,
            'hosted_invoice_url' => null,
        ];

        $invoiceItems = Mockery::mock();
        $invoiceItems->shouldReceive('all')->once()->andReturn((object) ['data' => []]);
        $invoiceItems->shouldReceive('create')->once()->andReturn((object) ['id' => 'ii_2']);

        $taxRates = Mockery::mock();
        $taxRates->shouldReceive('all')->once()->andReturn((object) ['data' => []]);
        $taxRates->shouldReceive('create')->once()->andReturn((object) ['id' => 'txr_21']);

        $customers = Mockery::mock();
        $customers->shouldReceive('retrieve')->once()->andReturn((object) [
            'invoice_settings' => (object) ['default_payment_method' => 'pm_card_visa'],
            'default_source' => null,
        ]);

        $paymentMethods = Mockery::mock();
        $paymentMethods->shouldReceive('all')->never();

        $invoices = Mockery::mock();
        $invoices->shouldReceive('all')->once()->andReturn((object) ['data' => []]);
        $invoices->shouldReceive('create')->once()->withArgs(function (array $payload): bool
        {
            return ($payload['collection_method'] ?? null) === 'charge_automatically'
                && ($payload['pending_invoice_items_behavior'] ?? null) === 'exclude';
        })->andReturn($draftInvoice);
        $invoices->shouldReceive('finalizeInvoice')->once()->with('inv_test_deposit_002')->andReturn($openInvoice);
        $invoices->shouldReceive('pay')->once()->with('inv_test_deposit_002')->andReturn($paidInvoice);

        $client = Mockery::mock(StripeClient::class);
        $client->invoiceItems = $invoiceItems;
        $client->taxRates = $taxRates;
        $client->customers = $customers;
        $client->paymentMethods = $paymentMethods;
        $client->invoices = $invoices;

        $this->bindFakeDepositService($client);

        $this->actingAs($user)
            ->post(route('project.invoice-deposit', $project->id), [
                'description' => 'Adelanto cobrado',
            ])
            ->assertRedirect(route('project.show', $project->id))
            ->assertSessionHas('success')
            ->assertSessionMissing('deposit_invoice_url');

        $invoice = Invoice::withoutGlobalScopes()
            ->where('source_reference_id', 'inv_test_deposit_002')
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame(0.0, (float) $invoice->balance);

        $fresh = $project->fresh();
        $this->assertSame(ProjectStatus::STATUS_IN_PROGRESS, (int) $fresh->status_id);
        $this->assertTrue((bool) data_get($fresh->data, 'deposit_invoice.charged'));
        $this->assertSame('paid', data_get($fresh->data, 'deposit_invoice.stripe_status'));
    }

    private function bindFakeDepositService(StripeClient $client): void
    {
        $service = new class(app(\App\Services\ProjectBudgetSpecService::class), app(EnterpriseVatRateResolver::class), $client) extends ProjectDepositInvoiceService
        {
            public function __construct(
                \App\Services\ProjectBudgetSpecService $budgetSpecService,
                EnterpriseVatRateResolver $vatRateResolver,
                private readonly StripeClient $fakeClient,
            ) {
                parent::__construct($budgetSpecService, $vatRateResolver);
            }

            protected function makeStripeClient(string $secret): StripeClient
            {
                return $this->fakeClient;
            }
        };

        $this->app->instance(ProjectDepositInvoiceService::class, $service);
    }

    #[Test]
    public function deposit_invoice_requires_stripe_customer(): void
    {
        [$user, $project] = $this->createApprovedProject();
        $user->currentTeam->setSetting('stripe_secret', 'sk_test_fake');

        $this->actingAs($user)
            ->from(route('project.show', $project->id))
            ->post(route('project.invoice-deposit', $project->id), [
                'description' => 'Adelanto 30%',
            ])
            ->assertRedirect(route('project.show', $project->id))
            ->assertSessionHasErrors('stripe_customer');
    }

    /**
     * @param  array{identification_number?: string, country?: string, tax_status?: string}  $billing
     */
    private function makeEnterpriseWithBilling(array $billing = []): Enterprise
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $enterprise = Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($team->id)->create([
            'name' => 'Client VAT',
            'type_id' => 1,
            'status_id' => 1,
            'responsible_id' => $user->id,
            'payment_type_id' => null,
            'invoice_type_id' => null,
        ]));

        $taxStatusId = EnterpriseTaxStatusType::query()
            ->where('name', $billing['tax_status'] ?? 'VAT registered')
            ->value('id') ?? 1;

        EnterpriseBillingAddress::query()->create([
            'enterprise_id' => $enterprise->id,
            'name' => 'Billing',
            'identification_number' => $billing['identification_number'] ?? 'B12345674',
            'tax_status_type_id' => $taxStatusId,
            'address' => 'Calle 1',
            'postal_code' => '28001',
            'locality' => 'Madrid',
            'province' => 'Madrid',
            'country' => $billing['country'] ?? 'ES',
            'status' => 1,
        ]);

        return $enterprise->fresh();
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function createApprovedProject(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $enterprise = $this->makeEnterpriseWithBilling([
            'identification_number' => 'B12345674',
            'country' => 'ES',
            'tax_status' => 'VAT registered',
        ]);
        $enterprise->forceFill(['team_id' => $team->id])->save();

        $project = Project::withoutEvents(fn () => Project::factory()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'responsible_id' => $user->id,
            'status_id' => ProjectStatus::STATUS_APPROVED,
            'name' => 'Dashboard Innovación — 4 secciones',
            'real_name' => 'Presupuesto: 4 secciones del Dashboard de Innovación',
            'discount' => 0,
            'data' => [
                'budget_client_response' => [
                    'status' => 'accepted',
                    'accepted_by_name' => 'Cliente',
                    'responded_at' => now()->toIso8601String(),
                ],
                'suggested_tasks' => [
                    [
                        'title' => 'Section A',
                        'estimated_hours' => 10,
                        'unit_price' => 1000,
                        'estimated_tokens' => 0,
                        'included' => true,
                    ],
                ],
            ],
        ]));

        return [$user, $project];
    }
}
