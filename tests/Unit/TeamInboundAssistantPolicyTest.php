<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Models\User;
use App\Services\Billing\AssistantSubscriptionService;
use App\Services\TeamInboundAssistantPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamInboundAssistantPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_auto_respond_on_allows_reply_when_no_contact_opt_out(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('assistant_auto_respond', '1');

        $policy = app(TeamInboundAssistantPolicy::class);

        $this->assertTrue($policy->allowsWhatsAppAutoReply($team, null));
    }

    public function test_team_auto_respond_off_blocks_even_when_contact_enables_assistant(): void
    {
        $this->seed([
            \Database\Seeders\CountrySeeder::class,
            \Database\Seeders\LanguageSeeder::class,
            \Database\Seeders\ContactStatusSeeder::class,
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('assistant_auto_respond', '0');
        $team->setSetting('assistant_auto_respond_admins_when_off', '0');

        \App\Models\Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '34600000000',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'data' => (object) ['chat_assistant_ai_enabled' => true],
        ]);

        $policy = app(TeamInboundAssistantPolicy::class);

        $this->assertFalse($policy->allowsWhatsAppAutoReply($team, null, (int) $team->id, '34600000000'));
    }

    public function test_silent_team_default_blocks_automatic_chats_but_allows_pinned_prompt(): void
    {
        $this->seed([
            \Database\Seeders\CountrySeeder::class,
            \Database\Seeders\LanguageSeeder::class,
            \Database\Seeders\ContactStatusSeeder::class,
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('assistant_auto_respond', '1');
        $team->setSetting(\App\Services\TeamSiteAssistantPromptService::SETTING_KEY, \App\Services\TeamSiteAssistantPromptService::OFF_KEY);

        \App\Models\Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '34600000001',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'data' => (object) ['chat_assistant_ai_enabled' => true],
        ]);
        \App\Models\Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '34600000002',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'data' => (object) [
                'chat_assistant_ai_enabled' => true,
                'chat_assistant_prompt_key' => 'chat:citas_y_ventas',
            ],
        ]);

        $policy = app(TeamInboundAssistantPolicy::class);

        $this->assertFalse($policy->autoReplyPreferencesAllow($team, null, (int) $team->id, '34600000001'));
        $this->assertFalse($policy->autoReplyPreferencesAllow($team, null, (int) $team->id, '34600000999'));
        $this->assertTrue($policy->autoReplyPreferencesAllow($team, null, (int) $team->id, '34600000002'));
    }

    public function test_contact_opt_out_blocks_reply_even_when_team_auto_respond_is_on(): void
    {
        $this->seed([
            \Database\Seeders\CountrySeeder::class,
            \Database\Seeders\LanguageSeeder::class,
            \Database\Seeders\ContactStatusSeeder::class,
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('assistant_auto_respond', '1');

        \App\Models\Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '34600000000',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'data' => (object) ['chat_assistant_ai_enabled' => false],
        ]);

        $policy = app(TeamInboundAssistantPolicy::class);

        $this->assertFalse($policy->allowsWhatsAppAutoReply($team, null, (int) $team->id, '34600000000'));
    }

    public function test_any_matching_contact_opt_out_blocks_reply(): void
    {
        $this->seed([
            \Database\Seeders\CountrySeeder::class,
            \Database\Seeders\LanguageSeeder::class,
            \Database\Seeders\ContactStatusSeeder::class,
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('assistant_auto_respond', '1');

        \App\Models\Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '34600000000',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'data' => (object) ['chat_assistant_ai_enabled' => true],
        ]);
        \App\Models\Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '+34 600 000 000',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'data' => (object) ['chat_assistant_ai_enabled' => false],
        ]);

        $policy = app(TeamInboundAssistantPolicy::class);

        $this->assertFalse($policy->allowsWhatsAppAutoReply($team, null, (int) $team->id, '34600000000'));
    }

    public function test_team_auto_respond_off_blocks_non_admin_senders(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('assistant_auto_respond', '0');
        $team->setSetting('assistant_auto_respond_admins_when_off', '0');

        $policy = app(TeamInboundAssistantPolicy::class);

        $this->assertFalse($policy->allowsWhatsAppAutoReply($team, User::factory()->create()));
    }

    public function test_admins_only_when_off_allows_team_admin_sender(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $admin = User::factory()->create();
        $admin->teams()->attach($team->id, ['role' => 'admin']);
        $admin->assignRole('admin');

        $team->setSetting('assistant_auto_respond', '0');
        $team->setSetting('assistant_auto_respond_admins_when_off', '1');

        $policy = app(TeamInboundAssistantPolicy::class);

        $this->assertTrue($policy->allowsWhatsAppAutoReply($team, $admin));
    }

    public function test_admin_owner_of_another_team_is_not_an_administrator_here(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $owner = User::factory()->create();
        $salesTeam = Team::factory()->create(['user_id' => $owner->id]);
        $foreignOwner = User::factory()->create();
        $foreignTeam = Team::factory()->create(['user_id' => $foreignOwner->id]);
        $foreignOwner->teams()->attach($foreignTeam->id, ['role' => 'admin']);
        $foreignOwner->assignRole('admin');

        $policy = app(TeamInboundAssistantPolicy::class);

        $this->assertFalse($policy->inboundSenderIsTeamAdministrator($foreignOwner, (int) $salesTeam->id));

        config([
            'humano_pricing.plan_access_team_ids' => [],
            'humano_pricing.require_paid_plan_for_ai' => false,
        ]);
        $this->assertFalse($policy->teamHasConfiguredInboundBot($foreignTeam));
        $this->assertFalse($policy->shouldSkipLinkedPeerAutoReply($salesTeam, '5491147348879', (int) $foreignTeam->id));

        $foreignTeam->setSetting('assistant_auto_respond', '1');
        $foreignTeam->setSetting(\App\Services\TeamSiteAssistantPromptService::SETTING_KEY, 'calendar:assistant_citas');
        $this->assertTrue($policy->teamHasConfiguredInboundBot($foreignTeam));
        $this->assertTrue($policy->shouldSkipLinkedPeerAutoReply($salesTeam, '5491147348879', (int) $foreignTeam->id));
    }

    public function test_contact_opt_out_blocks_even_an_admin_sender_when_team_allows_admins(): void
    {
        $this->seed([
            \Database\Seeders\CountrySeeder::class,
            \Database\Seeders\LanguageSeeder::class,
            \Database\Seeders\ContactStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $admin = User::factory()->create();
        $admin->teams()->attach($team->id, ['role' => 'admin']);
        $admin->assignRole('admin');

        $team->setSetting('assistant_auto_respond', '0');
        $team->setSetting('assistant_auto_respond_admins_when_off', '1');

        \App\Models\Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '34722372858',
            'creator_id' => $owner->id,
            'responsible_id' => $owner->id,
            'user_id' => $admin->id,
            'data' => (object) ['chat_assistant_ai_enabled' => false],
        ]);

        $policy = app(TeamInboundAssistantPolicy::class);

        $this->assertFalse($policy->allowsWhatsAppAutoReply($team, $admin, (int) $team->id, '34722372858'));
    }

    public function test_blacklisted_sender_phone_blocks_auto_reply_even_when_team_is_on(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('assistant_auto_respond', '1');
        $team->setSetting('assistant_whatsapp_blacklist_numbers', "34600000000\n+34 611 222 333");

        $policy = app(TeamInboundAssistantPolicy::class);

        $this->assertFalse($policy->allowsWhatsAppAutoReply($team, null, (int) $team->id, '34600000000'));
        $this->assertFalse($policy->allowsWhatsAppAutoReply($team, null, (int) $team->id, '+34 611 222 333'));
        $this->assertTrue($policy->allowsWhatsAppAutoReply($team, null, (int) $team->id, '34699999999'));
    }

    public function test_missing_paid_plan_blocks_auto_reply_when_required(): void
    {
        config([
            'humano_pricing.require_paid_plan_for_ai' => true,
            'humano_pricing.plan_access_team_ids' => [],
        ]);

        $team = Team::factory()->create(['created_at' => now()->subHours(49)]);
        $this->consumeAppTrial($team, 'assistant');
        $team->setSetting('assistant_auto_respond', '1');

        $policy = app(TeamInboundAssistantPolicy::class);

        $this->assertFalse($policy->allowsWhatsAppAutoReply($team, null));
        $this->assertSame('plan', $policy->lockedReason($team));
    }

    public function test_app_trial_unlocks_auto_reply_like_a_paid_plan(): void
    {
        config([
            'humano_pricing.require_paid_plan_for_ai' => true,
            'humano_pricing.app_trials.assistant' => 48,
        ]);

        $team = Team::factory()->create(['created_at' => now()->subHours(12)]);
        $team->setSetting('assistant_auto_respond', '1');

        $policy = app(TeamInboundAssistantPolicy::class);
        $access = app(AssistantSubscriptionService::class)
            ->accessForCatalog($team, 'assistant');

        $this->assertTrue($policy->allowsWhatsAppAutoReply($team, null));
        $this->assertNull($policy->lockedReason($team));
        $this->assertTrue($access['active']);
        $this->assertSame('trial', $access['status']);
        $this->assertNotNull($access['trial_ends_at']);
    }

    /**
     * The plan stops the AI from answering; it does not stop the team from choosing which prompt
     * that AI will use once the plan is paid.
     */
    public function test_missing_paid_plan_still_lets_the_team_pick_a_prompt(): void
    {
        config([
            'humano_pricing.require_paid_plan_for_ai' => true,
            'humano_pricing.plan_access_team_ids' => [],
        ]);

        $team = Team::factory()->create(['created_at' => now()->subHours(49)]);
        $this->consumeAppTrial($team, 'assistant');
        $team->setSetting('assistant_auto_respond', '1');

        $state = app(TeamInboundAssistantPolicy::class)->presentWhatsAppAssistantState($team, true, true);

        $this->assertTrue($state['assistant_toggle_available']);
        $this->assertTrue($state['assistant_inbound_enabled']);
        $this->assertFalse($state['assistant_plan_active']);
        $this->assertSame('plan', $state['assistant_locked_reason']);
    }

    public function test_auto_reply_preferences_ignore_the_plan_but_keep_team_choices(): void
    {
        config(['humano_pricing.require_paid_plan_for_ai' => true]);

        $team = Team::factory()->create();
        $policy = app(TeamInboundAssistantPolicy::class);

        $team->setSetting('assistant_auto_respond', '1');
        $this->assertTrue($policy->autoReplyPreferencesAllow($team, null));

        $team->setSetting('assistant_auto_respond', '0');
        $this->assertFalse($policy->autoReplyPreferencesAllow($team, null));
    }

    public function test_active_assistant_subscription_unlocks_auto_reply(): void
    {
        config(['humano_pricing.require_paid_plan_for_ai' => true]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('assistant_auto_respond', '1');
        $team->subscriptions()->create([
            'user_id' => $user->id,
            'type' => 'assistant',
            'stripe_id' => 'sub_ai_gate_active',
            'stripe_status' => 'active',
            'stripe_price' => 'price_assistant_monthly',
            'quantity' => 1,
        ]);

        $policy = app(TeamInboundAssistantPolicy::class);

        $this->assertTrue($policy->allowsWhatsAppAutoReply($team, null));
        $this->assertNull($policy->lockedReason($team));
    }

    public function test_trialing_assistant_subscription_unlocks_auto_reply(): void
    {
        config(['humano_pricing.require_paid_plan_for_ai' => true]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('assistant_auto_respond', '1');
        $team->subscriptions()->create([
            'user_id' => $user->id,
            'type' => 'assistant',
            'stripe_id' => 'sub_ai_gate_trial',
            'stripe_status' => 'trialing',
            'stripe_price' => 'price_assistant_monthly',
            'quantity' => 1,
        ]);

        $policy = app(TeamInboundAssistantPolicy::class);

        $this->assertTrue($policy->allowsWhatsAppAutoReply($team, null));
        $this->assertTrue($policy->presentWhatsAppAssistantState($team, true, true)['assistant_plan_active']);
    }

    public function test_whitelisted_team_has_paid_access_without_subscription_or_trial(): void
    {
        $team = Team::factory()->create(['created_at' => now()->subHours(49)]);
        $team->setSetting('assistant_auto_respond', '1');

        config([
            'humano_pricing.require_paid_plan_for_ai' => true,
            'humano_pricing.plan_access_team_ids' => [(int) $team->id],
            'humano_pricing.app_trials.assistant' => 48,
        ]);

        $access = app(AssistantSubscriptionService::class)->accessForCatalog($team, 'assistant');
        $policy = app(TeamInboundAssistantPolicy::class);

        $this->assertTrue($access['active']);
        $this->assertSame('paid', $access['status']);
        $this->assertNull($access['locked_reason']);
        $this->assertTrue($policy->allowsWhatsAppAutoReply($team, null));
        $this->assertNull($policy->lockedReason($team));
    }

    public function test_team_outside_whitelist_stays_expired_after_trial(): void
    {
        $team = Team::factory()->create(['created_at' => now()->subHours(49)]);
        $this->consumeAppTrial($team, 'assistant');
        $team->setSetting('assistant_auto_respond', '1');

        config([
            'humano_pricing.require_paid_plan_for_ai' => true,
            'humano_pricing.plan_access_team_ids' => [(int) $team->id + 999],
            'humano_pricing.app_trials.assistant' => 48,
        ]);

        $access = app(AssistantSubscriptionService::class)->accessForCatalog($team, 'assistant');
        $policy = app(TeamInboundAssistantPolicy::class);

        $this->assertFalse($access['active']);
        $this->assertSame('expired', $access['status']);
        $this->assertSame('plan', $access['locked_reason']);
        $this->assertFalse($policy->allowsWhatsAppAutoReply($team, null));
        $this->assertSame('plan', $policy->lockedReason($team));
    }

    public function test_hosting_subscription_does_not_unlock_assistant(): void
    {
        config([
            'humano_pricing.require_paid_plan_for_ai' => true,
            'humano_pricing.plan_access_team_ids' => [],
            'humano_pricing.app_trials.assistant' => 48,
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subHours(49),
        ]);
        $this->consumeAppTrial($team, 'assistant');
        $team->setSetting('assistant_auto_respond', '1');
        $team->subscriptions()->create([
            'user_id' => $user->id,
            'type' => 'hosting',
            'stripe_id' => 'sub_hosting_independent',
            'stripe_status' => 'active',
            'stripe_price' => 'price_hosting_monthly',
            'quantity' => 1,
        ]);

        $service = app(AssistantSubscriptionService::class);
        $assistant = $service->accessForCatalog($team, 'assistant');
        $apps = $service->appsPayload($team);

        $this->assertFalse($assistant['active']);
        $this->assertSame('expired', $assistant['status']);
        $this->assertSame('expired', $apps['assistant']['status']);
        $this->assertFalse(app(TeamInboundAssistantPolicy::class)->allowsWhatsAppAutoReply($team, null));
    }

    public function test_assistant_subscription_does_not_unlock_mailer(): void
    {
        config([
            'humano_pricing.plan_access_team_ids' => [],
            'humano_pricing.app_trials.mailer' => 0,
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subHours(49),
        ]);
        $team->subscriptions()->create([
            'user_id' => $user->id,
            'type' => 'assistant',
            'stripe_id' => 'sub_assistant_independent',
            'stripe_status' => 'active',
            'stripe_price' => 'price_assistant_monthly',
            'quantity' => 1,
        ]);

        $mailer = app(AssistantSubscriptionService::class)->accessForCatalog($team, 'mailer');

        $this->assertFalse($mailer['active']);
        $this->assertSame('expired', $mailer['status']);
    }

    public function test_old_team_gets_a_fresh_trial_when_the_product_is_new(): void
    {
        config([
            'humano_pricing.require_paid_plan_for_ai' => true,
            'humano_pricing.plan_access_team_ids' => [],
            'humano_pricing.app_trials.assistant' => 48,
        ]);

        $team = Team::factory()->create(['created_at' => now()->subYear()]);
        $service = app(AssistantSubscriptionService::class);

        $first = $service->accessForCatalog($team, 'assistant');
        $second = $service->accessForCatalog($team->fresh(), 'assistant');

        $this->assertTrue($first['active']);
        $this->assertSame('trial', $first['status']);
        $this->assertNotNull($first['trial_ends_at']);
        $this->assertSame($first['trial_ends_at'], $second['trial_ends_at']);
        $this->assertTrue(now()->addHours(47)->lt(\Carbon\Carbon::parse($first['trial_ends_at'])));
    }

    public function test_estimator_shop_and_mailer_share_the_assistant_trial_window(): void
    {
        config([
            'humano_pricing.plan_access_team_ids' => [],
            'humano_pricing.app_trials.estimator' => 48,
            'humano_pricing.app_trials.shop' => 48,
            'humano_pricing.app_trials.mailer' => 48,
        ]);

        $team = Team::factory()->create(['created_at' => now()->subYear()]);
        $service = app(AssistantSubscriptionService::class);
        $apps = $service->appsPayload($team);

        foreach (['estimator', 'shop', 'mailer', 'ads', 'projects'] as $catalog)
        {
            $this->assertArrayHasKey($catalog, $apps);
            $this->assertTrue($apps[$catalog]['active']);
            $this->assertSame('trial', $apps[$catalog]['status']);
            $this->assertNotNull($apps[$catalog]['trial_ends_at']);
        }
    }

    public function test_estimator_with_saved_card_is_paid_without_subscription(): void
    {
        config([
            'humano_pricing.plan_access_team_ids' => [],
            'humano_pricing.app_trials.estimator' => 48,
        ]);

        $team = Team::factory()->create([
            'created_at' => now()->subYear(),
            'pm_type' => 'card',
            'pm_last_four' => '4242',
        ]);

        $access = app(AssistantSubscriptionService::class)->accessForCatalog($team, 'estimator');

        $this->assertTrue($access['active']);
        $this->assertSame('paid', $access['status']);
    }

    private function consumeAppTrial(Team $team, string $catalog): void
    {
        $started = $team->created_at?->toIso8601String() ?? now()->subHours(49)->toIso8601String();
        $team->setSetting(AssistantSubscriptionService::trialStartedSettingKey($catalog), $started, [
            'type' => 'string',
            'group' => 'billing',
        ]);
    }
}
