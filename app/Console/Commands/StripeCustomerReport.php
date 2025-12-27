<?php

namespace App\Console\Commands;

use App\Models\Team;
use Illuminate\Console\Command;
use Stripe\Customer;
use Stripe\Stripe;

class StripeCustomerReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:customer-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a report of Stripe customers and local teams';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Stripe::setApiKey(config('cashier.secret'));

        $this->info('📊 Generating Stripe Customer Report...');
        $this->newLine();

        try
        {
            // Get all Stripe customers
            $this->info('📡 Fetching customers from Stripe...');
            $customers = Customer::all(['limit' => 100]);

            $this->newLine();
            $this->info('🔍 Stripe Customers:');
            $this->newLine();

            $stripeData = [];
            foreach ($customers->autoPagingIterator() as $customer)
            {
                $stripeData[] = [
                    'Customer ID' => $customer->id,
                    'Name' => $customer->name ?? 'N/A',
                    'Email' => $customer->email ?? 'N/A',
                    'Created' => date('Y-m-d', $customer->created),
                ];
            }

            $this->table(
                ['Customer ID', 'Name', 'Email', 'Created'],
                $stripeData,
            );

            // Get all local teams
            $this->newLine();
            $this->info('💾 Local Teams:');
            $this->newLine();

            $teams = Team::with('owner')->get();
            $teamsData = [];
            foreach ($teams as $team)
            {
                $teamsData[] = [
                    'Team ID' => $team->id,
                    'Name' => $team->name,
                    'Owner Email' => $team->owner->email ?? 'N/A',
                    'Stripe ID' => $team->stripe_id ?? '❌ Not synced',
                ];
            }

            $this->table(
                ['Team ID', 'Name', 'Owner Email', 'Stripe ID'],
                $teamsData,
            );

            // Check for mismatches
            $this->newLine();
            $this->info('🔄 Sync Status:');
            $this->newLine();

            $synced = $teams->where('stripe_id', '!=', null)->count();
            $notSynced = $teams->where('stripe_id', null)->count();

            $this->line("✅ Synced teams: {$synced}");
            $this->line("❌ Not synced teams: {$notSynced}");
            $this->line("📊 Total Stripe customers: {$customers->count()}");

            return Command::SUCCESS;
        } catch (\Exception $e)
        {
            $this->error('❌ Error: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
