<?php

namespace Tests\Feature;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentSync;
use App\Models\User;
use App\Services\Billing\MercadoPagoPaymentMatchUndoService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;
use Stripe\Service\InvoiceService;
use Stripe\StripeClient;
use Tests\TestCase;

class PaymentReconcileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            PaymentTypeSeeder::class,
            CurrencySeeder::class,
        ]);
    }

    public function test_payments_index_shows_reconcile_cta(): void
    {
        [$user] = $this->makeAdminWithTeam();

        $this->actingAs($user)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee(route('payments.reconcile', ['rebuild' => 1]), false)
            ->assertSee(__('payment_sync.mercadopago.open_queue'), false);
    }

    public function test_reconcile_page_shows_comparison_table(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-table-1',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 10000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 10000,
            'charge_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'settlement_payer' => [
                    'name' => 'Extract Payer SA',
                    'id_number' => '20123456789',
                ],
            ],
        ]);

        $statement = BankStatement::query()->create([
            'team_id' => $team->id,
            'provider' => BankStatement::PROVIDER_MERCADOPAGO,
            'period_year' => (int) now()->year,
            'period_month' => (int) now()->month,
            'source' => BankStatement::SOURCE_API,
        ]);

        BankStatementLine::query()->create([
            'bank_statement_id' => $statement->id,
            'external_id' => 'mp-table-1',
            'occurred_at' => now(),
            'amount' => 100,
            'currency' => 'ARS',
            'payer_name' => 'Extract Payer SA',
            'payer_id_number' => '20123456789',
            'payment_sync_id' => $sync->id,
        ]);

        $this->actingAs($user)
            ->get(route('payments.reconcile', ['rebuild' => 1]))
            ->assertOk()
            ->assertSee(__('payment_sync.reconcile.subtitle_table'), false)
            ->assertSee(__('payment_sync.reconcile.columns.statement_payer'), false)
            ->assertSee(__('payment_sync.reconcile.columns.humano_client'), false)
            ->assertSee('payment-reconcile-table', false);
    }

    public function test_reconcile_datatable_search_finds_statement_payer(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-search-hit',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 15000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 15000,
            'charge_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'settlement_payer' => [
                    'name' => 'Josibel Vivas Yoga',
                ],
            ],
        ]);

        PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-search-miss',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 9900,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 9900,
            'charge_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'settlement_payer' => [
                    'name' => 'Other Company',
                ],
            ],
        ]);

        $response = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('payments.reconcile', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => 'Josibel', 'regex' => 'false'],
            'order' => [['column' => 5, 'dir' => 'desc']],
            'columns' => [
                ['data' => 'occurred_at_label', 'name' => 'occurred_at_label', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'amount_label', 'name' => 'amount_label', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'statement_payer', 'name' => 'statement_payer', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'humano_client', 'name' => 'humano_client', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'invoice_label', 'name' => 'invoice_label', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'external_id', 'name' => 'external_id', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'reconcile_status', 'name' => 'reconcile_status', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
            ],
        ]));

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('recordsFiltered'));
        $this->assertStringContainsString('Josibel Vivas Yoga', (string) data_get($response->json('data.0'), 'statement_payer'));
        $this->assertStringNotContainsString('Other Company', json_encode($response->json('data') ?? []));
    }

    public function test_reconcile_accepts_suggestion_from_table_action(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Bratislava Marketing Group',
            'code' => 'cus_BRATISLAVA',
            'email' => 'finance@bratislava.test',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_bratislava_1',
            'customer_id' => 'cus_BRATISLAVA',
            'number' => '0005-0477',
            'status' => 'paid',
            'currency' => 'ars',
            'amount_due' => 6625.12,
            'amount_paid' => 6625.12,
            'amount_remaining' => 0,
            'total' => 6625.12,
            'paid' => true,
            'invoice_created_at' => now()->subDays(10),
            'last_synced_at' => now(),
            'raw_payload' => [
                'status_transitions' => [
                    'paid_at' => now()->subDays(2)->timestamp,
                ],
                'metadata' => [],
            ],
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0477',
            'date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(3)->toDateString(),
            'gross_amount' => 6625.12,
            'discount' => 0,
            'total_amount' => 6625.12,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_bratislava_1',
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-auto-1',
            'customer_id' => '999001',
            'customer_email' => 'finance@bratislava.test',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 662512,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 662512,
            'description' => 'Bank Transfer',
            'charge_created_at' => now()->subDays(2),
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $this->actingAs($user)
            ->post(route('payments.reconcile.accept'), [
                'sync_id' => $sync->id,
                'enterprise_id' => $enterprise->id,
                'invoice_ids' => [$invoice->id],
            ])
            ->assertRedirect(route('payments.reconcile'));

        $payment = Payment::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('source_provider', 'mercadopago')
            ->where('source_reference_id', 'mp-auto-1')
            ->first();

        $this->assertNotNull($payment);
        $this->assertSame((int) $invoice->id, (int) $payment->invoice_id);
    }

    public function test_reconcile_dismiss_marks_mismatch_ok(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Wrong Client SA',
            'code' => 'cus_WRONG',
            'email' => 'wrong@client.test',
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-2000',
            'date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'gross_amount' => 500,
            'discount' => 0,
            'total_amount' => 500,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_wrong_1',
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-mismatch-1',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 50000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 50000,
            'charge_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'settlement_payer' => [
                    'name' => 'Josibel Vivas Yoga',
                    'id_type' => 'CUIT',
                    'id_number' => '27111111111',
                ],
            ],
        ]);

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'mp-mismatch',
            'name' => 'Mercado Pago',
            'status' => 1,
        ]);

        Payment::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'invoice_id' => $invoice->id,
            'account_id' => $account->id,
            'transaction_type' => 'income',
            'date' => now()->toDateString(),
            'amount' => 500,
            'status' => 2,
            'type_id' => 1,
            'source_provider' => 'mercadopago',
            'source_reference_id' => 'mp-mismatch-1',
            'source_synced_at' => now(),
        ]);

        $statement = BankStatement::query()->create([
            'team_id' => $team->id,
            'provider' => BankStatement::PROVIDER_MERCADOPAGO,
            'period_year' => (int) now()->year,
            'period_month' => (int) now()->month,
            'source' => BankStatement::SOURCE_API,
        ]);

        $line = BankStatementLine::query()->create([
            'bank_statement_id' => $statement->id,
            'external_id' => 'mp-mismatch-1',
            'occurred_at' => now(),
            'amount' => 500,
            'currency' => 'ARS',
            'payer_name' => 'Josibel Vivas Yoga',
            'payer_id_number' => '27111111111',
            'payment_sync_id' => $sync->id,
        ]);

        $this->actingAs($user)
            ->post(route('payments.reconcile.dismiss'), [
                'sync_id' => $sync->id,
                'statement_line_id' => $line->id,
            ])
            ->assertRedirect(route('payments.reconcile'));

        $sync->refresh();
        $this->assertTrue($sync->isReconcileDismissed());
        $this->assertTrue($line->fresh()->isDismissed());
    }

    public function test_reconcile_undo_deletes_payment_and_clears_metadata(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();
        $team->setSetting('stripe_secret', 'sk_test_reconcile', [
            'group' => 'stripe',
            'type' => 'password',
            'is_encrypted' => true,
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Assigned Wrong',
            'code' => 'cus_UNDO',
            'email' => 'undo@client.test',
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-3000',
            'date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'gross_amount' => 200,
            'discount' => 0,
            'total_amount' => 200,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_undo_1',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_undo_1',
            'customer_id' => 'cus_UNDO',
            'number' => '0005-3000',
            'status' => 'paid',
            'currency' => 'ars',
            'amount_due' => 200,
            'amount_paid' => 200,
            'amount_remaining' => 0,
            'total' => 200,
            'paid' => true,
            'invoice_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'metadata' => [
                    'mercadopago_id' => 'mp-undo-1',
                    'payment_reference' => 'mp-undo-1',
                ],
            ],
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-undo-1',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 20000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 20000,
            'charge_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'settlement_payer' => [
                    'name' => 'Real Payer Name',
                ],
            ],
        ]);

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'mp-undo',
            'name' => 'Mercado Pago',
            'status' => 1,
        ]);

        Payment::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'invoice_id' => $invoice->id,
            'account_id' => $account->id,
            'transaction_type' => 'income',
            'date' => now()->toDateString(),
            'amount' => 200,
            'status' => 2,
            'type_id' => 1,
            'source_provider' => 'mercadopago',
            'source_reference_id' => 'mp-undo-1',
            'source_synced_at' => now(),
        ]);

        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('update')
            ->once()
            ->with('in_undo_1', Mockery::on(function (array $payload): bool
            {
                return ($payload['metadata']['mercadopago_id'] ?? null) === ''
                    && ($payload['metadata']['payment_reference'] ?? null) === '';
            }))
            ->andReturn((object) ['id' => 'in_undo_1']);

        $client = Mockery::mock(StripeClient::class);
        $client->invoices = $invoiceService;

        $undo = new class($client) extends MercadoPagoPaymentMatchUndoService
        {
            public function __construct(private readonly StripeClient $client) {}

            protected function makeClient(string $secret): StripeClient
            {
                return $this->client;
            }
        };

        $this->app->instance(MercadoPagoPaymentMatchUndoService::class, $undo);

        $this->actingAs($user)
            ->post(route('payments.reconcile.undo'), [
                'sync_id' => $sync->id,
            ])
            ->assertRedirect(route('payments.reconcile'));

        $this->assertFalse(
            Payment::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->where('source_reference_id', 'mp-undo-1')
                ->exists(),
        );

        $invoiceSync = InvoiceSync::query()
            ->where('external_id', 'in_undo_1')
            ->first();

        $this->assertNotNull($invoiceSync);
        $this->assertArrayNotHasKey('mercadopago_id', data_get($invoiceSync->raw_payload, 'metadata', []));
        $this->assertArrayNotHasKey('payment_reference', data_get($invoiceSync->raw_payload, 'metadata', []));
    }

    public function test_legacy_auto_assign_route_redirects_to_reconcile(): void
    {
        [$user] = $this->makeAdminWithTeam();

        $this->actingAs($user)
            ->get(route('payments.syncs.mercadopago.auto-assign', ['rebuild' => 1]))
            ->assertRedirect(route('payments.reconcile', ['rebuild' => 1]));
    }

    /**
     * @return array{0: User, 1: \App\Models\Team}
     */
    private function makeAdminWithTeam(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return [$user, $team];
    }
}
