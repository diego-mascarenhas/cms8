<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Conversation;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatCollaboratorWhatsAppVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $owner;

    private string $teamWa = '34999000111';

    private string $phoneA = '34111111111';

    private string $phoneB = '34222222222';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

        $this->owner = User::factory()->withPersonalTeam()->create();
        $this->team = $this->owner->ownedTeams()->first();
        $this->team->setSetting('whatsapp_from', $this->teamWa);

        $leadId = ContactStatus::where('name', 'Lead')->firstOrFail()->id;
        Contact::factory()->create([
            'team_id' => $this->team->id,
            'phone' => $this->phoneA,
            'status_id' => $leadId,
            'creator_id' => $this->owner->id,
            'responsible_id' => $this->owner->id,
        ]);
        Contact::factory()->create([
            'team_id' => $this->team->id,
            'phone' => $this->phoneB,
            'status_id' => $leadId,
            'creator_id' => $this->owner->id,
            'responsible_id' => $this->owner->id,
        ]);

        $i = 0;
        foreach ([$this->phoneA, $this->phoneB] as $from)
        {
            Conversation::create([
                'message_sid' => 'SM_collab_vis_'.(++$i),
                'channel' => 'whatsapp',
                'from' => $from,
                'to' => $this->teamWa,
                'body' => 'Hello',
                'status' => 'received',
                'direction' => 'inbound',
            ]);
        }
    }

    public function test_collaborator_sees_all_team_whatsapp_chats_without_being_responsible(): void
    {
        $collaborator = User::factory()->create();
        $collaborator->assignRole('collaborator');
        $this->team->users()->attach($collaborator->id, ['role' => 'collaborator']);
        $collaborator->forceFill(['current_team_id' => $this->team->id])->save();

        $res = $this->actingAs($collaborator)->getJson(route('chat.list'));
        $res->assertOk();

        $froms = collect($res->json('contacts'))
            ->pluck('from')
            ->map(fn ($f) => (string) $f)
            ->sort()
            ->values()
            ->all();

        $expected = collect([$this->phoneA, $this->phoneB])
            ->map(fn ($p) => preg_replace('/[^0-9]/', '', (string) $p))
            ->sort()
            ->values()
            ->all();

        $this->assertSame($expected, $froms);
    }

    public function test_client_still_only_sees_assigned_contact_chats(): void
    {
        $client = User::factory()->create(['phone' => $this->phoneA]);
        $client->assignRole('client');
        $this->team->users()->attach($client->id, ['role' => 'client']);
        $client->forceFill(['current_team_id' => $this->team->id])->save();

        Contact::withoutGlobalScopes()
            ->where('team_id', $this->team->id)
            ->where('phone', $this->phoneA)
            ->update(['responsible_id' => $client->id]);

        $res = $this->actingAs($client)->getJson(route('chat.list'));
        $res->assertOk();

        $froms = collect($res->json('contacts'))->pluck('from')->all();
        $digitsA = preg_replace('/[^0-9]/', '', $this->phoneA);

        $this->assertSame([$digitsA], $froms);
    }
}
