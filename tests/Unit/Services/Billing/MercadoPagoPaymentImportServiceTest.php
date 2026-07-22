<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Enterprise;
use App\Models\Payment;
use App\Models\PaymentSync;
use App\Models\Team;
use App\Services\Billing\MercadoPagoPaymentImportService;
use App\Services\Billing\MercadoPagoPaymentSyncUpserter;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MercadoPagoPaymentImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            PaymentTypeSeeder::class,
            CurrencySeeder::class,
        ]);
    }

    public function test_upserter_maps_approved_payment_into_payment_syncs(): void
    {
        $team = Team::factory()->create();

        $sync = app(MercadoPagoPaymentSyncUpserter::class)->upsertFromPayload($team->id, [
            'id' => 1234567890,
            'status' => 'approved',
            'currency_id' => 'ARS',
            'transaction_amount' => 1500.50,
            'transaction_amount_refunded' => 0,
            'description' => 'Suscripción mensual',
            'date_approved' => '2026-05-10T14:30:00.000-03:00',
            'payer' => [
                'id' => 998877,
                'email' => 'cliente@example.com',
            ],
        ]);

        $this->assertInstanceOf(PaymentSync::class, $sync);
        $this->assertSame('mercadopago', $sync->provider);
        $this->assertSame('1234567890', $sync->external_id);
        $this->assertSame('approved', $sync->status);
        $this->assertSame('ARS', $sync->currency);
        $this->assertSame(150050, (int) $sync->amount_cents);
        $this->assertSame(150050, (int) $sync->amount_net_cents);
        $this->assertSame('998877', $sync->customer_id);
        $this->assertSame('cliente@example.com', $sync->customer_email);
    }

    public function test_import_creates_core_payment_for_approved_sync(): void
    {
        $team = Team::factory()->create();
        Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente MP',
            'code' => '998877',
            'email' => 'cliente@example.com',
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '1234567890',
            'customer_id' => '998877',
            'customer_email' => 'cliente@example.com',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 150050,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 150050,
            'description' => 'Suscripción mensual',
            'charge_created_at' => '2026-05-10 14:30:00',
            'last_synced_at' => now(),
            'raw_payload' => ['id' => 1234567890],
        ]);

        $payment = app(MercadoPagoPaymentImportService::class)->importFromPaymentSync($sync);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame('mercadopago', $payment->source_provider);
        $this->assertSame('1234567890', $payment->source_reference_id);
        $this->assertSame(1500.50, (float) $payment->amount);
        $this->assertSame(2, (int) $payment->status);
        $this->assertSame(\App\Enums\TransactionType::INCOME, $payment->transaction_type);
    }

    public function test_import_skips_non_approved_payments(): void
    {
        $team = Team::factory()->create();
        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '999',
            'status' => 'pending',
            'currency' => 'ARS',
            'amount_cents' => 10000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 10000,
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $payment = app(MercadoPagoPaymentImportService::class)->importFromPaymentSync($sync);

        $this->assertNull($payment);
    }
}
