<?php

namespace Database\Seeders;

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
                'currency_id' => 978, // EUR currency ID
                'status' => 1,
            ],
            [
                'code' => 'USD',
                'name' => 'Cuenta Dólar',
                'symbol' => '$',
                'currency_id' => 840, // USD currency ID
                'status' => 1,
            ],
            [
                'code' => 'PAYPAL',
                'name' => 'PayPal',
                'symbol' => '$',
                'currency_id' => 840, // USD currency ID
                'status' => 1,
            ],
            [
                'code' => 'STRIPE',
                'name' => 'Stripe',
                'symbol' => '€',
                'currency_id' => 978, // EUR currency ID
                'status' => 1,
            ],
        ];

        // Create payment accounts for each team
        foreach ($teams as $team)
        {
            $this->command->info("Creating payment accounts for team: {$team->name}");

            foreach ($paymentAccountsTemplate as $accountData)
            {
                $account = array_merge($accountData, ['team_id' => $team->id]);

                \App\Models\PaymentAccount::firstOrCreate(
                    [
                        'team_id' => $team->id,
                        'code' => $account['code'],
                    ],
                    $account,
                );
            }
        }

        $this->command->info('Payment accounts created successfully for all teams');
    }
}
