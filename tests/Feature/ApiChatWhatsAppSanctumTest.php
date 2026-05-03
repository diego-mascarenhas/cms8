<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiChatWhatsAppSanctumTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_list_requires_authentication(): void
    {
        $this->getJson('/api/chat/whatsapp-list')->assertStatus(401);
    }

    public function test_whatsapp_messages_requires_authentication(): void
    {
        $this->getJson('/api/chat/whatsapp-messages/34111222333')->assertStatus(401);
    }

    public function test_whatsapp_list_returns_json_when_authenticated(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $teamWa = '34999000111';
        $team->setSetting('whatsapp_from', $teamWa);
        $clientPhone = '34600111222';
        Conversation::create([
            'message_sid' => 'SM_api_chat_list_1',
            'channel' => 'whatsapp',
            'from' => $clientPhone,
            'to' => $teamWa,
            'body' => 'Hola mundo',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list');

        $response->assertOk();
        $response->assertJsonStructure(['contacts']);
        $contacts = $response->json('contacts');
        $this->assertNotEmpty($contacts);
        $first = $contacts[0];
        $this->assertSame(preg_replace('/[^0-9]/', '', $clientPhone), $first['from']);
        $this->assertArrayHasKey('last_message', $first);
        $this->assertArrayHasKey('last_message_time', $first);
        $this->assertArrayHasKey('unread_count', $first);
    }

    public function test_whatsapp_messages_returns_messages_when_authenticated(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $teamWa = '34999000111';
        $team->setSetting('whatsapp_from', $teamWa);
        $clientPhone = '34600333444';
        Conversation::create([
            'message_sid' => 'SM_api_chat_msg_1',
            'channel' => 'whatsapp',
            'from' => $clientPhone,
            'to' => $teamWa,
            'body' => 'First',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-messages/'.$clientPhone);

        $response->assertOk();
        $response->assertJsonStructure(['messages']);
        $this->assertCount(1, $response->json('messages'));
        $this->assertSame('First', $response->json('messages.0.body'));
    }
}
