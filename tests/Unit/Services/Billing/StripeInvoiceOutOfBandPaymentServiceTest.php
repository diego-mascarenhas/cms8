<?php

namespace Tests\Unit\Services\Billing;

use App\Enums\TransactionType;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Team;
use App\Services\Billing\StripeInvoiceCoreImportService;
use App\Services\Billing\StripeInvoiceOutOfBandPaymentService;
use App\Services\Billing\StripeInvoiceSyncRefresher;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Service\InvoiceService;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeInvoiceOutOfBandPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            PaymentTypeSeeder::class,
            CurrencySeeder::class,
        ]);
    }

    public function test_marks_stripe_invoice_paid_out_of_band_with_type_and_reference_metadata(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('stripe_secret', 'sk_test_fake');

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente Stripe',
            'code' => 'cus_test',
        ]);

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'mp',
            'name' => 'Mercado Pago',
            'status' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0950',
            'date' => now()->toDateString(),
            'gross_amount' => 10608.16,
            'discount' => 0,
            'total_amount' => 10608.16,
            'balance' => 10608.16,
            'status' => 1,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_test_oob_1',
        ]);

        $payment = Payment::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'transaction_type' => TransactionType::INCOME,
            'date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
            'account_id' => $account->id,
            'type_id' => 12,
            'amount' => 10608.16,
            'remarks' => 'Ref: 76V4MR2Z8P4VPR389DEZOL · 0005-0950',
            'status' => 2,
            'source_provider' => 'mercadopago',
            'source_reference_id' => '169690439304',
        ]);

        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('update')
            ->once()
            ->withArgs(function (string $id, array $params): bool
            {
                return $id === 'in_test_oob_1'
                    && ($params['metadata']['payment_method'] ?? null) === 'MercadoPago'
                    && ($params['metadata']['payment_reference'] ?? null) === '76V4MR2Z8P4VPR389DEZOL'
                    && ($params['metadata']['mercadopago_id'] ?? null) === '169690439304'
                    && ($params['metadata']['source_provider'] ?? null) === 'mercadopago';
            })
            ->andReturn((object) ['id' => 'in_test_oob_1']);

        $invoiceService->shouldReceive('pay')
            ->once()
            ->with('in_test_oob_1', ['paid_out_of_band' => true])
            ->andReturn((object) ['id' => 'in_test_oob_1', 'status' => 'paid']);

        $client = Mockery::mock(StripeClient::class);
        $client->invoices = $invoiceService;

        $syncRefresher = Mockery::mock(StripeInvoiceSyncRefresher::class);
        $syncRefresher->shouldReceive('refreshFromStripe')
            ->once()
            ->andReturn(null);

        $coreImport = Mockery::mock(StripeInvoiceCoreImportService::class);
        $coreImport->shouldNotReceive('importFromSyncRow');

        $service = new class($syncRefresher, $coreImport, $client) extends StripeInvoiceOutOfBandPaymentService
        {
            public function __construct(
                StripeInvoiceSyncRefresher $syncRefresher,
                StripeInvoiceCoreImportService $coreImportService,
                private readonly StripeClient $client,
            ) {
                parent::__construct($syncRefresher, $coreImportService);
            }

            protected function makeClient(string $secret): StripeClient
            {
                return $this->client;
            }
        };

        $this->assertTrue($service->markPaidFromPayment($payment));
    }

    public function test_links_metadata_on_already_paid_stripe_invoice_without_pay_call(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('stripe_secret', 'sk_test_fake');

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente Stripe',
            'code' => 'cus_test',
        ]);

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'mp',
            'name' => 'Mercado Pago',
            'status' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0915',
            'date' => now()->toDateString(),
            'gross_amount' => 10608.16,
            'discount' => 0,
            'total_amount' => 10608.16,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_test_paid_link',
        ]);

        \App\Models\InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_test_paid_link',
            'customer_id' => 'cus_test',
            'number' => '0005-0915',
            'status' => 'paid',
            'currency' => 'ars',
            'total' => 10608.16,
            'paid' => true,
            'last_synced_at' => now(),
            'raw_payload' => ['metadata' => []],
        ]);

        $payment = Payment::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'transaction_type' => TransactionType::INCOME,
            'date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
            'account_id' => $account->id,
            'type_id' => 12,
            'amount' => 10608.16,
            'remarks' => 'Ref: XJ8G7V957E38ZM5MNEMPYR · 0005-0915',
            'status' => 2,
            'source_provider' => 'mercadopago',
            'source_reference_id' => '168825700130',
        ]);

        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('update')
            ->once()
            ->withArgs(function (string $id, array $params): bool
            {
                return $id === 'in_test_paid_link'
                    && ($params['metadata']['mercadopago_id'] ?? null) === '168825700130'
                    && ($params['metadata']['payment_reference'] ?? null) === 'XJ8G7V957E38ZM5MNEMPYR';
            })
            ->andReturn((object) ['id' => 'in_test_paid_link']);
        $invoiceService->shouldNotReceive('pay');

        $client = Mockery::mock(StripeClient::class);
        $client->invoices = $invoiceService;

        $syncRefresher = Mockery::mock(StripeInvoiceSyncRefresher::class);
        $syncRefresher->shouldReceive('refreshFromStripe')
            ->once()
            ->andReturn(null);

        $coreImport = Mockery::mock(StripeInvoiceCoreImportService::class);
        $coreImport->shouldNotReceive('importFromSyncRow');

        $service = new class($syncRefresher, $coreImport, $client) extends StripeInvoiceOutOfBandPaymentService
        {
            public function __construct(
                StripeInvoiceSyncRefresher $syncRefresher,
                StripeInvoiceCoreImportService $coreImportService,
                private readonly StripeClient $client,
            ) {
                parent::__construct($syncRefresher, $coreImportService);
            }

            protected function makeClient(string $secret): StripeClient
            {
                return $this->client;
            }
        };

        $this->assertTrue($service->linkMetadataFromPayment($payment));
    }

    public function test_skips_non_stripe_invoices(): void
    {
        $team = Team::factory()->create();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente local',
        ]);
        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'cash',
            'name' => 'Cash',
            'status' => 1,
        ]);
        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'LOCAL-1',
            'date' => now()->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 1,
            'source_provider' => 'manual',
        ]);
        $payment = Payment::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'transaction_type' => TransactionType::INCOME,
            'date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
            'account_id' => $account->id,
            'type_id' => 12,
            'amount' => 100,
            'status' => 2,
            'source_provider' => 'mercadopago',
            'source_reference_id' => 'mp-1',
        ]);

        $service = app(StripeInvoiceOutOfBandPaymentService::class);

        $this->assertFalse($service->markPaidFromPayment($payment));
    }
}
