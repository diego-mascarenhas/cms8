<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Stripe\Stripe;
use Stripe\Subscription;

class SuspendOverdueAccounts extends Command
{
    protected $signature = 'stripe:suspend-overdue';
    protected $description = 'Suspend cPanel accounts for past due subscriptions';

    private $cpanelUrl;
    private $cpanelUsername;
    private $cpanelApiToken;

    public function __construct()
    {
        parent::__construct();
        $this->cpanelUrl = env('CPANEL_URL');
        $this->cpanelUsername = env('CPANEL_USERNAME');
        $this->cpanelApiToken = env('CPANEL_API_TOKEN');
    }

    public function handle()
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            $subscriptions = Subscription::all([
                'status' => 'past_due',
                'limit' => 100,
                'expand' => ['data.customer'],
            ]);

            if ($subscriptions->count() === 0) {
                $this->info('No past due subscriptions found');

                return;
            }

            $this->info('Found ' . $subscriptions->count() . ' past due subscriptions');

            foreach ($subscriptions as $subscription) {
                $cpanelUsername = $subscription->metadata->cpanel_username ?? null;

                if (! $cpanelUsername) {
                    $this->warn('No cPanel username found in metadata for subscription: ' . $subscription->id);
                    continue;
                }

                $this->suspendCpanelAccount($cpanelUsername, $subscription);
            }

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->error('Error Type: ' . get_class($e));
        }
    }

    private function suspendCpanelAccount($username, $subscription)
    {
        $this->info('Suspending cPanel account for user: ' . $username);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'whm ' . $this->cpanelUsername . ':' . $this->cpanelApiToken,
            ])
                ->withOptions([
                    'verify' => false,
                ])
                ->get($this->cpanelUrl . '/json-api/suspendacct', [
                    'user' => $username,
                    'reason' => 'Payment overdue for subscription ' . $subscription->id,
                ]);

            if ($response->successful()) {
                $this->info('Successfully suspended account: ' . $username);

                $subscription->metadata['suspended_at'] = time();
                $subscription->metadata['suspension_reason'] = 'past_due';
                $subscription->save();
            } else {
                $this->error('Failed to suspend account: ' . $username);
                $this->error('cPanel API Response: ' . $response->body());
            }

        } catch (\Exception $e) {
            $this->error("Error suspending account {$username}: " . $e->getMessage());
        }
    }
}
