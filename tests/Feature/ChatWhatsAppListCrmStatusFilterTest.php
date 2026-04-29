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

class ChatWhatsAppListCrmStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $user;

    private string $teamWa = '34999000111';

    private string $phoneA = '34111111111';

    private string $phoneB = '34222222222';

    private string $phoneC = '34333333333';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->user = User::factory()->withPersonalTeam()->create();
        $this->team = $this->user->ownedTeams()->first();
        $this->user->forceFill(['current_team_id' => $this->team->id])->save();
        $this->user->assignRole('admin');

        $this->team->setSetting('whatsapp_from', $this->teamWa);

        $leadId = ContactStatus::where('name', 'Lead')->firstOrFail()->id;
        $clienteId = ContactStatus::where('name', 'Cliente')->firstOrFail()->id;

        Contact::factory()->create([
            'team_id' => $this->team->id,
            'phone' => $this->phoneA,
            'status_id' => $leadId,
            'creator_id' => $this->user->id,
            'responsible_id' => $this->user->id,
        ]);
        Contact::factory()->create([
            'team_id' => $this->team->id,
            'phone' => $this->phoneB,
            'status_id' => $clienteId,
            'creator_id' => $this->user->id,
            'responsible_id' => $this->user->id,
        ]);

        $i = 0;
        foreach ([$this->phoneA, $this->phoneB, $this->phoneC] as $from)
        {
            Conversation::create([
                'message_sid' => 'SM_crm_filter_test_'.(++$i),
                'channel' => 'whatsapp',
                'from' => $from,
                'to' => $this->teamWa,
                'body' => 'Hello',
                'status' => 'received',
                'direction' => 'inbound',
            ]);
        }
    }

    public function test_chat_list_returns_all_phones_without_crm_status_param(): void
    {
        $res = $this->actingAs($this->user)->getJson(route('chat.list'));
        $res->assertOk();
        $froms = collect($res->json('contacts'))->pluck('from')->map(fn ($f) => (string) $f)->sort()->values()->all();
        $expected = collect([$this->phoneA, $this->phoneB, $this->phoneC])
            ->map(fn ($p) => preg_replace('/[^0-9]/', '', (string) $p))
            ->sort()
            ->values()
            ->all();
        $this->assertSame($expected, $froms);
    }

    public function test_chat_list_filters_by_status_id(): void
    {
        $leadId = ContactStatus::where('name', 'Lead')->firstOrFail()->id;

        $res = $this->actingAs($this->user)->getJson(route('chat.list', ['crm_status' => (string) $leadId]));
        $res->assertOk();

        $froms = collect($res->json('contacts'))->pluck('from')->all();
        $digitsA = preg_replace('/[^0-9]/', '', $this->phoneA);

        $this->assertSame([$digitsA], $froms);
    }

    public function test_chat_list_none_shows_only_chats_without_crm_contact(): void
    {
        $digitsC = preg_replace('/[^0-9]/', '', $this->phoneC);

        $res = $this->actingAs($this->user)->getJson(route('chat.list', ['crm_status' => 'none']));
        $res->assertOk();

        $froms = collect($res->json('contacts'))->pluck('from')->all();
        $this->assertSame([$digitsC], $froms);
    }
}
