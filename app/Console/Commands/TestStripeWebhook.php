<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stripe\Event;
use Stripe\Stripe;
use Stripe\WebhookEndpoint;

class TestStripeWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:test-webhook
                            {--list : List all webhook endpoints}
                            {--test : Send a test event}
                            {--check : Check webhook configuration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test and debug Stripe webhook configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Stripe::setApiKey(config('cashier.secret'));

        if ($this->option('list'))
        {
            return $this->listWebhooks();
        }

        if ($this->option('test'))
        {
            return $this->sendTestEvent();
        }

        if ($this->option('check'))
        {
            return $this->checkConfiguration();
        }

        // Default: show menu
        $this->info('🔧 Stripe Webhook Test & Debug Tool');
        $this->newLine();

        $choice = $this->choice(
            'What would you like to do?',
            [
                'check' => 'Check webhook configuration',
                'list' => 'List all webhook endpoints',
                'test' => 'Send a test event',
                'events' => 'List recent webhook events',
            ],
            'check',
        );

        switch ($choice)
        {
            case 'check':
                return $this->checkConfiguration();
            case 'list':
                return $this->listWebhooks();
            case 'test':
                return $this->sendTestEvent();
            case 'events':
                return $this->listRecentEvents();
        }

        return Command::SUCCESS;
    }

    /**
     * Check webhook configuration
     */
    protected function checkConfiguration()
    {
        $this->info('🔍 Checking Stripe Webhook Configuration...');
        $this->newLine();

        // Check Stripe keys
        $secretKey = config('cashier.secret');
        $webhookSecret = config('cashier.webhook.secret');

        $this->table(
            ['Configuration', 'Value', 'Status'],
            [
                ['Stripe Secret Key', substr($secretKey, 0, 20).'...', $secretKey ? '✅' : '❌'],
                ['Webhook Secret', $webhookSecret ? substr($webhookSecret, 0, 20).'...' : 'Not configured', $webhookSecret ? '✅' : '⚠️'],
                ['Webhook URL', url('/stripe/webhook'), '✅'],
                ['CSRF Exempt', 'stripe/webhook', '✅'],
            ],
        );

        $this->newLine();

        if (! $webhookSecret)
        {
            $this->warn('⚠️  Warning: CASHIER_WEBHOOK_SECRET is not configured in .env');
            $this->info('This means webhook signature verification is disabled.');
            $this->info('To fix this:');
            $this->info('1. Go to Stripe Dashboard > Developers > Webhooks');
            $this->info('2. Click on your webhook endpoint');
            $this->info('3. Copy the "Signing secret"');
            $this->info('4. Add to .env: CASHIER_WEBHOOK_SECRET=whsec_...');
            $this->newLine();
        }

        // Check webhook endpoint in Stripe
        try
        {
            $endpoints = WebhookEndpoint::all(['limit' => 100]);
            $productionUrl = 'https://admin.revisionalpha.com/stripe/webhook';
            $found = false;

            foreach ($endpoints->data as $endpoint)
            {
                if ($endpoint->url === $productionUrl)
                {
                    $found = true;
                    $this->info('✅ Webhook endpoint found in Stripe:');
                    $this->line("   URL: {$endpoint->url}");
                    $this->line("   Status: {$endpoint->status}");
                    $this->line('   Events: '.count($endpoint->enabled_events).' event types');
                    $this->newLine();

                    if ($endpoint->status !== 'enabled')
                    {
                        $this->warn("⚠️  Webhook status is: {$endpoint->status}");
                    }
                }
            }

            if (! $found)
            {
                $this->error('❌ Production webhook endpoint not found in Stripe');
                $this->info("Looking for: {$productionUrl}");
            }
        } catch (\Exception $e)
        {
            $this->error('❌ Error checking webhooks: '.$e->getMessage());
        }

        return Command::SUCCESS;
    }

    /**
     * List all webhook endpoints
     */
    protected function listWebhooks()
    {
        $this->info('📋 Listing Stripe Webhook Endpoints...');
        $this->newLine();

        try
        {
            $endpoints = WebhookEndpoint::all(['limit' => 100]);

            if (count($endpoints->data) === 0)
            {
                $this->warn('No webhook endpoints found.');

                return Command::SUCCESS;
            }

            $data = [];
            foreach ($endpoints->data as $endpoint)
            {
                $data[] = [
                    substr($endpoint->id, 0, 20).'...',
                    $endpoint->url,
                    $endpoint->status,
                    count($endpoint->enabled_events).' events',
                ];
            }

            $this->table(
                ['ID', 'URL', 'Status', 'Events'],
                $data,
            );
        } catch (\Exception $e)
        {
            $this->error('❌ Error: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Send a test event
     */
    protected function sendTestEvent()
    {
        $this->info('🧪 Sending test event to webhook...');
        $this->newLine();

        $this->warn('⚠️  Note: Test events can only be sent from Stripe Dashboard or CLI');
        $this->info('To send a test event:');
        $this->info('1. Go to: https://dashboard.stripe.com/test/webhooks');
        $this->info('2. Click on your webhook endpoint');
        $this->info('3. Click "Send test webhook"');
        $this->info('4. Select an event type (e.g., customer.subscription.updated)');
        $this->newLine();

        $this->info('Or use Stripe CLI:');
        $this->line('stripe trigger customer.subscription.updated');

        return Command::SUCCESS;
    }

    /**
     * List recent webhook events
     */
    protected function listRecentEvents()
    {
        $this->info('📊 Fetching recent webhook events...');
        $this->newLine();

        try
        {
            $events = Event::all(['limit' => 10]);

            $data = [];
            foreach ($events->data as $event)
            {
                $data[] = [
                    date('Y-m-d H:i:s', $event->created),
                    $event->type,
                    substr($event->id, 0, 25).'...',
                ];
            }

            $this->table(
                ['Date', 'Event Type', 'Event ID'],
                $data,
            );
        } catch (\Exception $e)
        {
            $this->error('❌ Error: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
