<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Billing\AssistantSubscriptionService;
use App\Services\Billing\TeamBillingDataService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiAssistantSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: \App\Models\User, 1: \App\Models\Team, 2: string}
     */
    private function assistantUserWithToken(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $this->seed([CountrySeeder::class, LanguageSeeder::class]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return [$user, $team, $user->createToken('assistant-billing-test')->plainTextToken];
    }

    public function test_subscription_summary_requires_authentication(): void
    {
        $this->getJson('/api/assistant/subscription')->assertStatus(401);
    }

    public function test_subscription_summary_returns_assistant_plan_without_stripe_customer(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/subscription');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.plan.id', 'assistant');
        $response->assertJsonPath('data.subscription', null);
        $response->assertJsonPath('data.payment_method', null);
        $response->assertJsonPath('data.payment_methods', []);
        $response->assertJsonPath('data.can_checkout', true);
        $this->assertSame($team->id, $team->fresh()->id);
        $this->assertIsArray($response->json('data.invoices'));
    }

    public function test_subscription_summary_includes_active_assistant_subscription(): void
    {
        [$user, $team, $token] = $this->assistantUserWithToken();
        $monthlyPrice = collect(config('humano_pricing.plans', []))
            ->firstWhere('id', 'assistant')['stripe_price_monthly_id'] ?? 'price_assistant_monthly';

        $team->subscriptions()->create([
            'user_id' => $user->id,
            'type' => 'assistant',
            'stripe_id' => 'sub_test_assistant_1',
            'stripe_status' => 'active',
            'stripe_price' => $monthlyPrice,
            'quantity' => 1,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/subscription');

        $response->assertOk();
        $response->assertJsonPath('data.subscription.active', true);
        $response->assertJsonPath('data.subscription.interval', 'monthly');
        $response->assertJsonPath('data.subscription.current_period_start', null);
        $response->assertJsonPath('data.subscription.current_period_end', null);
        $response->assertJsonPath('data.can_checkout', false);
    }

    public function test_checkout_requires_interval_and_return_urls(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/checkout', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['interval', 'success_url', 'cancel_url']);
    }

    public function test_checkout_rejects_active_assistant_subscription(): void
    {
        [$user, $team, $token] = $this->assistantUserWithToken();

        $team->subscriptions()->create([
            'user_id' => $user->id,
            'type' => 'assistant',
            'stripe_id' => 'sub_test_assistant_2',
            'stripe_status' => 'active',
            'stripe_price' => 'price_assistant_monthly',
            'quantity' => 1,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/checkout', [
                'interval' => 'monthly',
                'success_url' => 'https://assistant.test/profile',
                'cancel_url' => 'https://assistant.test/profile',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_checkout_returns_stripe_url_when_service_succeeds(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $this->mock(AssistantSubscriptionService::class, function ($mock)
        {
            $mock->shouldReceive('createCheckout')
                ->once()
                ->andReturn([
                    'success' => true,
                    'url' => 'https://checkout.stripe.com/c/pay/cs_test_assistant',
                ]);
        });

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/checkout', [
                'interval' => 'yearly',
                'success_url' => 'https://assistant.test/profile',
                'cancel_url' => 'https://assistant.test/profile',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('url', 'https://checkout.stripe.com/c/pay/cs_test_assistant');
    }

    public function test_complete_checkout_requires_session_id(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/checkout/complete', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['session_id']);
    }

    public function test_billing_update_copies_whatsapp_to_user_phone(): void
    {
        [$user, $team, $token] = $this->assistantUserWithToken();

        $this->mock(TeamBillingDataService::class, function ($mock) use ($team)
        {
            $mock->shouldReceive('updateBilling')
                ->once()
                ->andReturn([
                    'success' => true,
                    'message' => 'Datos de facturación actualizados correctamente.',
                ]);
            $mock->shouldReceive('buildPayload')
                ->once()
                ->andReturn([
                    'team' => [
                        'id' => $team->id,
                        'name' => $team->name,
                    ],
                    'billing' => [
                        'has_stripe_customer' => true,
                        'individual_name' => 'Diego Mascarenhas',
                        'business_name' => '',
                        'country' => 'US',
                        'phone' => '+54 34722372858',
                        'tax_id' => '123456789',
                    ],
                ]);
        });

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/billing', [
                'individual_name' => 'Diego Mascarenhas',
                'country' => 'US',
                'phone' => '+54 34722372858',
                'tax_id' => '123456789',
            ]);

        $response->assertOk();
        $this->assertSame('5434722372858', (string) $user->fresh()->phone);
    }
}
