<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentSync;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MercadoPagoPaymentSyncAssignTest extends TestCase
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

    public function test_admin_can_list_pending_mercadopago_syncs(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-pending-1',
            'customer_id' => '111',
            'customer_email' => 'payer@example.com',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 150050,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 150050,
            'description' => 'Bank Transfer',
            'charge_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $this->actingAs($user)
            ->get(route('payments.syncs.mercadopago.index'))
            ->assertOk()
            ->assertSee('mercadopago-payment-sync-table', false);

        $response = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('payments.syncs.mercadopago.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => 'mp-pending-1', 'regex' => 'false'],
            'order' => [['column' => 2, 'dir' => 'desc']],
            'columns' => [
                ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'transaction_indicator', 'name' => 'transaction_indicator', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'charge_created_at', 'name' => 'charge_created_at', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'amount_label', 'name' => 'amount_label', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'payer', 'name' => 'payer', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'external_id', 'name' => 'external_id', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
            ],
        ]));

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('recordsFiltered'));
        $this->assertStringContainsString('mp-pending-1', (string) data_get($response->json('data.0'), 'external_id'));
        $this->assertStringContainsString('bg-success', (string) data_get($response->json('data.0'), 'transaction_indicator'));
    }

    public function test_datatable_defaults_to_unassigned_and_hides_stripe_linked(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-unassigned-1',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 10000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 10000,
            'charge_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-stripe-linked-1',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 20000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 20000,
            'charge_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'transaction_details' => [
                    'transaction_id' => 'REFSTRIPELINKED001',
                ],
            ],
        ]);

        \App\Models\InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_filter_test',
            'status' => 'paid',
            'currency' => 'ars',
            'number' => '0005-0999',
            'paid' => true,
            'invoice_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'metadata' => [
                    'payment_reference' => 'REFSTRIPELINKED001',
                ],
            ],
        ]);

        $unassigned = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('payments.syncs.mercadopago.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'assignment_filter' => 'unassigned',
            'order' => [['column' => 2, 'dir' => 'desc']],
            'columns' => [
                ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'transaction_indicator', 'name' => 'transaction_indicator', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'charge_created_at', 'name' => 'charge_created_at', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'amount_label', 'name' => 'amount_label', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'payer', 'name' => 'payer', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'external_id', 'name' => 'external_id', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
            ],
        ]));

        $unassigned->assertOk();
        $this->assertSame(1, (int) $unassigned->json('recordsFiltered'));
        $this->assertStringContainsString('mp-unassigned-1', (string) data_get($unassigned->json('data.0'), 'external_id'));

        $stripeOnly = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('payments.syncs.mercadopago.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'assignment_filter' => 'stripe',
            'order' => [['column' => 2, 'dir' => 'desc']],
            'columns' => [
                ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'transaction_indicator', 'name' => 'transaction_indicator', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'charge_created_at', 'name' => 'charge_created_at', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'amount_label', 'name' => 'amount_label', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'payer', 'name' => 'payer', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'external_id', 'name' => 'external_id', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
            ],
        ]));

        $stripeOnly->assertOk();
        $this->assertSame(1, (int) $stripeOnly->json('recordsFiltered'));
        $this->assertStringContainsString('mp-stripe-linked-1', (string) data_get($stripeOnly->json('data.0'), 'external_id'));
    }

    public function test_stripe_filter_includes_already_imported_linked_syncs(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Hygeia',
            'code' => 'cus_hygeia',
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
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_hygeia_linked',
        ]);

        PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '169690439304',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 1060816,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 1060816,
            'charge_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'transaction_details' => [
                    'transaction_id' => '76V4MR2Z8P4VPR389DEZOL',
                ],
            ],
        ]);

        \App\Models\InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_hygeia_linked',
            'status' => 'paid',
            'currency' => 'ars',
            'number' => '0005-0950',
            'paid' => true,
            'invoice_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'metadata' => [
                    'payment_reference' => '76V4MR2Z8P4VPR389DEZOL',
                ],
            ],
        ]);

        $account = \App\Models\PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'mp',
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
            'amount' => 10608.16,
            'status' => 2,
            'type_id' => 12,
            'source_provider' => 'mercadopago',
            'source_reference_id' => '169690439304',
        ]);

        $response = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('payments.syncs.mercadopago.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'assignment_filter' => 'stripe',
            'order' => [['column' => 2, 'dir' => 'desc']],
            'columns' => [
                ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'transaction_indicator', 'name' => 'transaction_indicator', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'charge_created_at', 'name' => 'charge_created_at', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'amount_label', 'name' => 'amount_label', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'payer', 'name' => 'payer', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'external_id', 'name' => 'external_id', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
            ],
        ]));

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('recordsFiltered'));
        $this->assertStringContainsString('169690439304', (string) data_get($response->json('data.0'), 'external_id'));
        $this->assertStringContainsString(
            route('invoice.show', $invoice->id),
            (string) data_get($response->json('data.0'), 'action'),
        );

        $all = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('payments.syncs.mercadopago.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'assignment_filter' => 'all',
            'search' => ['value' => '169690439304', 'regex' => 'false'],
            'order' => [['column' => 2, 'dir' => 'desc']],
            'columns' => [
                ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'transaction_indicator', 'name' => 'transaction_indicator', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'charge_created_at', 'name' => 'charge_created_at', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'amount_label', 'name' => 'amount_label', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'payer', 'name' => 'payer', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'external_id', 'name' => 'external_id', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
            ],
        ]));

        $all->assertOk();
        $this->assertSame(1, (int) $all->json('recordsFiltered'));
        $this->assertStringContainsString('169690439304', (string) data_get($all->json('data.0'), 'external_id'));
    }

    public function test_datatable_marks_syncs_linked_via_stripe_payment_reference(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente Stripe MP',
            'code' => 'cus_mp_linked',
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
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_stripe_linked_mp',
        ]);

        PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '169690439304',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 1060816,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 1060816,
            'description' => 'Bank Transfer',
            'charge_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'operation_type' => 'account_fund',
                'transaction_details' => [
                    'transaction_id' => '76V4MR2Z8P4VPR389DEZOL',
                ],
            ],
        ]);

        \App\Models\InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_stripe_linked_mp',
            'status' => 'paid',
            'currency' => 'ars',
            'number' => '0005-0950',
            'amount_due' => 10608.16,
            'amount_paid' => 10608.16,
            'amount_remaining' => 0,
            'total' => 10608.16,
            'paid' => true,
            'invoice_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'metadata' => [
                    'payment_method' => 'MercadoPago',
                    'payment_reference' => '76V4MR2Z8P4VPR389DEZOL',
                    'source_provider' => 'mercadopago',
                ],
            ],
        ]);

        $response = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('payments.syncs.mercadopago.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'assignment_filter' => 'stripe',
            'search' => ['value' => '169690439304', 'regex' => 'false'],
            'order' => [['column' => 2, 'dir' => 'desc']],
            'columns' => [
                ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'transaction_indicator', 'name' => 'transaction_indicator', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'charge_created_at', 'name' => 'charge_created_at', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'amount_label', 'name' => 'amount_label', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'payer', 'name' => 'payer', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'external_id', 'name' => 'external_id', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
            ],
        ]));

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('recordsFiltered'));
        $this->assertStringContainsString(
            route('invoice.show', $invoice->id),
            (string) data_get($response->json('data.0'), 'action'),
        );
        $this->assertStringContainsString('0005-0950', (string) data_get($response->json('data.0'), 'action'));
        $this->assertStringNotContainsString(
            __('payment_sync.mercadopago.assign_action'),
            (string) data_get($response->json('data.0'), 'action'),
        );
    }

    public function test_datatable_marks_syncs_linked_via_stripe_mercadopago_id(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente Stripe MP id',
            'code' => 'cus_mp_id_linked',
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0951',
            'date' => now()->toDateString(),
            'gross_amount' => 5000,
            'discount' => 0,
            'total_amount' => 5000,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_stripe_linked_mp_id',
        ]);

        PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '168215955681',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 500000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 500000,
            'description' => 'Bank Transfer',
            'charge_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'operation_type' => 'regular_payment',
                'transaction_details' => [
                    'transaction_id' => 'UNRELATED_BANK_REF',
                ],
            ],
        ]);

        \App\Models\InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_stripe_linked_mp_id',
            'status' => 'paid',
            'currency' => 'ars',
            'number' => '0005-0951',
            'amount_due' => 5000,
            'amount_paid' => 5000,
            'amount_remaining' => 0,
            'total' => 5000,
            'paid' => true,
            'invoice_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'metadata' => [
                    'payment_method' => 'MercadoPago',
                    'mercadopago_id' => '168215955681',
                    'source_provider' => 'mercadopago',
                ],
            ],
        ]);

        $response = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('payments.syncs.mercadopago.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'assignment_filter' => 'stripe',
            'search' => ['value' => '168215955681', 'regex' => 'false'],
            'order' => [['column' => 2, 'dir' => 'desc']],
            'columns' => [
                ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'transaction_indicator', 'name' => 'transaction_indicator', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'charge_created_at', 'name' => 'charge_created_at', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'amount_label', 'name' => 'amount_label', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'payer', 'name' => 'payer', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'external_id', 'name' => 'external_id', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
            ],
        ]));

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('recordsFiltered'));
        $this->assertStringContainsString(
            route('invoice.show', $invoice->id),
            (string) data_get($response->json('data.0'), 'action'),
        );
        $this->assertStringContainsString('0005-0951', (string) data_get($response->json('data.0'), 'action'));
    }

    public function test_stripe_linked_sync_without_local_invoice_links_to_materialize_route(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente Stripe MP',
            'code' => 'cus_mp_linked',
            'email' => 'cliente@example.com',
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '169690439304',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 1060816,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 1060816,
            'description' => 'Bank Transfer',
            'charge_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'operation_type' => 'account_fund',
                'transaction_details' => [
                    'transaction_id' => '76V4MR2Z8P4VPR389DEZOL',
                ],
            ],
        ]);

        \App\Models\InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_stripe_linked_mp',
            'customer_id' => 'cus_mp_linked',
            'customer_email' => 'cliente@example.com',
            'status' => 'paid',
            'currency' => 'ars',
            'number' => '0005-0950',
            'amount_due' => 10608.16,
            'amount_paid' => 10608.16,
            'amount_remaining' => 0,
            'total' => 10608.16,
            'paid' => true,
            'invoice_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'metadata' => [
                    'payment_method' => 'MercadoPago',
                    'payment_reference' => '76V4MR2Z8P4VPR389DEZOL',
                    'source_provider' => 'mercadopago',
                ],
            ],
        ]);

        $response = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('payments.syncs.mercadopago.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'assignment_filter' => 'stripe',
            'search' => ['value' => '169690439304', 'regex' => 'false'],
            'order' => [['column' => 2, 'dir' => 'desc']],
            'columns' => [
                ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'transaction_indicator', 'name' => 'transaction_indicator', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'charge_created_at', 'name' => 'charge_created_at', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'amount_label', 'name' => 'amount_label', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'payer', 'name' => 'payer', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'external_id', 'name' => 'external_id', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
            ],
        ]));

        $response->assertOk();
        $this->assertStringContainsString(
            route('payments.syncs.mercadopago.linked-invoice', $sync),
            (string) data_get($response->json('data.0'), 'action'),
        );
        $this->assertStringContainsString('0005-0950', (string) data_get($response->json('data.0'), 'action'));
        $this->assertStringNotContainsString(
            __('payment_sync.mercadopago.assign_action'),
            (string) data_get($response->json('data.0'), 'action'),
        );

        $this->actingAs($user)
            ->get(route('payments.syncs.mercadopago.assign', $sync))
            ->assertRedirect();

        $invoice = Invoice::withoutGlobalScopes()
            ->where('source_reference_id', 'in_stripe_linked_mp')
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame('0005-0950', $invoice->number);

        $payment = Payment::withoutGlobalScopes()
            ->where('source_provider', 'mercadopago')
            ->where('source_reference_id', '169690439304')
            ->first();

        $this->assertNotNull($payment);
        $this->assertSame((int) $invoice->id, (int) $payment->invoice_id);
        $this->assertEqualsWithDelta(10608.16, (float) $payment->amount, 0.01);
    }

    public function test_admin_can_import_sync_with_forced_enterprise_and_invoice(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente Transfer',
            'code' => null,
            'email' => 'cliente@example.com',
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-1000',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 1500.50,
            'discount' => 0,
            'total_amount' => 1500.50,
            'balance' => 1500.50,
            'status' => 1,
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-force-1',
            'customer_id' => '999888',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 150050,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 150050,
            'description' => 'Bank Transfer',
            'charge_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $this->actingAs($user)
            ->post(route('payments.syncs.mercadopago.import', $sync), [
                'enterprise_id' => $enterprise->id,
                'invoice_ids' => [$invoice->id],
                'remarks' => '0005-0950',
                'link_payer_code' => 1,
            ])
            ->assertRedirect(route('payments.syncs.mercadopago.index'));

        $payment = Payment::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('source_provider', 'mercadopago')
            ->where('source_reference_id', 'mp-force-1')
            ->first();

        $this->assertNotNull($payment);
        $this->assertSame($enterprise->id, (int) $payment->enterprise_id);
        $this->assertSame($invoice->id, (int) $payment->invoice_id);
        $this->assertSame(12, (int) $payment->type_id);
        $this->assertSame('0005-0950', $payment->remarks);
        $this->assertSame('999888', $enterprise->fresh()->code);
    }

    public function test_assign_materializes_open_stripe_invoice_sync_for_client(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => '301',
            'code' => 'cus_TDu3TWcwCTek5O',
            'email' => 'info@trescientosuno.com',
        ]);

        \App\Models\InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_1TvCA8RwN51ygFdebsmbYyGd',
            'customer_id' => 'cus_TDu3TWcwCTek5O',
            'customer_email' => 'info@trescientosuno.com',
            'number' => '0005-0957',
            'status' => 'open',
            'currency' => 'eur',
            'amount_due' => 24.00,
            'amount_paid' => 0,
            'amount_remaining' => 24.00,
            'subtotal' => 19.83,
            'total' => 24.00,
            'paid' => false,
            'invoice_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-open-stripe',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 1060816,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 1060816,
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $this->actingAs($user)
            ->get(route('payments.syncs.mercadopago.assign', [
                'sync' => $sync,
                'enterprise_id' => $enterprise->id,
            ]))
            ->assertOk()
            ->assertSee('0005-0957', false)
            ->assertDontSee(__('payment_sync.mercadopago.no_open_invoices'), false);

        $this->assertDatabaseHas('invoices', [
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_1TvCA8RwN51ygFdebsmbYyGd',
            'number' => '0005-0957',
        ]);

        $local = Invoice::withoutGlobalScopes()
            ->where('source_reference_id', 'in_1TvCA8RwN51ygFdebsmbYyGd')
            ->first();

        $this->assertNotNull($local);
        $this->assertGreaterThan(0, (float) $local->balance);
    }

    public function test_admin_can_import_split_across_two_invoices(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente Combo',
            'email' => 'combo@example.com',
        ]);

        $invoiceA = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-3001',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 1000,
            'discount' => 0,
            'total_amount' => 1000,
            'balance' => 1000,
            'status' => 1,
        ]);
        $invoiceB = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-3002',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 500,
            'discount' => 0,
            'total_amount' => 500,
            'balance' => 500,
            'status' => 1,
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-split-1',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 150000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 150000,
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $this->actingAs($user)
            ->get(route('payments.syncs.mercadopago.assign', ['sync' => $sync, 'enterprise_id' => $enterprise->id]))
            ->assertOk()
            ->assertSee('Sugerencias por importe', false)
            ->assertSee('Suma de facturas', false);

        $this->actingAs($user)
            ->post(route('payments.syncs.mercadopago.import', $sync), [
                'enterprise_id' => $enterprise->id,
                'invoice_ids' => [$invoiceA->id, $invoiceB->id],
            ])
            ->assertRedirect(route('payments.syncs.mercadopago.index'));

        $this->assertSame(2, Payment::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('source_provider', 'mercadopago')
            ->where('source_reference_id', 'like', 'mp-split-1:%')
            ->count());
    }

    public function test_import_rejects_invoice_from_other_enterprise(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $enterpriseA = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente A',
            'email' => 'a@example.com',
        ]);
        $enterpriseB = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente B',
            'email' => 'b@example.com',
        ]);

        $invoiceB = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterpriseB->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-2000',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 1,
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-mismatch',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 10000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 10000,
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $this->actingAs($user)
            ->from(route('payments.syncs.mercadopago.assign', $sync))
            ->post(route('payments.syncs.mercadopago.import', $sync), [
                'enterprise_id' => $enterpriseA->id,
                'invoice_ids' => [$invoiceB->id],
            ])
            ->assertRedirect(route('payments.syncs.mercadopago.assign', [
                'sync' => $sync,
                'enterprise_id' => $enterpriseA->id,
            ]))
            ->assertSessionHasErrors('invoice_ids');

        $this->assertFalse(
            Payment::withoutGlobalScopes()
                ->where('source_reference_id', 'mp-mismatch')
                ->exists(),
        );
    }

    public function test_assign_selector_lists_only_stripe_enterprises_with_outstanding_balance(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $withDebt = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 3,
            'status_id' => 1,
            'name' => 'AF Con Deuda',
            'code' => 'cus_with_debt',
            'email' => 'debt@example.com',
        ]);
        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $withDebt->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-AF-1',
            'date' => now()->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 1,
        ]);

        $paidOnly = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 3,
            'status_id' => 1,
            'name' => 'AF Sin Deuda',
            'code' => 'cus_paid_only',
        ]);
        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $paidOnly->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-AF-0',
            'date' => now()->toDateString(),
            'gross_amount' => 50,
            'discount' => 0,
            'total_amount' => 50,
            'balance' => 0,
            'status' => 2,
        ]);

        $noStripe = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente Local',
            'code' => null,
            'email' => 'local@example.com',
        ]);
        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $noStripe->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-LOC-1',
            'date' => now()->toDateString(),
            'gross_amount' => 80,
            'discount' => 0,
            'total_amount' => 80,
            'balance' => 80,
            'status' => 1,
        ]);

        $openSyncOnly = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente Sync Abierto',
            'code' => 'cus_open_sync',
        ]);
        \App\Models\InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_open_sync_only',
            'customer_id' => 'cus_open_sync',
            'number' => '0005-SYNC',
            'status' => 'open',
            'currency' => 'eur',
            'amount_due' => 24.00,
            'amount_paid' => 0,
            'amount_remaining' => 24.00,
            'paid' => false,
            'last_synced_at' => now(),
            'raw_payload' => ['collection_method' => 'send_invoice'],
        ]);

        $chargeAutomatically = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente Con Tarjeta',
            'code' => 'cus_charge_auto',
        ]);
        \App\Models\InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_charge_auto_open',
            'customer_id' => 'cus_charge_auto',
            'number' => '0005-CARD',
            'status' => 'open',
            'currency' => 'eur',
            'amount_due' => 50.00,
            'amount_paid' => 0,
            'amount_remaining' => 50.00,
            'paid' => false,
            'last_synced_at' => now(),
            'raw_payload' => ['collection_method' => 'charge_automatically'],
        ]);

        $paidUnlinked = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'AF Paid Unlinked',
            'code' => 'cus_paid_unlinked',
        ]);
        \App\Models\InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_paid_unlinked_only',
            'customer_id' => 'cus_paid_unlinked',
            'number' => '0005-PAID',
            'status' => 'paid',
            'currency' => 'ars',
            'amount_due' => 100,
            'amount_paid' => 100,
            'amount_remaining' => 0,
            'total' => 100,
            'paid' => true,
            'last_synced_at' => now(),
            'raw_payload' => ['metadata' => [], 'collection_method' => 'send_invoice'],
        ]);

        $settledLocally = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente Sync Saldo Cero',
            'code' => 'cus_settled_local',
        ]);
        \App\Models\InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_settled_local_open',
            'customer_id' => 'cus_settled_local',
            'number' => '0005-SETTLED',
            'status' => 'open',
            'currency' => 'ars',
            'amount_due' => 200,
            'amount_paid' => 0,
            'amount_remaining' => 200,
            'paid' => false,
            'last_synced_at' => now(),
            'raw_payload' => ['collection_method' => 'send_invoice'],
        ]);
        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $settledLocally->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-SETTLED',
            'date' => now()->toDateString(),
            'gross_amount' => 200,
            'discount' => 0,
            'total_amount' => 200,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_settled_local_open',
        ]);

        $coveredByCreditNote = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente Con Nota Credito',
            'code' => 'cus_with_credit_note',
        ]);
        \App\Models\InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_open_with_cn',
            'customer_id' => 'cus_with_credit_note',
            'number' => '0005-CN-BASE',
            'status' => 'open',
            'currency' => 'eur',
            'amount_due' => 24,
            'amount_paid' => 0,
            'amount_remaining' => 24,
            'total' => 24,
            'paid' => false,
            'last_synced_at' => now(),
            'raw_payload' => ['collection_method' => 'send_invoice'],
        ]);
        \App\Models\InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'cn_covers_open_invoice',
            'customer_id' => 'cus_with_credit_note',
            'number' => '0005-CN-BASE-CN-01',
            'status' => 'issued',
            'currency' => 'eur',
            'amount_due' => 24,
            'amount_paid' => 0,
            'amount_remaining' => 0,
            'total' => 24,
            'paid' => false,
            'last_synced_at' => now(),
            'raw_payload' => [
                'invoice' => 'in_open_with_cn',
                'amount' => 2400,
            ],
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-selector-filter',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 10000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 10000,
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $html = $this->actingAs($user)
            ->get(route('payments.syncs.mercadopago.assign', $sync))
            ->assertOk()
            ->assertSee(__('payment_sync.mercadopago.enterprise_filter_hint'), false)
            ->getContent();

        $this->assertStringContainsString('AF Con Deuda', $html);
        $this->assertStringContainsString('Cliente Sync Abierto', $html);
        $this->assertStringNotContainsString('AF Paid Unlinked', $html);
        $this->assertStringNotContainsString('Cliente Con Tarjeta', $html);
        $this->assertStringNotContainsString('Cliente Sync Saldo Cero', $html);
        $this->assertStringNotContainsString('Cliente Con Nota Credito', $html);
        $this->assertStringNotContainsString('AF Sin Deuda', $html);
        $this->assertStringNotContainsString('Cliente Local', $html);
    }

    public function test_assign_lists_paid_stripe_invoices_without_mercadopago_metadata(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Nora Schvartz',
            'code' => 'cus_TWH5XgAfpGuIbF',
        ]);

        \App\Models\InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_paid_unlinked_nora',
            'customer_id' => 'cus_TWH5XgAfpGuIbF',
            'number' => '0005-0915',
            'status' => 'paid',
            'currency' => 'ars',
            'amount_due' => 10608.16,
            'amount_paid' => 10608.16,
            'amount_remaining' => 0,
            'total' => 10608.16,
            'paid' => true,
            'invoice_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'metadata' => [],
            ],
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '168825700130',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 1060816,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 1060816,
            'charge_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'transaction_details' => [
                    'transaction_id' => 'XJ8G7V957E38ZM5MNEMPYR',
                ],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('payments.syncs.mercadopago.assign', [
                'sync' => $sync,
                'enterprise_id' => $enterprise->id,
            ]))
            ->assertOk()
            ->assertSee('0005-0915', false)
            ->assertSee(__('payment_sync.mercadopago.paid_unlinked_heading'), false)
            ->assertSee(__('payment_sync.mercadopago.suggestion_paid_link'), false);

        $invoice = Invoice::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('source_reference_id', 'in_paid_unlinked_nora')
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame(0.0, (float) $invoice->balance);

        $this->actingAs($user)
            ->post(route('payments.syncs.mercadopago.import', $sync), [
                'enterprise_id' => $enterprise->id,
                'invoice_ids' => [$invoice->id],
                'link_payer_code' => 0,
            ])
            ->assertRedirect(route('payments.syncs.mercadopago.index'));

        $payment = Payment::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('source_provider', 'mercadopago')
            ->where('source_reference_id', '168825700130')
            ->first();

        $this->assertNotNull($payment);
        $this->assertSame((int) $invoice->id, (int) $payment->invoice_id);
        $this->assertEqualsWithDelta(10608.16, (float) $payment->amount, 0.01);
    }

    public function test_admin_can_import_paid_unlinked_split_matching_payment_total(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Naturis',
            'code' => 'cus_TWGg5P4h1jtpnV',
        ]);

        $invoiceIds = [];
        foreach (['0005-A', '0005-B', '0005-C', '0005-D'] as $index => $number)
        {
            $externalId = 'in_naturis_paid_'.$index;
            \App\Models\InvoiceSync::query()->create([
                'team_id' => $team->id,
                'provider' => 'stripe',
                'external_id' => $externalId,
                'customer_id' => 'cus_TWGg5P4h1jtpnV',
                'number' => $number,
                'status' => 'paid',
                'currency' => 'ars',
                'amount_due' => 9547.34,
                'amount_paid' => 9547.34,
                'amount_remaining' => 0,
                'total' => 9547.34,
                'paid' => true,
                'invoice_created_at' => now()->subMonths(4 - $index),
                'last_synced_at' => now(),
                'raw_payload' => ['metadata' => []],
            ]);

            $invoice = Invoice::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'enterprise_id' => $enterprise->id,
                'type_id' => 1,
                'operation' => 'sell',
                'number' => $number,
                'date' => now()->subMonths(4 - $index)->toDateString(),
                'gross_amount' => 9547.34,
                'discount' => 0,
                'total_amount' => 9547.34,
                'balance' => 0,
                'status' => 2,
                'source_provider' => 'stripe',
                'source_reference_id' => $externalId,
            ]);
            $invoiceIds[] = $invoice->id;
        }

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '163897957145',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 3818936,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 3818936,
            'charge_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'transaction_details' => [
                    'transaction_id' => 'WY7ZEPN6MK1ROG742Q0M51',
                ],
            ],
        ]);

        $this->actingAs($user)
            ->post(route('payments.syncs.mercadopago.import', $sync), [
                'enterprise_id' => $enterprise->id,
                'invoice_ids' => $invoiceIds,
                'link_payer_code' => 0,
            ])
            ->assertRedirect(route('payments.syncs.mercadopago.index'));

        $payments = Payment::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('source_provider', 'mercadopago')
            ->where('source_reference_id', 'like', '163897957145:%')
            ->orderBy('id')
            ->get();

        $this->assertCount(4, $payments);
        $this->assertEqualsWithDelta(38189.36, (float) $payments->sum('amount'), 0.01);
        foreach ($payments as $payment)
        {
            $this->assertEqualsWithDelta(9547.34, (float) $payment->amount, 0.01);
            $this->assertContains((int) $payment->invoice_id, $invoiceIds);
        }
    }

    public function test_alianza_stripe_client_can_be_assigned_on_import(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 3,
            'status_id' => 1,
            'name' => 'AF Construcciones',
            'code' => 'cus_TWDzSciIesXAx2',
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0816',
            'date' => now()->toDateString(),
            'gross_amount' => 15918.88,
            'discount' => 0,
            'total_amount' => 15918.88,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_af_paid_link',
        ]);

        \App\Models\InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_af_paid_link',
            'customer_id' => 'cus_TWDzSciIesXAx2',
            'number' => '0005-0816',
            'status' => 'paid',
            'currency' => 'ars',
            'total' => 15918.88,
            'paid' => true,
            'last_synced_at' => now(),
            'raw_payload' => ['metadata' => []],
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '161534392800',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 1591888,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 1591888,
            'charge_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $this->actingAs($user)
            ->post(route('payments.syncs.mercadopago.import', $sync), [
                'enterprise_id' => $enterprise->id,
                'invoice_ids' => [$invoice->id],
                'link_payer_code' => 0,
            ])
            ->assertRedirect(route('payments.syncs.mercadopago.index'))
            ->assertSessionDoesntHaveErrors();

        $this->assertTrue(
            Payment::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->where('source_reference_id', '161534392800')
                ->exists(),
        );
    }

    public function test_assign_backfills_local_payments_when_stripe_already_has_mercadopago_metadata(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'AEJBA',
            'code' => 'cus_TWDwJSHxKsgNPT',
        ]);

        $invoiceIds = [];
        foreach ([0, 1, 2, 3] as $index)
        {
            $externalId = 'in_aejba_mar20_'.$index;
            \App\Models\InvoiceSync::query()->create([
                'team_id' => $team->id,
                'provider' => 'stripe',
                'external_id' => $externalId,
                'customer_id' => 'cus_TWDwJSHxKsgNPT',
                'number' => '0005-0'.(600 + $index),
                'status' => 'paid',
                'currency' => 'ars',
                'amount_due' => 51235,
                'amount_paid' => 51235,
                'amount_remaining' => 0,
                'total' => 51235,
                'paid' => true,
                'invoice_created_at' => now()->subMonths(4 - $index),
                'last_synced_at' => now(),
                'raw_payload' => [
                    'metadata' => [
                        'mercadopago_id' => '150438897505',
                        'payment_reference' => '67REZ8NPQJZ1POWP94KVGO',
                        'payment_method' => 'MercadoPago',
                    ],
                    'status_transitions' => [
                        'paid_at' => now()->subMonths(4)->timestamp,
                    ],
                ],
            ]);

            $invoice = Invoice::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'enterprise_id' => $enterprise->id,
                'type_id' => 1,
                'operation' => 'sell',
                'number' => '0005-0'.(600 + $index),
                'date' => now()->subMonths(4 - $index)->toDateString(),
                'gross_amount' => 51235,
                'discount' => 0,
                'total_amount' => 51235,
                'balance' => 0,
                'status' => 2,
                'source_provider' => 'stripe',
                'source_reference_id' => $externalId,
            ]);
            $invoiceIds[] = $invoice->id;
        }

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '150438897505',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 20494000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 20494000,
            'charge_created_at' => now()->subMonths(4),
            'last_synced_at' => now(),
            'raw_payload' => [
                'transaction_details' => [
                    'transaction_id' => '67REZ8NPQJZ1POWP94KVGO',
                ],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('payments.syncs.mercadopago.assign', $sync))
            ->assertRedirect(route('payments.syncs.mercadopago.index'))
            ->assertSessionHas('success');

        $payments = Payment::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('source_provider', 'mercadopago')
            ->where('source_reference_id', 'like', '150438897505:%')
            ->get();

        $this->assertCount(4, $payments);
        $this->assertEqualsWithDelta(204940.0, (float) $payments->sum('amount'), 0.01);
        $this->assertEqualsCanonicalizing($invoiceIds, $payments->pluck('invoice_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_assign_adopts_existing_local_payments_without_duplicating(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'AEJBA',
            'code' => 'cus_TWDwJSHxKsgNPT',
        ]);

        $account = \App\Models\PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'mp',
            'name' => 'Mercado Pago',
            'status' => 1,
        ]);
        $typeId = (int) \App\Models\PaymentType::query()->where('name', 'MercadoPago')->value('id');

        $invoiceIds = [];
        $existingPaymentIds = [];
        foreach ([0, 1, 2, 3] as $index)
        {
            $externalId = 'in_aejba_adopt_'.$index;
            \App\Models\InvoiceSync::query()->create([
                'team_id' => $team->id,
                'provider' => 'stripe',
                'external_id' => $externalId,
                'customer_id' => 'cus_TWDwJSHxKsgNPT',
                'number' => '0005-0'.(700 + $index),
                'status' => 'paid',
                'currency' => 'ars',
                'total' => 51235,
                'paid' => true,
                'invoice_created_at' => now()->subMonths(4 - $index),
                'last_synced_at' => now(),
                'raw_payload' => [
                    'metadata' => [
                        'mercadopago_id' => '150438897505',
                        'payment_reference' => '67REZ8NPQJZ1POWP94KVGO',
                    ],
                ],
            ]);

            $invoice = Invoice::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'enterprise_id' => $enterprise->id,
                'type_id' => 1,
                'operation' => 'sell',
                'number' => '0005-0'.(700 + $index),
                'date' => now()->subMonths(4 - $index)->toDateString(),
                'gross_amount' => 51235,
                'discount' => 0,
                'total_amount' => 51235,
                'balance' => 0,
                'status' => 2,
                'source_provider' => 'stripe',
                'source_reference_id' => $externalId,
            ]);
            $invoiceIds[] = $invoice->id;

            $payment = Payment::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'enterprise_id' => $enterprise->id,
                'transaction_type' => \App\Enums\TransactionType::INCOME,
                'date' => '2026-03-20',
                'invoice_id' => $invoice->id,
                'account_id' => $account->id,
                'type_id' => $typeId,
                'amount' => 51235,
                'remarks' => 'Pago local previo',
                'status' => 2,
                'source_provider' => 'manual',
                'source_reference_id' => 'legacy-local-'.$index,
            ]);
            $existingPaymentIds[] = $payment->id;
        }

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '150438897505',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 20494000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 20494000,
            'charge_created_at' => now()->subMonths(4),
            'last_synced_at' => now(),
            'raw_payload' => [
                'transaction_details' => [
                    'transaction_id' => '67REZ8NPQJZ1POWP94KVGO',
                ],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('payments.syncs.mercadopago.assign', $sync))
            ->assertRedirect(route('payments.syncs.mercadopago.index'))
            ->assertSessionHas('success');

        $payments = Payment::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereIn('invoice_id', $invoiceIds)
            ->where('status', '!=', 0)
            ->get();

        $this->assertCount(4, $payments);
        $this->assertEqualsCanonicalizing($existingPaymentIds, $payments->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertTrue($payments->every(
            fn (Payment $payment) => $payment->source_provider === 'mercadopago'
                && str_starts_with((string) $payment->source_reference_id, '150438897505:'),
        ));
    }

    /**
     * @return array{0: User, 1: \App\Models\Team}
     */
    private function makeAdminWithTeam(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return [$user, $team];
    }
}
