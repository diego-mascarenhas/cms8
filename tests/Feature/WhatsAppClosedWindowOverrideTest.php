<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppCustomerServiceWindow;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppClosedWindowOverrideTest extends TestCase
{
    use RefreshDatabase;

    private const TEAM_NUMBER = '34999000111';

    private const CLIENT_PHONE = '34600111999';

    public function test_send_is_blocked_when_the_window_is_closed(): void
    {
        [$token] = $this->inbox();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/chat/whatsapp-send', [
                'to' => self::CLIENT_PHONE,
                'message' => 'Fuera de ventana',
            ])
            ->assertStatus(422);
    }

    public function test_accepted_override_sends_when_the_team_allows_it(): void
    {
        [$token] = $this->inbox();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/chat/whatsapp-send', [
                'to' => self::CLIENT_PHONE,
                'message' => 'Respondo igual',
                'accept_closed_window' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('conversations', [
            'channel' => 'whatsapp',
            'to' => self::CLIENT_PHONE,
            'direction' => 'outbound',
            'body' => 'Respondo igual',
        ]);
    }

    public function test_accepted_override_is_rejected_when_the_team_disables_it(): void
    {
        [$token, $user] = $this->inbox();
        $user->currentTeam->setSetting(WhatsAppCustomerServiceWindow::SETTING_KEY, false, [
            'group' => 'chat',
            'type' => 'boolean',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/chat/whatsapp-send', [
                'to' => self::CLIENT_PHONE,
                'message' => 'Respondo igual',
                'accept_closed_window' => true,
            ])
            ->assertStatus(422);
    }

    public function test_settings_toggle_persists_closed_window_policy(): void
    {
        [$token, $user] = $this->inbox();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-status')
            ->assertOk()
            ->assertJsonPath('allow_closed_window', true);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/team-settings-sidebar', [
                'key' => WhatsAppCustomerServiceWindow::SETTING_KEY,
                'on' => false,
            ])
            ->assertOk();

        $this->assertFalse($user->currentTeam->fresh()->allowsClosedWhatsAppWindow());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-status')
            ->assertOk()
            ->assertJsonPath('allow_closed_window', false);
    }

    /**
     * @return array{0: string, 1: User}
     */
    private function inbox(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        config(['whatsapp.driver' => 'local']);
        config(['whatsapp.local.base_url' => 'http://127.0.0.1:3000']);
        Http::fake([
            'http://127.0.0.1:3000/status*' => Http::response(['status' => 'connected', 'number' => self::TEAM_NUMBER], 200),
            'http://127.0.0.1:3000/send-message' => Http::response(['success' => true, 'id' => 'wa_override_1'], 200),
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->setSetting('whatsapp_from', self::TEAM_NUMBER);
        $team->setSetting('whatsapp_driver', 'local', ['group' => 'chat']);
        $team->setSetting('assistant_auto_respond', '0');

        $inbound = Conversation::create([
            'message_sid' => 'SM_closed_window_1',
            'channel' => 'whatsapp',
            'from' => self::CLIENT_PHONE,
            'to' => self::TEAM_NUMBER,
            'body' => 'Hola',
            'status' => 'received',
            'direction' => 'inbound',
        ]);
        $inbound->forceFill([
            'created_at' => now()->subHours(30),
            'updated_at' => now()->subHours(30),
        ])->save();

        return [$user->createToken('override')->plainTextToken, $user];
    }
}
