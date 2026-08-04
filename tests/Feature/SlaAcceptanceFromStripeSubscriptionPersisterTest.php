<?php

namespace Tests\Feature;

use App\Models\SLA;
use App\Models\SLAAcceptance;
use App\Models\SubscriptionProduct;
use App\Models\Team;
use App\Models\User;
use App\Services\Stripe\SlaAcceptanceFromStripeSubscriptionPersister;
use App\Services\Stripe\StripeSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SlaAcceptanceFromStripeSubscriptionPersisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_acceptance_with_token_and_date_and_stamps_stripe_metadata(): void
    {
        $user = User::factory()->create([
            'email' => 'rmartinez@rsalud.com.ar',
            'name' => 'MAAB S.A.',
        ]);
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'stripe_id' => 'cus_TWHThFQ4yaEICf',
            'name' => 'MAAB S.A.',
        ]);

        $product = SubscriptionProduct::query()->create([
            'stripe_id' => 'price_1Ttsy4RwN51ygFder9VMZz2Z',
            'stripe_product' => 'prod_UtgIO8dyGGbyQD',
            'stripe_price' => 'price_1Ttsy4RwN51ygFder9VMZz2Z',
            'name' => 'VPS FESS.ORG.AR y RSALUD.COM.AR',
            'active' => true,
            'category' => 'vps',
            'type' => 'hosting',
            'currency' => 'usd',
            'unit_amount' => 60,
            'recurring_interval' => 'month',
            'recurring_interval_count' => 1,
        ]);

        $sla = SLA::query()->create([
            'subscription_product_id' => $product->id,
            'title' => 'Condiciones del servicio (anual con pago mensual)',
            'content' => '<p>He leído y acepto las <a href="https://revisionalpha.com/terminos-y-condiciones">Condiciones Generales</a>.</p>',
            'version' => '1.0',
            'is_active' => true,
        ]);

        $token = '539eae66e85e98053f6266a90238e065b376e4201e868fec6576cc2f4b729654';

        $stripe = Mockery::mock(StripeSubscriptionService::class);
        $stripe->shouldReceive('updateMetadata')
            ->once()
            ->withArgs(function (string $stripeId, array $metadata) use ($token): bool
            {
                return $stripeId === 'sub_test_sla_001'
                    && ($metadata['sla_acceptance_token'] ?? null) === $token
                    && ! empty($metadata['sla_accepted_at'])
                    && ! empty($metadata['sla_acceptance_id']);
            })
            ->andReturn((object) ['id' => 'sub_test_sla_001']);

        $this->app->instance(StripeSubscriptionService::class, $stripe);

        $acceptance = app(SlaAcceptanceFromStripeSubscriptionPersister::class)
            ->persistFromStripeSubscription([
                'id' => 'sub_test_sla_001',
                'customer' => $team->stripe_id,
                'metadata' => [
                    'sla_acceptance_token' => $token,
                    'purpose' => 'vps_fess_rsalud_subscription',
                ],
                'items' => [
                    'data' => [
                        [
                            'price' => [
                                'id' => $product->stripe_price,
                                'product' => $product->stripe_product,
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertInstanceOf(SLAAcceptance::class, $acceptance);
        $this->assertSame($token, $acceptance->token);
        $this->assertSame($sla->id, $acceptance->sla_id);
        $this->assertNotNull($acceptance->accepted_at);
        $this->assertSame('rmartinez@rsalud.com.ar', $acceptance->accepted_by_email);
    }

    public function test_stamps_metadata_only_when_sla_product_missing(): void
    {
        $token = 'token-without-local-sla';

        $stripe = Mockery::mock(StripeSubscriptionService::class);
        $stripe->shouldReceive('updateMetadata')
            ->once()
            ->withArgs(function (string $stripeId, array $metadata) use ($token): bool
            {
                return $stripeId === 'sub_meta_only'
                    && ($metadata['sla_acceptance_token'] ?? null) === $token
                    && ! empty($metadata['sla_accepted_at']);
            })
            ->andReturn((object) ['id' => 'sub_meta_only']);

        $this->app->instance(StripeSubscriptionService::class, $stripe);

        $acceptance = app(SlaAcceptanceFromStripeSubscriptionPersister::class)
            ->persistFromStripeSubscription([
                'id' => 'sub_meta_only',
                'customer' => 'cus_unknown',
                'metadata' => [
                    'sla_acceptance_token' => $token,
                ],
                'items' => [
                    'data' => [
                        [
                            'price' => [
                                'id' => 'price_missing',
                                'product' => 'prod_missing',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertNull($acceptance);
        $this->assertDatabaseMissing('sla_acceptances', ['token' => $token]);
    }
}
