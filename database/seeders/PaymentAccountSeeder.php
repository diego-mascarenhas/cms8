<?php

namespace Database\Seeders;

use App\Models\PaymentAccount;
use App\Services\Finance\PaymentAccountCompatibilityService;
use Illuminate\Database\Seeder;

class PaymentAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing teams
        $teams = \App\Models\Team::all();

        if ($teams->isEmpty())
        {
            $this->command->warn('No teams found. Please run team seeders first.');

            return;
        }

        $paymentAccountsTemplate = [
            [
                'code' => 'EUR',
                'name' => 'Cuenta Euro',
                'symbol' => '€',
                'currency_id' => 978,
                'status' => 1,
            ],
            [
                'code' => 'USD',
                'name' => 'Cuenta Dólar',
                'symbol' => '$',
                'currency_id' => 840,
                'status' => 1,
            ],
            [
                'code' => 'CASH',
                'name' => 'Caja',
                'symbol' => '€',
                'currency_id' => 978,
                'status' => 1,
            ],
            [
                'code' => 'PAYPAL',
                'name' => 'PayPal',
                'symbol' => '$',
                'currency_id' => 840,
                'status' => 1,
            ],
            [
                'code' => 'STRIPE',
                'name' => 'Stripe',
                'symbol' => '€',
                'currency_id' => 978,
                'status' => 1,
            ],
            [
                'code' => 'WISE_EUR',
                'name' => 'Wise (EUR)',
                'symbol' => '€',
                'currency_id' => 978,
                'status' => 1,
            ],
            [
                'code' => 'WISE_USD',
                'name' => 'Wise (USD)',
                'symbol' => '$',
                'currency_id' => 840,
                'status' => 1,
            ],
            [
                'code' => 'BIZUM',
                'name' => 'Bizum',
                'symbol' => '€',
                'currency_id' => 978,
                'status' => 1,
            ],
            [
                'code' => 'MERCADOPAGO',
                'name' => 'Mercado Pago',
                'symbol' => '$',
                'currency_id' => 840,
                'status' => 1,
            ],
            [
                'code' => 'CUENTICA',
                'name' => 'Cuéntica',
                'symbol' => '€',
                'currency_id' => 978,
                'status' => 1,
            ],
        ];

        // Create payment accounts for each team
        $compatibilityService = app(PaymentAccountCompatibilityService::class);

        foreach ($teams as $team)
        {
            $this->command->info("Creating payment accounts for team: {$team->name}");

            foreach ($paymentAccountsTemplate as $accountData)
            {
                $account = array_merge($accountData, ['team_id' => $team->id]);

                $paymentAccount = PaymentAccount::firstOrCreate(
                    [
                        'team_id' => $team->id,
                        'code' => $account['code'],
                    ],
                    $account,
                );

                $compatibilityService->syncDefaultPaymentTypesForAccount($paymentAccount);
            }
        }

        $this->command->info('Payment accounts created successfully for all teams');
    }
}
