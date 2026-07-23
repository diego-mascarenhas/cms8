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
            ->assertRedirect(route('payments.index'));

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
            ->assertRedirect(route('payments.index'));

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
            ->assertRedirect(route('payments.syncs.mercadopago.assign', $sync))
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
            'raw_payload' => [],
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
        $this->assertStringNotContainsString('AF Sin Deuda', $html);
        $this->assertStringNotContainsString('Cliente Local', $html);
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
