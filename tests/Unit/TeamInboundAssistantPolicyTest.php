<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Models\User;
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
}
