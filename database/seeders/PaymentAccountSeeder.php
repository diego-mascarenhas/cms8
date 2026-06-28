<?php

namespace Database\Seeders;

use App\Models\PaymentAccount;
use App\Services\Finance\PaymentAccountCompatibilityService;
use Illuminate\Database\Seeder;

class PaymentAccountSeeder extends Seeder
{
    /**
     * @var list<array{code: string, name: string, currency_id: int, status: int, payment_type_ids: list<int>}>
     */
    private const PAYMENT_ACCOUNTS_TEMPLATE = [
        [
            'code' => 'BANK_EUR',
            'name' => 'Cuenta bancaria (EUR)',
            'currency_id' => 978,
            'status' => 1,
            'payment_type_ids' => [2, 11],
        ],
        [
            'code' => 'BANK_USD',
            'name' => 'Cuenta bancaria (USD)',
            'currency_id' => 840,
            'status' => 1,
            'payment_type_ids' => [2],
        ],
        [
            'code' => 'CASH',
            'name' => 'Caja',
            'currency_id' => 978,
            'status' => 1,
            'payment_type_ids' => [1],
        ],
        [
            'code' => 'STRIPE_EUR',
            'name' => 'Stripe (EUR)',
            'currency_id' => 978,
            'status' => 1,
            'payment_type_ids' => [6, 8],
        ],
        [
            'code' => 'STRIPE_USD',
            'name' => 'Stripe (USD)',
            'currency_id' => 840,
            'status' => 1,
            'payment_type_ids' => [6, 8],
        ],
        [
            'code' => 'PAYPAL_EUR',
            'name' => 'PayPal (EUR)',
            'currency_id' => 978,
            'status' => 1,
            'payment_type_ids' => [6, 7],
        ],
        [
            'code' => 'PAYPAL_USD',
            'name' => 'PayPal (USD)',
            'currency_id' => 840,
            'status' => 1,
            'payment_type_ids' => [6, 7],
        ],
        [
            'code' => 'WISE_EUR',
            'name' => 'Wise (EUR)',
            'currency_id' => 978,
            'status' => 1,
            'payment_type_ids' => [9],
        ],
        [
            'code' => 'WISE_USD',
            'name' => 'Wise (USD)',
            'currency_id' => 840,
            'status' => 1,
            'payment_type_ids' => [9],
        ],
        [
            'code' => 'MPAGO_ARS',
            'name' => 'Mercado Pago (ARS)',
            'currency_id' => 32,
            'status' => 1,
            'payment_type_ids' => [2, 12],
        ],
        [
            'code' => 'CUENTICA',
            'name' => 'Cuéntica',
            'currency_id' => 978,
            'status' => 1,
            'payment_type_ids' => [13],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teams = \App\Models\Team::all();

        if ($teams->isEmpty())
        {
            $this->command->warn('No teams found. Please run team seeders first.');

            return;
        }

        $compatibilityService = app(PaymentAccountCompatibilityService::class);

        foreach ($teams as $team)
        {
            $this->command->info("Creating payment accounts for team: {$team->name}");

            foreach (self::PAYMENT_ACCOUNTS_TEMPLATE as $accountData)
            {
                $paymentTypeIds = $accountData['payment_type_ids'];
                unset($accountData['payment_type_ids']);

                $paymentAccount = PaymentAccount::updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'code' => $accountData['code'],
                    ],
                    array_merge($accountData, ['team_id' => $team->id]),
                );

                $compatibilityService->syncConfiguredPaymentTypes($paymentAccount, $paymentTypeIds);
            }
        }

        $this->command->info('Payment accounts created successfully for all teams');
    }
}
