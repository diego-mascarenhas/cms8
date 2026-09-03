<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Enterprise;
use App\Models\MailerUsageLog;
use App\Models\MessageDelivery;
use App\Models\Project;
use App\Models\TokenUsageLog;
use App\Models\User;
use App\Services\Billing\AssistantSubscriptionService;
use App\Services\Billing\TeamBillingDataService;
use Carbon\Carbon;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Jetstream\Features;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiAssistantSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openrouter.cache_store' => 'array']);
        Http::fake([
            'https://openrouter.ai/api/v1/models' => Http::response(['data' => []], 200),
        ]);
    }

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
        $response->assertJsonPath('data.token_usage.total_calls', 0);
        $response->assertJsonPath('data.token_usage.total_tokens_used', 0);
        $response->assertJsonPath('data.token_usage.amount_due_cents', 0);
        $response->assertJsonPath('data.token_usage.currency', 'EUR');
        $this->assertNotEmpty($response->json('data.token_usage.period_start'));
        $this->assertNotEmpty($response->json('data.token_usage.period_end'));
        $this->assertIsArray($response->json('data.token_usage.by_module'));
        $response->assertJsonPath('data.whatsapp_usage.messages_sent', 0);
        $response->assertJsonPath('data.whatsapp_usage.our_amount_cents', 0);
        $response->assertJsonPath('data.whatsapp_usage.amount_due_cents', 0);
        $response->assertJsonPath('data.whatsapp_usage.currency', 'EUR');
        $this->assertSame(0.003, $response->json('data.whatsapp_usage.our_rate'));
        $this->assertSame(0.005, $response->json('data.whatsapp_usage.reference_rate'));
    }

    public function test_estimator_catalog_is_free_and_ready_for_token_billing(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/subscription?catalog=estimator');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.plan.id', 'estimator');
        $response->assertJsonPath('data.plan.monthly_amount', '0');
        $response->assertJsonPath('data.plan.yearly_amount', '0');
        $response->assertJsonPath('data.plan.checkout_available', true);
        $response->assertJsonPath('data.can_checkout', true);
        $response->assertJsonPath('data.subscription', null);
        $response->assertJsonPath('data.token_usage.amount_due_cents', 0);
        $response->assertJsonPath('data.estimator_usage.emails_sent', 0);
    }

    public function test_estimator_usage_counts_sent_quote_emails(): void
    {
        [$user, $team, $token] = $this->assistantUserWithToken();
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ProjectStatusSeeder::class,
        ]);

        $enterprise = Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($team->id)->create([
            'type_id' => 1,
            'status_id' => 1,
            'payment_type_id' => null,
            'invoice_type_id' => null,
        ]));

        Project::withoutEvents(fn () => Project::factory()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'responsible_id' => $user->id,
            'status_id' => 2,
            'data' => [
                'budget_email' => [
                    'sent_at' => now()->toIso8601String(),
                ],
            ],
        ]));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/subscription?catalog=estimator')
            ->assertOk()
            ->assertJsonPath('data.estimator_usage.emails_sent', 1);
    }

    public function test_estimator_checkout_does_not_require_a_stripe_price(): void
    {
        [, $team] = $this->assistantUserWithToken();

        $result = app(AssistantSubscriptionService::class)->createCheckout(
            $team,
            'monthly',
            'https://estimator.test/profile',
            'https://estimator.test/profile',
            'estimator',
        );

        if ($result['success'])
        {
            $this->assertNotEmpty($result['url'] ?? null);

            return;
        }

        $this->assertStringNotContainsString(
            'precio de Stripe configurado',
            (string) ($result['message'] ?? ''),
        );
    }

    public function test_innovation_catalog_is_free_and_ready_for_token_billing(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/subscription?catalog=innovation');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.plan.id', 'fanyion');
        $response->assertJsonPath('data.plan.monthly_amount', '0');
        $response->assertJsonPath('data.plan.yearly_amount', '0');
        $response->assertJsonPath('data.plan.checkout_available', true);
        $response->assertJsonPath('data.can_checkout', true);
        $response->assertJsonPath('data.subscription', null);
        $response->assertJsonPath('data.token_usage.amount_due_cents', 0);
        $response->assertJsonPath('data.mailer_usage.emails_sent', 0);
        $response->assertJsonPath('data.mailer_usage.amount_due_cents', 0);
        $this->assertSame(0.002, $response->json('data.mailer_usage.our_rate'));
    }

    public function test_innovation_catalog_bills_sent_emails_in_current_period(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();
        config(['emailer.payg.price_per_email' => 0.01]);

        MessageDelivery::create([
            'team_id' => $team->id,
            'message_id' => 1,
            'contact_id' => 1,
            'sent_at' => now(),
            'status_id' => 2,
            'delivery_status' => 'sent',
        ]);
        MessageDelivery::create([
            'team_id' => $team->id,
            'message_id' => 1,
            'contact_id' => 2,
            'sent_at' => now(),
            'status_id' => 2,
            'delivery_status' => 'sent',
        ]);
        MessageDelivery::create([
            'team_id' => $team->id,
            'message_id' => 1,
            'contact_id' => 3,
            'sent_at' => now()->subMonth(),
            'status_id' => 2,
            'delivery_status' => 'sent',
        ]);
        MailerUsageLog::factory()->create([
            'team_id' => $team->id,
            'source' => 'fanyion',
            'count' => 2,
            'sent_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/subscription?catalog=innovation');

        $response->assertOk();
        $response->assertJsonPath('data.mailer_usage.emails_sent', 5);
        $response->assertJsonPath('data.mailer_usage.period_emails_sent', 5);
        $response->assertJsonPath('data.mailer_usage.amount_due_cents', 5);
        $this->assertSame(0.01, $response->json('data.mailer_usage.our_rate'));
    }

    public function test_fanyion_checkout_does_not_require_a_stripe_price(): void
    {
        [, $team] = $this->assistantUserWithToken();

        $result = app(AssistantSubscriptionService::class)->createCheckout(
            $team,
            'monthly',
            'https://fanyion.test/admin/plan-and-billing',
            'https://fanyion.test/admin/plan-and-billing',
            'fanyion',
        );

        if ($result['success'])
        {
            $this->assertNotEmpty($result['url'] ?? null);

            return;
        }

        $this->assertStringNotContainsString(
            'precio de Stripe configurado',
            (string) ($result['message'] ?? ''),
        );
    }

    public function test_subscription_usage_starts_at_first_use_not_trial_clock(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();
        $team->setSetting(
            AssistantSubscriptionService::trialStartedSettingKey('assistant'),
            now()->subHours(12)->toIso8601String(),
        );

        $firstUse = TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => null,
            'service' => 'PromptController',
            'json_size' => 10,
            'toon_size' => 0,
            'json_tokens' => 1_000_000,
            'toon_tokens' => 0,
            'savings_percentage' => 0,
            'used_toon' => false,
        ]);
        $firstUse->forceFill(['created_at' => now()->subDays(10)])->save();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/subscription');

        $response->assertOk();
        $response->assertJsonPath('data.token_usage.total_tokens_used', 10_000_000);
        $this->assertEqualsWithDelta(
            $firstUse->fresh()->created_at->timestamp,
            Carbon::parse($response->json('data.token_usage.period_start'))->timestamp,
            5,
        );
    }

    public function test_paid_cycle_treats_pre_plan_token_usage_as_cac(): void
    {
        [, $team] = $this->assistantUserWithToken();

        $firstUse = TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => null,
            'service' => 'PromptController',
            'json_size' => 10,
            'toon_size' => 0,
            'json_tokens' => 1_000_000,
            'toon_tokens' => 0,
            'savings_percentage' => 0,
            'used_toon' => false,
        ]);
        $firstUse->forceFill(['created_at' => now()->subDays(10)])->save();

        $periodStart = now()->subHour();
        $method = new ReflectionMethod(AssistantSubscriptionService::class, 'tokenUsagePeriod');
        [$from] = $method->invoke(
            app(AssistantSubscriptionService::class),
            $team,
            [
                'current_period_start' => $periodStart->toIso8601String(),
                'current_period_end' => now()->addMonth()->toIso8601String(),
            ],
        );

        $this->assertEqualsWithDelta($periodStart->timestamp, $from->timestamp, 2);
        $this->assertGreaterThan($firstUse->fresh()->created_at->timestamp, $from->timestamp);
    }

    public function test_subscription_token_usage_bills_tokens_used_in_current_period(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();
        TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => null,
            'service' => 'PromptController',
            'json_size' => 10,
            'toon_size' => 0,
            'json_tokens' => 1_000_000,
            'toon_tokens' => 0,
            'savings_percentage' => 0,
            'used_toon' => false,
        ]);

        $previousPeriod = TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => null,
            'service' => 'PromptController',
            'json_size' => 10,
            'toon_size' => 0,
            'json_tokens' => 1_000_000,
            'toon_tokens' => 0,
            'savings_percentage' => 0,
            'used_toon' => false,
        ]);
        $previousPeriod->forceFill(['created_at' => now()->subMonth()])->save();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/subscription');

        $response->assertOk();
        $response->assertJsonPath('data.token_usage.total_tokens_used', 20_000_000);
        $response->assertJsonPath('data.token_usage.total_calls', 2);
        $response->assertJsonPath('data.token_usage.amount_due_cents', 2000);
        $response->assertJsonPath('data.token_usage.currency', 'EUR');
    }

    public function test_subscription_token_usage_bills_catalog_priced_whatsapp_models(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();
        $teamNumber = '34999000111';
        $team->setSetting('whatsapp_from', $teamNumber);

        Conversation::create([
            'message_sid' => 'SM_sub_usage_haiku',
            'channel' => 'whatsapp',
            'from' => $teamNumber,
            'to' => '34600111222',
            'body' => 'Respuesta automática',
            'status' => 'sent',
            'direction' => 'outbound',
            'metadata' => [
                'token_usage' => [
                    'prompt_tokens' => 1_000_000,
                    'completion_tokens' => 0,
                    'total_tokens' => 1_000_000,
                    'model' => 'claude-haiku-4-5',
                ],
            ],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/subscription');

        $response->assertOk();
        $response->assertJsonPath('data.token_usage.total_tokens_used', 10_000_000);
        $response->assertJsonPath('data.token_usage.amount_due_cents', 1000);
        $this->assertSame(
            (int) collect($response->json('data.token_usage.by_module'))->sum('tokens_used'),
            $response->json('data.token_usage.total_tokens_used'),
        );
    }

    public function test_subscription_token_modules_match_usage_sources(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();
        $teamNumber = '34999000111';
        $team->setSetting('whatsapp_from', $teamNumber);

        Conversation::create([
            'message_sid' => 'SM_sub_usage_align',
            'channel' => 'whatsapp',
            'from' => $teamNumber,
            'to' => '34600111222',
            'body' => 'Respuesta automática',
            'status' => 'sent',
            'direction' => 'outbound',
            'metadata' => [
                'token_usage' => [
                    'prompt_tokens' => 1_000_000,
                    'completion_tokens' => 0,
                    'total_tokens' => 1_000_000,
                    'model' => 'claude-haiku-4-5',
                ],
            ],
        ]);

        $usage = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/usage')
            ->assertOk();
        $subscription = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/subscription')
            ->assertOk();

        $this->assertSame(
            $usage->json('data.all.tokens'),
            $subscription->json('data.token_usage.total_tokens_used'),
        );
        $this->assertSame(
            $usage->json('data.all.amount_cents'),
            $subscription->json('data.token_usage.amount_due_cents'),
        );
        $this->assertSame(
            collect($usage->json('data.sources'))->pluck('tokens_used', 'module_name')->all(),
            collect($subscription->json('data.token_usage.by_module'))->pluck('tokens_used', 'module_name')->all(),
        );
    }

    public function test_subscription_whatsapp_usage_bills_outbound_messages_in_current_period(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();
        config([
            'humano_pricing.whatsapp_message_billing.our_amount' => 0.10,
            'humano_pricing.whatsapp_message_billing.reference_amount' => 0.25,
        ]);

        $teamNumber = '34999000111';
        $team->setSetting('whatsapp_from', $teamNumber);

        Conversation::create([
            'message_sid' => 'SM_wa_bill_1',
            'channel' => 'whatsapp',
            'from' => $teamNumber,
            'to' => '34600111222',
            'body' => 'Hola',
            'status' => 'sent',
            'direction' => 'outbound',
        ]);
        Conversation::create([
            'message_sid' => 'SM_wa_bill_2',
            'channel' => 'whatsapp',
            'from' => $teamNumber,
            'to' => '34600111222',
            'body' => 'Hola',
            'status' => 'delivered',
            'direction' => 'outbound',
        ]);

        $previous = Conversation::create([
            'message_sid' => 'SM_wa_bill_old',
            'channel' => 'whatsapp',
            'from' => $teamNumber,
            'to' => '34600111222',
            'body' => 'Hola',
            'status' => 'sent',
            'direction' => 'outbound',
        ]);
        $previous->forceFill(['created_at' => now()->subMonth()])->save();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/subscription');

        $response->assertOk();
        $response->assertJsonPath('data.whatsapp_usage.messages_sent', 3);
        $response->assertJsonPath('data.whatsapp_usage.our_amount_cents', 30);
        $response->assertJsonPath('data.whatsapp_usage.saved_amount_cents', 45);
        $response->assertJsonPath('data.whatsapp_usage.amount_due_cents', 30);
        $response->assertJsonPath('data.whatsapp_usage.period_messages_sent', 3);
        $response->assertJsonPath('data.whatsapp_usage.average_savings', 60);
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
        $response->assertJsonPath('data.subscription_code', 'sub_test_assistant_1');
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

    public function test_checkout_returns_subscription_data_when_card_is_already_on_file(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $this->mock(AssistantSubscriptionService::class, function ($mock)
        {
            $mock->shouldReceive('createCheckout')
                ->once()
                ->andReturn([
                    'success' => true,
                    'url' => 'https://assistant.test/profile',
                    'data' => [
                        'can_checkout' => false,
                        'subscription' => [
                            'active' => true,
                            'status' => 'active',
                        ],
                    ],
                ]);
        });

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/checkout', [
                'interval' => 'monthly',
                'success_url' => 'https://assistant.test/profile',
                'cancel_url' => 'https://assistant.test/profile',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('url', 'https://assistant.test/profile');
        $response->assertJsonPath('data.subscription.active', true);
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

    /**
     * @return array{reason: string, comment?: string}
     */
    private function cancelPayload(string $reason = 'too_expensive', ?string $comment = null): array
    {
        $payload = ['reason' => $reason];
        if ($comment !== null)
        {
            $payload['comment'] = $comment;
        }

        return $payload;
    }

    public function test_cancel_requires_a_reason(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/subscription/cancel', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_cancel_requires_comment_when_reason_is_other(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/subscription/cancel', ['reason' => 'other'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['comment']);
    }

    public function test_cancel_requires_an_active_assistant_subscription(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/subscription/cancel', $this->cancelPayload())
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cancel_rejects_already_scheduled_cancellation(): void
    {
        [$user, $team, $token] = $this->assistantUserWithToken();

        $team->subscriptions()->create([
            'user_id' => $user->id,
            'type' => 'assistant',
            'stripe_id' => 'sub_test_assistant_cancel_scheduled',
            'stripe_status' => 'active',
            'stripe_price' => 'price_assistant_monthly',
            'quantity' => 1,
            'ends_at' => now()->addDays(10),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/subscription/cancel', $this->cancelPayload())
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cancel_returns_updated_summary_when_service_succeeds(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $this->mock(AssistantSubscriptionService::class, function ($mock)
        {
            $mock->shouldReceive('cancel')
                ->once()
                ->andReturn([
                    'success' => true,
                    'data' => [
                        'plan' => ['id' => 'assistant'],
                        'subscription' => [
                            'active' => true,
                            'ends_at' => now()->addDays(20)->toIso8601String(),
                        ],
                        'can_checkout' => false,
                        'invoices' => [],
                    ],
                ]);
        });

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/subscription/cancel', $this->cancelPayload('missing_features', 'Falta un reporte'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.plan.id', 'assistant');
    }

    public function test_cancel_is_forbidden_for_non_owners(): void
    {
        [, $team] = $this->assistantUserWithToken();

        $member = User::factory()->create();
        $team->users()->attach($member, ['role' => 'admin']);
        $member->forceFill(['current_team_id' => $team->id])->save();
        $memberToken = $member->createToken('assistant-billing-member')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$memberToken)
            ->postJson('/api/assistant/subscription/cancel')
            ->assertStatus(403);
    }

    public function test_resume_requires_a_scheduled_cancellation(): void
    {
        [$user, $team, $token] = $this->assistantUserWithToken();

        $team->subscriptions()->create([
            'user_id' => $user->id,
            'type' => 'assistant',
            'stripe_id' => 'sub_test_assistant_resume_active',
            'stripe_status' => 'active',
            'stripe_price' => 'price_assistant_monthly',
            'quantity' => 1,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/subscription/resume')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_resume_returns_updated_summary_when_service_succeeds(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $this->mock(AssistantSubscriptionService::class, function ($mock)
        {
            $mock->shouldReceive('resume')
                ->once()
                ->andReturn([
                    'success' => true,
                    'data' => [
                        'plan' => ['id' => 'assistant'],
                        'subscription' => [
                            'active' => true,
                            'ends_at' => null,
                        ],
                        'can_checkout' => false,
                        'invoices' => [],
                    ],
                ]);
        });

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/subscription/resume')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subscription.ends_at', null);
    }

    public function test_resume_is_forbidden_for_non_owners(): void
    {
        [, $team] = $this->assistantUserWithToken();

        $member = User::factory()->create();
        $team->users()->attach($member, ['role' => 'admin']);
        $member->forceFill(['current_team_id' => $team->id])->save();
        $memberToken = $member->createToken('assistant-billing-member')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$memberToken)
            ->postJson('/api/assistant/subscription/resume')
            ->assertStatus(403);
    }

    public function test_payment_method_requires_return_urls(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/payment-method', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['success_url', 'cancel_url']);
    }

    public function test_payment_method_returns_stripe_url_when_service_succeeds(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $this->mock(AssistantSubscriptionService::class, function ($mock)
        {
            $mock->shouldReceive('createPaymentMethodUpdate')
                ->once()
                ->andReturn([
                    'success' => true,
                    'url' => 'https://checkout.stripe.com/c/pay/cs_test_payment_method',
                ]);
        });

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/payment-method', [
                'success_url' => 'https://assistant.test/profile?updated=payment-method',
                'cancel_url' => 'https://assistant.test/profile?checkout=cancel&updated=payment-method',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('url', 'https://checkout.stripe.com/c/pay/cs_test_payment_method');
    }

    public function test_payment_method_returns_needs_billing_code_when_stripe_customer_is_missing(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $this->mock(AssistantSubscriptionService::class, function ($mock)
        {
            $mock->shouldReceive('createPaymentMethodUpdate')
                ->once()
                ->andReturn([
                    'success' => false,
                    'code' => 'needs_billing',
                    'message' => 'Completá los datos de facturación para crear el cliente en Stripe.',
                ]);
        });

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/payment-method', [
                'success_url' => 'https://assistant.test/profile?updated=payment-method',
                'cancel_url' => 'https://assistant.test/profile?checkout=cancel&updated=payment-method',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'needs_billing')
            ->assertJsonPath('message', 'Completá los datos de facturación para crear el cliente en Stripe.');
    }

    public function test_payment_method_is_forbidden_for_non_owners(): void
    {
        [, $team] = $this->assistantUserWithToken();

        $member = User::factory()->create();
        $team->users()->attach($member, ['role' => 'admin']);
        $member->forceFill(['current_team_id' => $team->id])->save();
        $memberToken = $member->createToken('assistant-billing-member')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$memberToken)
            ->postJson('/api/assistant/payment-method', [
                'success_url' => 'https://assistant.test/profile',
                'cancel_url' => 'https://assistant.test/profile',
            ])
            ->assertStatus(403);
    }

    public function test_platform_catalog_returns_hunter_business_and_mentor_plans(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/subscription?catalog=platform');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.plan.id', 'hunter');
        $response->assertJsonPath('data.subscription', null);
        $response->assertJsonPath('data.can_checkout', true);
        $this->assertSame(
            ['hunter', 'business', 'mentor'],
            collect($response->json('data.plans'))->pluck('id')->all(),
        );
        $this->assertNotEmpty($response->json('data.plans.0.monthly_amount'));
        $this->assertNotEmpty($response->json('data.plans.1.name'));
    }

    public function test_platform_catalog_ignores_active_assistant_subscription(): void
    {
        [$user, $team, $token] = $this->assistantUserWithToken();
        $monthlyPrice = collect(config('humano_pricing.plans', []))
            ->firstWhere('id', 'assistant')['stripe_price_monthly_id'] ?? 'price_assistant_monthly';

        $team->subscriptions()->create([
            'user_id' => $user->id,
            'type' => 'assistant',
            'stripe_id' => 'sub_test_assistant_platform_catalog',
            'stripe_status' => 'active',
            'stripe_price' => $monthlyPrice,
            'quantity' => 1,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/subscription?catalog=platform');

        $response->assertOk();
        $response->assertJsonPath('data.subscription', null);
        $response->assertJsonPath('data.can_checkout', true);
        $this->assertSame(
            ['hunter', 'business', 'mentor'],
            collect($response->json('data.plans'))->pluck('id')->all(),
        );
    }

    public function test_shop_catalog_returns_freelancer_commerce_and_enterprise_plans(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/subscription?catalog=shop');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.plan.id', 'shop_basic');
        $response->assertJsonPath('data.plan.name', 'Shop Freelancer');
        $response->assertJsonPath('data.plan.monthly_amount', '19');
        $response->assertJsonPath('data.subscription', null);
        $response->assertJsonPath('data.can_checkout', true);
        $this->assertSame(
            ['shop_basic', 'shop_premium', 'shop_profesional'],
            collect($response->json('data.plans'))->pluck('id')->all(),
        );
        $this->assertSame(
            ['19', '39', '79'],
            collect($response->json('data.plans'))->pluck('monthly_amount')->all(),
        );
        $this->assertSame(
            ['190', '390', '790'],
            collect($response->json('data.plans'))->pluck('yearly_amount')->all(),
        );
        $this->assertSame(
            ['Shop Freelancer', 'Shop Commerce', 'Shop Enterprise'],
            collect($response->json('data.plans'))->pluck('name')->all(),
        );
        $this->assertNotContains('hunter', collect($response->json('data.plans'))->pluck('id')->all());
        $this->assertNotContains('business', collect($response->json('data.plans'))->pluck('id')->all());
    }

    public function test_mailer_catalog_returns_basic_foundation_and_scale_plans(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/subscription?catalog=mailer');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.plan.id', 'mailer_basic');
        $response->assertJsonPath('data.plan.name', 'Mailer Basic');
        $response->assertJsonPath('data.plan.monthly_amount', '15.99');
        $response->assertJsonPath('data.subscription', null);
        $response->assertJsonPath('data.can_checkout', true);
        $response->assertJsonPath('data.mailer_usage.price_per_email', '0.002');
        $response->assertJsonPath('data.mailer_usage.emails_included', 10000);
        $this->assertSame(
            ['mailer_basic', 'mailer_foundation', 'mailer_scale'],
            collect($response->json('data.plans'))->pluck('id')->all(),
        );
        $this->assertSame(
            ['15.99', '35.99', '119.99'],
            collect($response->json('data.plans'))->pluck('monthly_amount')->all(),
        );
        $this->assertSame(
            [3000, 20000, 50000],
            collect($response->json('data.plans'))->pluck('subscribers_limit')->all(),
        );
        $this->assertSame('Mailer Scale', $response->json('data.plans.2.name'));
    }

    public function test_mailer_catalog_ignores_active_assistant_subscription(): void
    {
        [$user, $team, $token] = $this->assistantUserWithToken();
        $monthlyPrice = collect(config('humano_pricing.plans', []))
            ->firstWhere('id', 'assistant')['stripe_price_monthly_id'] ?? 'price_assistant_monthly';

        $team->subscriptions()->create([
            'user_id' => $user->id,
            'type' => 'assistant',
            'stripe_id' => 'sub_test_mailer_catalog_ignores_assistant',
            'stripe_status' => 'active',
            'stripe_price' => $monthlyPrice,
            'quantity' => 1,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/subscription?catalog=mailer');

        $response->assertOk();
        $response->assertJsonPath('data.subscription', null);
        $response->assertJsonPath('data.can_checkout', true);
        $this->assertSame(
            ['mailer_basic', 'mailer_foundation', 'mailer_scale'],
            collect($response->json('data.plans'))->pluck('id')->all(),
        );
    }

    public function test_checkout_rejects_unknown_plan(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/checkout', [
                'interval' => 'monthly',
                'plan' => 'hosting-basic',
                'success_url' => 'https://mailer.test/profile',
                'cancel_url' => 'https://mailer.test/profile',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);
    }
}
