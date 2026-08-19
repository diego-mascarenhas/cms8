<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiChatWhatsAppListPaginationTest extends TestCase
{
    use RefreshDatabase;

    private const TEAM_NUMBER = '34999000111';

    public function test_limit_returns_the_newest_conversations_first(): void
    {
        [$token] = $this->inboxWithConversations(5);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list?limit=2');

        $response->assertOk();
        $this->assertSame(['34600000005', '34600000004'], $this->phonesIn($response->json('contacts')));
    }

    public function test_offset_continues_where_the_previous_page_stopped(): void
    {
        [$token] = $this->inboxWithConversations(5);

        $first = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list?limit=2');
        $second = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list?limit=2&offset='.$first->json('next_offset'));

        $second->assertOk();
        $this->assertSame(['34600000003', '34600000002'], $this->phonesIn($second->json('contacts')));
        $this->assertEmpty(array_intersect($this->phonesIn($first->json('contacts')), $this->phonesIn($second->json('contacts'))));
    }

    public function test_last_page_reports_no_more_conversations(): void
    {
        [$token] = $this->inboxWithConversations(5);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list?limit=2&offset=4');

        $response->assertOk();
        $response->assertJsonPath('has_more', false);
        $response->assertJsonPath('next_offset', 5);
        $this->assertSame(['34600000001'], $this->phonesIn($response->json('contacts')));
    }

    /**
     * The inbox header counts every thread, so a page must not shrink the totals it reports.
     */
    public function test_totals_cover_every_conversation_not_only_the_page(): void
    {
        [$token] = $this->inboxWithConversations(5);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list?limit=2');

        $response->assertOk();
        $response->assertJsonPath('total', 5);
        $response->assertJsonPath('unread_total', 5);
        $response->assertJsonPath('has_more', true);
        $this->assertCount(2, $response->json('contacts'));
    }

    public function test_list_without_a_limit_still_returns_every_conversation(): void
    {
        [$token] = $this->inboxWithConversations(5);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list');

        $response->assertOk();
        $response->assertJsonPath('has_more', false);
        $this->assertCount(5, $response->json('contacts'));
    }

    public function test_limit_is_bounded(): void
    {
        [$token] = $this->inboxWithConversations(1);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list?limit=500')
            ->assertStatus(422);
    }

    /**
     * @return array{0: string, 1: User, 2: Team}
     */
    private function inboxWithConversations(int $count): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Http::fake(['*' => Http::response(['pictures' => []], 200)]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->setSetting('whatsapp_from', self::TEAM_NUMBER);

        for ($i = 1; $i <= $count; $i++)
        {
            Conversation::create([
                'message_sid' => 'SM_list_page_'.$i,
                'channel' => 'whatsapp',
                'from' => '346000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'to' => self::TEAM_NUMBER,
                'body' => 'Mensaje '.$i,
                'status' => 'received',
                'direction' => 'inbound',
            ])->forceFill(['created_at' => now()->subMinutes($count - $i)])->save();
        }

        return [$user->createToken('pagination')->plainTextToken, $user, $team];
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
