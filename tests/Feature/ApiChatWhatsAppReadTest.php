<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiChatWhatsAppReadTest extends TestCase
{
    use RefreshDatabase;

    private const TEAM_NUMBER = '34999000111';

    private const CLIENT_PHONE = '34600111222';

    public function test_mark_unread_requires_authentication(): void
    {
        $this->patchJson('/api/chat/whatsapp-read', [
            'phone' => self::CLIENT_PHONE,
            'unread' => true,
        ])->assertStatus(401);
    }

    public function test_mark_unread_restores_the_latest_inbound_after_opening_the_thread(): void
    {
        [$token] = $this->inbox();

        Conversation::query()
            ->where('from', self::CLIENT_PHONE)
            ->update(['status' => 'read']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list')
            ->assertOk()
            ->assertJsonPath('contacts.0.unread_count', 0);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-read', [
                'phone' => self::CLIENT_PHONE,
                'unread' => true,
            ])
            ->assertOk()
            ->assertJsonPath('unread', true)
            ->assertJsonPath('unread_count', 1);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list')
            ->assertOk()
            ->assertJsonPath('contacts.0.unread_count', 1);
    }

    public function test_mark_read_clears_inbound_received_messages(): void
    {
        [$token] = $this->inbox();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list')
            ->assertOk()
            ->assertJsonPath('contacts.0.unread_count', 1);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-read', [
                'phone' => self::CLIENT_PHONE,
                'unread' => false,
            ])
            ->assertOk()
            ->assertJsonPath('unread', false)
            ->assertJsonPath('unread_count', 0);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list')
            ->assertOk()
            ->assertJsonPath('contacts.0.unread_count', 0);
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

        Http::fake(['*' => Http::response(['pictures' => []], 200)]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->setSetting('whatsapp_from', self::TEAM_NUMBER);

        Conversation::create([
            'message_sid' => 'SM_read_inbox',
            'channel' => 'whatsapp',
            'from' => self::CLIENT_PHONE,
            'to' => self::TEAM_NUMBER,
            'body' => 'Hola',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        return [$user->createToken('read')->plainTextToken, $user];
    }
}
