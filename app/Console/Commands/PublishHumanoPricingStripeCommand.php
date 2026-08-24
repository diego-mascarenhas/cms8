<?php

namespace App\Console\Commands;

use App\Services\Billing\HumanoPricingStripePublisher;
use Illuminate\Console\Command;
use Stripe\StripeClient;

class PublishHumanoPricingStripeCommand extends Command
{
    protected $signature = 'humano-pricing:publish-stripe
                            {--live : Use the commented sk_live_ STRIPE_SECRET in .env}
                            {--dry-run : Show what would be created without calling Stripe}';

    protected $description = 'Create missing Assistant, Shop, Ads and Projects products and prices in Stripe';

    public function handle(): int
    {
        $secret = $this->option('live')
            ? $this->liveSecretFromEnvFile()
            : (string) config('cashier.secret');

        if ($secret === '')
        {
            $this->error('No Stripe secret available.');

            return self::FAILURE;
        }

        if ($this->option('live') && ! str_starts_with($secret, 'sk_live_'))
        {
            $this->error('The --live flag needs a sk_live_ key. Add a commented STRIPE_SECRET=sk_live_... line to .env.');

            return self::FAILURE;
        }

        if (! $this->option('live') && str_starts_with($secret, 'sk_live_'))
        {
            $this->error('Active STRIPE_SECRET is live. Run without mixing modes, or use --live explicitly.');

            return self::FAILURE;
        }

        $mode = str_starts_with($secret, 'sk_live_') ? 'live' : 'test';
        $this->info('Publishing humano_pricing app products to Stripe '.$mode.' (key '.substr($secret, 0, 8).'…).');

        $publisher = new HumanoPricingStripePublisher(new StripeClient($secret));
        $results = $publisher->publish((bool) $this->option('dry-run'));

        if ($results === [])
        {
            $this->warn('No paid Shop/Ads/Projects plans found in humano_pricing.');

            return self::SUCCESS;
        }

        $this->table(
            ['Plan', 'Name', 'Action', 'Product', 'Price', 'Amount'],
            array_map(function (array $row): array
            {
                return [
                    $row['plan_id'],
                    $row['name'],
                    $row['created'] ? 'create' : 'reuse',
                    $row['product_id'],
                    $row['price_id'],
                    $row['amount'].' '.strtoupper($row['currency']),
                ];
            }, $results),
        );

        if ($this->option('dry-run'))
        {
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Env keys for '.$mode.':');
        foreach ($results as $row)
        {
            $prefix = $this->envPrefix($row['plan_id']);
            $this->line($prefix.'_STRIPE_PRODUCT_ID='.$row['product_id']);
            $this->line($prefix.'_PRICE_MONTHLY_ID='.$row['price_id']);
            if (($row['yearly_price_id'] ?? '') !== '')
            {
                $this->line($prefix.'_PRICE_YEARLY_ID='.$row['yearly_price_id']);
            }
        }

        return self::SUCCESS;
    }

    private function envPrefix(string $planId): string
    {
        return match ($planId)
        {
            'shop_basic' => 'HUMANO_PRICING_SHOP_BASIC',
            'shop_premium' => 'HUMANO_PRICING_SHOP_PREMIUM',
            'shop_profesional' => 'HUMANO_PRICING_SHOP_PROFESIONAL',
            'ads' => 'HUMANO_PRICING_ADS',
            'projects' => 'HUMANO_PRICING_PROJECTS',
            default => 'HUMANO_PRICING_'.strtoupper($planId),
        };
    }

    private function liveSecretFromEnvFile(): string
    {
        $path = base_path('.env');
        if (! is_readable($path))
        {
            return '';
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line)
        {
            $line = trim($line);
            if (! str_starts_with($line, '#') || ! str_contains($line, 'STRIPE_SECRET='))
            {
                continue;
            }

            $value = trim((string) preg_replace('/^#\s*STRIPE_SECRET=/', '', $line), " \t\"'");
            if (str_starts_with($value, 'sk_live_'))
            {
                return $value;
            }
        }

        return '';
    }
}
