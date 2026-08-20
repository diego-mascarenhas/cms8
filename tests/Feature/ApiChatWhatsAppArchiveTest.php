<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Models\WhatsAppChatArchive;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiChatWhatsAppArchiveTest extends TestCase
{
    use RefreshDatabase;

    private const TEAM_NUMBER = '34999000111';

    private const CLIENT_PHONE = '34600111222';

    private const OTHER_PHONE = '34600333444';

    public function test_archive_requires_authentication(): void
    {
        $this->patchJson('/api/chat/whatsapp-archive', [
            'phone' => self::CLIENT_PHONE,
            'archived' => true,
        ])->assertStatus(401);
    }

    public function test_inbox_hides_archived_chats_and_counts_them_apart(): void
    {
        [$token, $user] = $this->inbox();

        WhatsAppChatArchive::factory()->create([
            'team_id' => $user->currentTeam->id,
            'phone' => self::CLIENT_PHONE,
        ]);

        $inbox = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list');

        $inbox->assertOk();
        $inbox->assertJsonPath('total', 1);
        $inbox->assertJsonPath('archived_count', 1);
        $this->assertSame([self::OTHER_PHONE], $this->phonesIn($inbox->json('contacts')));

        $archived = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list?archived=1');

        $archived->assertOk();
        $archived->assertJsonPath('total', 1);
        $archived->assertJsonPath('contacts.0.from', self::CLIENT_PHONE);
        $archived->assertJsonPath('contacts.0.is_archived', true);
    }

    public function test_archive_and_unarchive_move_the_chat_between_lists(): void
    {
        [$token] = $this->inbox();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-archive', [
                'phone' => self::CLIENT_PHONE,
                'archived' => true,
            ])
            ->assertOk()
            ->assertJsonPath('is_archived', true);

        $this->assertDatabaseHas('whatsapp_chat_archives', [
            'phone' => self::CLIENT_PHONE,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list')
            ->assertJsonPath('archived_count', 1)
            ->assertJsonPath('total', 1);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-archive', [
                'phone' => self::CLIENT_PHONE,
                'archived' => false,
            ])
            ->assertOk()
            ->assertJsonPath('is_archived', false);

        $this->assertDatabaseMissing('whatsapp_chat_archives', [
            'phone' => self::CLIENT_PHONE,
        ]);
    }

    public function test_inbound_message_unarchives_the_chat(): void
    {
        [$token, $user] = $this->inbox();

        WhatsAppChatArchive::factory()->create([
            'team_id' => $user->currentTeam->id,
            'phone' => self::CLIENT_PHONE,
        ]);

        Conversation::create([
            'message_sid' => 'SM_unarchive_inbound',
            'channel' => 'whatsapp',
            'from' => self::CLIENT_PHONE,
            'to' => self::TEAM_NUMBER,
            'body' => 'Volví',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        $this->assertDatabaseMissing('whatsapp_chat_archives', [
            'team_id' => $user->currentTeam->id,
            'phone' => self::CLIENT_PHONE,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list')
            ->assertJsonPath('archived_count', 0)
            ->assertJsonPath('total', 2);
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

        foreach ([self::CLIENT_PHONE, self::OTHER_PHONE] as $index => $phone)
        {
            Conversation::create([
                'message_sid' => 'SM_archive_'.$index,
                'channel' => 'whatsapp',
                'from' => $phone,
                'to' => self::TEAM_NUMBER,
                'body' => 'Hola '.$phone,
                'status' => 'received',
                'direction' => 'inbound',
            ]);
        }

        return [$user->createToken('archive')->plainTextToken, $user];
    }

    /**
     * @param  array<int, array<string, mixed>>  $contacts
     * @return list<string>
     */
    private function phonesIn(array $contacts): array
    {
        return array_map(static fn (array $contact): string => (string) $contact['from'], $contacts);
    }
}
