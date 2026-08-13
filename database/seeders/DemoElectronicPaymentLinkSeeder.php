<?php

namespace Database\Seeders;

use App\Models\Enterprise;
use App\Models\EnterpriseBillingAddress;
use App\Models\EnterpriseTaxStatusType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceType;
use App\Models\PaymentAccount;
use App\Models\PaymentSync;
use App\Models\Team;
use App\Services\Finance\PaymentAccountCompatibilityService;
use Illuminate\Database\Seeder;

/**
 * Unpaid ARS invoice + Mercado Pago syncs so team owners can try
 * "Vincular pago electrónico" after a fresh seed (Revision Alpha has no import).
 */
class DemoElectronicPaymentLinkSeeder extends Seeder
{
    public const INVOICE_NUMBER = 'DEMO-MP-LINK';

    public const ENTERPRISE_CODE = 'DEMO-MP-CLI';

    /**
     * @var list<string>
     */
    private const TEAM_NAMES = [
        'Demo',
        "REVISION ALPHA's Team",
        "Humano's Team",
    ];

    public function run(): void
    {
        if (EnterpriseTaxStatusType::query()->doesntExist())
        {
            $this->call(EnterpriseTaxStatusTypeSeeder::class);
        }

        $teams = Team::query()->whereIn('name', self::TEAM_NAMES)->orderBy('id')->get();

        if ($teams->isEmpty())
        {
            $this->command?->warn('DemoElectronicPaymentLinkSeeder: no Demo / Revision Alpha / Humano team — skip.');

            return;
        }

        foreach ($teams as $team)
        {
            $this->seedForTeam($team);
        }
    }

    private function seedForTeam(Team $team): void
    {
        $team->enableModule('invoices');
        $team->enableModule('payments');

        $this->ensureMercadoPagoAccount((int) $team->id);

        $enterprise = Enterprise::withoutGlobalScopes()->firstOrCreate(
            ['team_id' => $team->id, 'code' => self::ENTERPRISE_CODE],
            [
                'name' => 'Nunca Indiva',
                'type_id' => 1,
                'status_id' => 1,
                'email' => 'facturacion@nunca-indiva.demo',
                'phone' => '541112345678',
            ],
        );

        $billing = EnterpriseBillingAddress::firstOrCreate(
            ['enterprise_id' => $enterprise->id],
            [
                'name' => $enterprise->name,
                'identification_number' => '27273118484',
                'tax_status_type_id' => EnterpriseTaxStatusType::query()->value('id') ?? 1,
                'address' => 'Av. Corrientes 1234',
                'postal_code' => 'C1043AAZ',
                'locality' => 'CABA',
                'province' => 'Buenos Aires',
                'country' => 'AR',
                'status' => 1,
            ],
        );

        $total = 15919.00;

        $invoice = Invoice::withoutGlobalScopes()->updateOrCreate(
            ['team_id' => $team->id, 'number' => self::INVOICE_NUMBER],
            [
                'enterprise_id' => $enterprise->id,
                'billing_id' => $billing->id,
                'type_id' => InvoiceType::query()->value('id') ?? 1,
                'operation' => 'sell',
                'date' => now()->toDateString(),
                'due_date' => now()->addDays(15)->toDateString(),
                'gross_amount' => 13156.20,
                'discount' => 0,
                'total_amount' => $total,
                'balance' => $total,
                'status' => 2,
                'currency_id' => 32,
            ],
        );

        InvoiceItem::query()->updateOrCreate(
            ['invoice_id' => $invoice->id, 'description' => 'Hosting y mantenimiento'],
            [
                'quantity' => 1,
                'unit_price' => 13156.20,
                'discount' => 0,
                'tax_percentage' => 21,
            ],
        );

        foreach ($this->paymentSyncFixtures() as $index => $fixture)
        {
            PaymentSync::query()->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'provider' => 'mercadopago',
                    'external_id' => 'demo-mp-'.$team->id.'-'.$index,
                ],
                [
                    'status' => 'approved',
                    'currency' => 'ARS',
                    'amount_cents' => $fixture['amount_cents'],
                    'amount_refunded_cents' => 0,
                    'amount_net_cents' => $fixture['amount_cents'],
                    'charge_created_at' => $fixture['charged_at'],
                    'last_synced_at' => now(),
                    'raw_payload' => $fixture['payload'],
                ],
            );
        }

        $this->command?->info(sprintf(
            '✅ Electronic payment demo: invoice %s (id %d) + %d Mercado Pago syncs for %s',
            self::INVOICE_NUMBER,
            $invoice->id,
            count($this->paymentSyncFixtures()),
            $team->name,
        ));
    }

    private function ensureMercadoPagoAccount(int $teamId): void
    {
        $account = PaymentAccount::withoutGlobalScopes()->updateOrCreate(
            [
                'team_id' => $teamId,
                'code' => 'MPAGO_ARS',
            ],
            [
                'name' => 'Mercado Pago (ARS)',
                'currency_id' => 32,
                'status' => 1,
            ],
        );

        app(PaymentAccountCompatibilityService::class)->syncConfiguredPaymentTypes($account, [2, 12]);
    }

    /**
     * Inserted in non-chronological order on purpose; the invoice selector sorts by date desc.
     *
     * @return list<array{charged_at: string, amount_cents: int, payload: array<string, mixed>}>
     */
    private function paymentSyncFixtures(): array
    {
        return [
            $this->syncFixture('2026-07-16', 1591900, 'Nunca Indiva', '27273118484', 'L18MKX9RX6JO40XO9O6WYV'),
            $this->syncFixture('2026-03-10', 1926184, 'Carla Barletta', '20421566217', 'REF-MAR-10'),
            $this->syncFixture('2026-08-07', 880000, 'Hygeia Sa', '30712345678', 'REF-AUG-07'),
            $this->syncFixture('2026-08-10', 2450000, 'Estudio Norte', '30700011223', 'REF-AUG-10'),
            $this->syncFixture('2026-04-06', 1591888, 'Hygeia Sa', '30712345678', 'L18MKX9RX6JO40XO9O6WYV'),
            $this->syncFixture('2026-03-12', 450000, 'Nunca Indiva', '27273118484', 'REF-MAR-12'),
            $this->syncFixture('2026-03-09', 1250000, 'Laura Sánchez', '27333444555', 'REF-MAR-09'),
            $this->syncFixture('2026-02-12', 310000, 'Mobile Apps Studio', '30799888776', 'REF-FEB-12'),
        ];
    }

    /**
     * @return array{charged_at: string, amount_cents: int, payload: array<string, mixed>}
     */
    private function syncFixture(
        string $date,
        int $amountCents,
        string $payerName,
        string $cuil,
        string $transactionId,
    ): array {
        return [
            'charged_at' => $date.' 12:00:00',
            'amount_cents' => $amountCents,
            'payload' => [
                'operation_type' => 'regular_payment',
                'collector_id' => '616106613',
                'payer' => [
                    'id' => '291110986',
                    'email' => strtolower(str_replace(' ', '.', $payerName)).'@demo.test',
                    'identification' => [
                        'type' => 'CUIL',
                        'number' => $cuil,
                    ],
                ],
                'transaction_details' => [
                    'transaction_id' => $transactionId,
                ],
                'settlement_payer' => [
                    'name' => $payerName,
                    'id_type' => 'CUIL',
                    'id_number' => $cuil,
                ],
            ],
        ];
    }
}
