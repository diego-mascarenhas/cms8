<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Payment;
use App\Models\PaymentSync;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MercadoPagoPaymentSyncCommandTest extends TestCase
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

    public function test_sync_command_upserts_payment_syncs_from_api(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('mercadopago_access_token', 'APP_USR-test-token', [
            'group' => 'mercadopago',
            'type' => 'password',
            'is_encrypted' => true,
        ]);

        Http::fake([
            'api.mercadopago.com/v1/payments/search*' => Http::response([
                'paging' => ['total' => 1, 'limit' => 50, 'offset' => 0],
                'results' => [
                    [
                        'id' => 555001,
                        'status' => 'approved',
                        'currency_id' => 'ARS',
                        'transaction_amount' => 2500,
                        'transaction_amount_refunded' => 0,
                        'description' => 'Pago test',
                        'date_created' => '2026-05-01T10:00:00.000-03:00',
                        'date_approved' => '2026-05-01T10:00:05.000-03:00',
                        'payer' => [
                            'id' => 111,
                            'email' => 'payer@example.com',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('mercadopago:sync-payments', [
            '--team_id' => $team->id,
            '--limit' => 10,
        ])->assertSuccessful();

        $sync = PaymentSync::query()
            ->where('team_id', $team->id)
            ->where('provider', 'mercadopago')
            ->where('external_id', '555001')
            ->first();

        $this->assertNotNull($sync);
        $this->assertSame('approved', $sync->status);
        $this->assertSame(250000, (int) $sync->amount_cents);
    }

    public function test_import_command_creates_payment_from_sync(): void
    {
        $team = Team::factory()->create();
        Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente',
            'code' => '111',
            'email' => 'payer@example.com',
        ]);

        PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '555001',
            'customer_id' => '111',
            'customer_email' => 'payer@example.com',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 250000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 250000,
            'description' => 'Pago test',
            'charge_created_at' => '2026-05-01 10:00:00',
            'last_synced_at' => now(),
            'raw_payload' => ['id' => 555001],
        ]);

        $this->artisan('payment-syncs:import-mercadopago', [
            '--team_id' => $team->id,
            '--fallback-email' => true,
        ])->assertSuccessful();

        $payment = Payment::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('source_provider', 'mercadopago')
            ->where('source_reference_id', '555001')
            ->first();

        $this->assertNotNull($payment);
        $this->assertSame(2500.0, (float) $payment->amount);
    }

    public function test_team_settings_mercadopago_page_loads(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($user)
            ->get(route('team-settings.edit', ['team' => $team, 'group' => 'mercadopago']))
            ->assertOk()
            ->assertSee('mercadopago_access_token', false);
    }
}
