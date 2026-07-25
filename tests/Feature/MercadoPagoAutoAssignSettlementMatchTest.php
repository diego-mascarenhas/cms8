<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\PaymentSync;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\MercadoPagoAutoAssignMatcherService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MercadoPagoAutoAssignSettlementMatchTest extends TestCase
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

    public function test_matcher_uses_settlement_payer_name_and_skips_ambiguous_open_invoices(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $user->teams()->attach($team);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->actingAs($user);

        $hygeia = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Hygeia',
            'email' => 'hygeia@example.com',
        ]);

        $sibaja = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Nestor Sibaja',
            'email' => 'ns@example.com',
        ]);

        $otherPaid = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Other Hosting Client',
            'email' => 'other@example.com',
        ]);

        $hygeiaInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $hygeia->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0864',
            'date' => '2026-06-13',
            'due_date' => '2026-06-20',
            'gross_amount' => 10608.16,
            'discount' => 0,
            'total_amount' => 10608.16,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_hygeia_paid',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_hygeia_paid',
            'customer_id' => 'cus_HYGEIA',
            'number' => '0005-0864',
            'status' => 'paid',
            'currency' => 'ars',
            'amount_due' => 10608.16,
            'amount_paid' => 10608.16,
            'amount_remaining' => 0,
            'total' => 10608.16,
            'paid' => true,
            'invoice_created_at' => now()->subDays(12),
            'last_synced_at' => now(),
            'raw_payload' => ['metadata' => []],
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $otherPaid->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0999',
            'date' => '2026-06-01',
            'due_date' => '2026-06-10',
            'gross_amount' => 10608.16,
            'discount' => 0,
            'total_amount' => 10608.16,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_other_paid',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_other_paid',
            'customer_id' => 'cus_OTHER',
            'number' => '0005-0999',
            'status' => 'paid',
            'currency' => 'ars',
            'amount_due' => 10608.16,
            'amount_paid' => 10608.16,
            'amount_remaining' => 0,
            'total' => 10608.16,
            'paid' => true,
            'invoice_created_at' => now()->subDays(30),
            'last_synced_at' => now(),
            'raw_payload' => ['metadata' => []],
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $sibaja->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0425',
            'date' => '2026-01-01',
            'due_date' => '2026-01-15',
            'gross_amount' => 10608.16,
            'discount' => 0,
            'total_amount' => 10608.16,
            'balance' => 10608.16,
            'status' => 1,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_sibaja_open',
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
            'charge_created_at' => now()->subDays(10),
            'last_synced_at' => now(),
            'raw_payload' => [
                'id' => 169690439304,
                'operation_type' => 'account_fund',
                'payer' => [
                    'id' => 616106613,
                    'email' => 'diego.mascarenhas@icloud.com',
                ],
                'collector_id' => 616106613,
                'transaction_details' => [
                    'transaction_id' => '76V4MR2Z8P4VPR389DEZOL',
                ],
                'settlement_payer' => [
                    'name' => 'Hygeia Sa',
                    'id_type' => 'CUIT',
                    'id_number' => '30712345678',
                    'enriched_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $suggestions = app(MercadoPagoAutoAssignMatcherService::class)
            ->buildSuggestions((int) $team->id, 25, false);

        $this->assertCount(1, $suggestions);
        $this->assertSame((int) $hygeia->id, $suggestions[0]['enterprise_id']);
        $this->assertSame([(int) $hygeiaInvoice->id], $suggestions[0]['invoice_ids']);
        $this->assertSame('Hygeia Sa', $suggestions[0]['settlement_payer_name']);
        $this->assertSame('30712345678', $suggestions[0]['settlement_payer_id_number']);
        $this->assertStringContainsString(
            __('payment_sync.mercadopago.auto_assign.client_by_settlement_name'),
            $suggestions[0]['reason'],
        );
    }
}
