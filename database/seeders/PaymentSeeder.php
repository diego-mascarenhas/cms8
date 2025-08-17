<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing teams and their payment accounts
        $teams = \App\Models\Team::with('paymentAccounts')->get();

        if ($teams->isEmpty()) {
            $this->command->warn('No teams found. Please run team seeders first.');
            return;
        }

        foreach ($teams as $team) {
            if ($team->paymentAccounts->isEmpty()) {
                $this->command->warn("No payment accounts found for team: {$team->name}");
                continue;
            }

            $this->command->info("Creating sample payments for team: {$team->name}");

            // Create some sample payments
            for ($i = 0; $i < 10; $i++) {
                $account = $team->paymentAccounts->random();
                $transactionType = rand(0, 1) ? 'I' : 'E'; // Random Income or Expense

                \App\Models\Payment::create([
                    'team_id' => $team->id,
                    'enterprise_id' => null, // No enterprises yet
                    'transaction_type' => $transactionType,
                    'date' => now()->subDays(rand(0, 90)), // Random date in last 90 days
                    'invoice_id' => null, // No invoices yet
                    'account_id' => $account->id,
                    'type_id' => 1, // Assuming payment type 1 exists
                    'amount' => rand(100, 5000) / 100, // Random amount between 1.00 and 50.00
                    'remarks' => 'Sample payment ' . ($i + 1),
                    'status' => rand(1, 2), // Random status: 1 = In Process, 2 = Approved
                ]);
            }
        }

        $this->command->info('Sample payments created successfully for all teams');
    }
}
