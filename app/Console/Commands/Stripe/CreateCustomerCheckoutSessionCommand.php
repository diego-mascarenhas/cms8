<?php

namespace App\Console\Commands\Stripe;

use Illuminate\Console\Command;
use Stripe\StripeClient;

class CreateCustomerCheckoutSessionCommand extends Command
{
    protected $signature = 'stripe:customer-checkout
                            {customer : Stripe customer ID (cus_...)}
                            {price : Stripe price ID (price_...)}
                            {--promo= : Promotion code ID (promo_...), not the code string}
                            {--token= : sla_acceptance_token to store in metadata}
                            {--locale=es-419 : Checkout locale}
                            {--success-url= : Success URL (default: humano.revisionalpha.com with session_id)}
                            {--cancel-url=https://revisionalpha.com/}';

    protected $description = 'Create a live Checkout Session for an existing customer (prefills billing country from customer address, e.g. AR)';

    public function handle(): int
    {
        $secret = (string) config('cashier.secret');
        if ($secret === '' || str_starts_with($secret, 'sk_test_'))
        {
            $this->error('This command requires a live Stripe secret (sk_live_...). Local test keys cannot create sessions for live customers.');

            return self::FAILURE;
        }

        $customerId = (string) $this->argument('customer');
        $priceId = (string) $this->argument('price');
        $promoId = $this->option('promo');
        $token = (string) ($this->option('token') ?: '');

        $stripe = new StripeClient($secret);

        $customer = $stripe->customers->retrieve($customerId);
        $country = $customer->address->country ?? null;
        $this->info("Customer: {$customer->id} · {$customer->email} · country=".($country ?: 'null'));

        if ($country !== 'AR')
        {
            $stripe->customers->update($customerId, [
                'address' => array_filter([
                    'country' => 'AR',
                    'city' => $customer->address->city ?? null,
                    'line1' => $customer->address->line1 ?? null,
                    'line2' => $customer->address->line2 ?? null,
                    'postal_code' => $customer->address->postal_code ?? null,
                    'state' => $customer->address->state ?? null,
                ]),
                'preferred_locales' => ['es-419'],
            ]);
            $this->warn('Updated customer address.country to AR');
        }

        $metadata = array_filter([
            'billing_country' => 'AR',
            'purpose' => 'vps_fess_rsalud_subscription',
            'sla_acceptance_token' => $token !== '' ? $token : null,
            'customer_id' => $customerId,
        ]);

        $successUrl = (string) ($this->option('success-url') ?: 'https://humano.revisionalpha.com/?checkout=success&session_id={CHECKOUT_SESSION_ID}');

        $params = [
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'billing_address_collection' => 'required',
            'customer_update' => [
                'address' => 'auto',
                'name' => 'auto',
            ],
            'automatic_tax' => ['enabled' => true],
            'tax_id_collection' => ['enabled' => true],
            'locale' => (string) $this->option('locale'),
            'consent_collection' => [
                'terms_of_service' => 'required',
            ],
            'custom_text' => [
                'terms_of_service_acceptance' => [
                    'message' => 'He leído y acepto las [Condiciones Generales del Servicio](https://revisionalpha.com/terminos-y-condiciones), incluyendo la vigencia anual con facturación mensual.',
                ],
            ],
            'success_url' => $successUrl,
            'cancel_url' => (string) $this->option('cancel-url'),
            'metadata' => $metadata,
            'subscription_data' => [
                'metadata' => $metadata,
            ],
        ];

        if ($promoId)
        {
            $params['discounts'] = [['promotion_code' => $promoId]];
        } else
        {
            $params['allow_promotion_codes'] = true;
        }

        $session = $stripe->checkout->sessions->create($params);

        $this->newLine();
        $this->info('Checkout Session created (customer-bound → billing country from customer).');
        $this->line('id:  '.$session->id);
        $this->line('url: '.$session->url);

        return self::SUCCESS;
    }
}
