<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class WooCommerceCredentialsSeeder extends Seeder
{
    /**
     * Seed WooCommerce credentials from .env into the first team (or the team named "Revision Alpha").
     * Add to your .env:
     *   WOOCOMMERCE_URL=https://wordpress.revisionalpha.net
     *   WOOCOMMERCE_CONSUMER_KEY=ck_...
     *   WOOCOMMERCE_CONSUMER_SECRET=cs_...
     * Then run: php artisan db:seed --class=WooCommerceCredentialsSeeder
     */
    public function run(): void
    {
        $url = config('services.woocommerce.url') ?? '';
        $key = config('services.woocommerce.consumer_key') ?? '';
        $secret = config('services.woocommerce.consumer_secret') ?? '';

        if ($url === '' || $key === '' || $secret === '')
        {
            $this->command?->warn('WooCommerce credentials not set. Add WOOCOMMERCE_URL, WOOCOMMERCE_CONSUMER_KEY and WOOCOMMERCE_CONSUMER_SECRET to .env and register them in config/services.php, then run this seeder again.');

            return;
        }

        $team = Team::where('name', 'Revision Alpha')->first() ?? Team::query()->first();
        if (! $team)
        {
            $this->command?->error('No team found. Create a team first.');

            return;
        }

        $team->setSetting('woocommerce_url', $url);
        $team->setSetting('woocommerce_consumer_key', $key);
        $team->setSetting('woocommerce_consumer_secret', $secret, ['is_encrypted' => true]);
        $team->setSetting('woocommerce_api_version', 'wc/v3');
        $team->setSetting('woocommerce_verify_ssl', '1');

        $this->command?->info('WooCommerce credentials saved for team: '.$team->name);
    }
}
