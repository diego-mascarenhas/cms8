<?php

namespace Tests\Unit\Services\Finance;

use App\Models\PaymentSync;
use App\Models\Team;
use App\Services\Finance\InvoiceElectronicPaymentLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceElectronicPaymentLinkServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_label_includes_settlement_payer_name_not_collector_email(): void
    {
        $team = Team::factory()->create();

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '152737100207',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 1591888,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 1591888,
            'charge_created_at' => now()->setDate(2026, 4, 6)->startOfDay(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'operation_type' => 'account_fund',
                'collector_id' => '616106613',
                'payer' => [
                    'id' => '616106613',
                    'email' => 'diego.mascarenhas@icloud.com',
                ],
                'transaction_details' => [
                    'transaction_id' => 'L18MKX9RX6JO40XO9O6WYV',
                ],
                'settlement_payer' => [
                    'name' => 'Hygeia Sa',
                    'id_type' => 'CUIT',
                    'id_number' => '30712345678',
                ],
            ],
        ]);

        $label = app(InvoiceElectronicPaymentLinkService::class)->labelForSync($sync);

        $this->assertStringContainsString('06/04/2026', $label);
        $this->assertStringContainsString('15.918,88 ARS', $label);
        $this->assertStringContainsString('Hygeia Sa (CUIT 30712345678)', $label);
        $this->assertStringContainsString('152737100207', $label);
        $this->assertStringContainsString('L18MKX9RX6JO40XO9O6WYV', $label);
        $this->assertStringNotContainsString('diego.mascarenhas@icloud.com', $label);
    }

    public function test_label_omits_collector_email_when_settlement_payer_is_missing(): void
    {
        $team = Team::factory()->create();

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '149282315377',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 1591888,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 1591888,
            'charge_created_at' => now()->setDate(2026, 3, 12)->startOfDay(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'operation_type' => 'account_fund',
                'collector_id' => '616106613',
                'payer' => [
                    'id' => '616106613',
                    'email' => 'diego.mascarenhas@icloud.com',
                    'identification' => [
                        'type' => 'CUIT',
                        'number' => '20250242000',
                    ],
                ],
            ],
        ]);

        $label = app(InvoiceElectronicPaymentLinkService::class)->labelForSync($sync);

        $this->assertSame('12/03/2026 · 15.918,88 ARS · 149282315377', $label);
        $this->assertStringNotContainsString('diego.mascarenhas@icloud.com', $label);
    }

    public function test_label_keeps_third_party_payer_email(): void
    {
        $team = Team::factory()->create();

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '149052903723',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 1926184,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 1926184,
            'charge_created_at' => now()->setDate(2026, 3, 10)->startOfDay(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'operation_type' => 'regular_payment',
                'collector_id' => '616106613',
                'payer' => [
                    'id' => '291110986',
                    'email' => 'c.barletta@hotmail.com',
                    'identification' => [
                        'type' => 'CUIT',
                        'number' => '20421566217',
                    ],
                ],
            ],
        ]);

        $label = app(InvoiceElectronicPaymentLinkService::class)->labelForSync($sync);

        $this->assertStringContainsString('c.barletta@hotmail.com (CUIT 20421566217)', $label);
    }
}
