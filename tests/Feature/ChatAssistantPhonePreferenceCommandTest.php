<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatAssistantPhonePreferenceCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_assistant_phone_off_and_on_updates_contact_data(): void
    {
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'phone' => '34722372858',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $this->artisan('chat:assistant-phone', ['state' => 'off', 'phone' => '+34 722 37 28 58'])
            ->assertSuccessful();

        $contact->refresh();
        $this->assertFalse($contact->allowsInboundChatAssistant());

        $this->artisan('chat:assistant-phone', ['state' => 'on', 'phone' => '34722372858', '--team' => (string) $team->id])
            ->assertSuccessful();

        $contact->refresh();
        $this->assertTrue($contact->allowsInboundChatAssistant());
    }

    public function test_chat_assistant_phone_requires_team_when_ambiguous(): void
    {
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $owner = User::factory()->create();
        $teamA = Team::factory()->create(['user_id' => $owner->id]);
        $teamB = Team::factory()->create(['user_id' => $owner->id]);
        $owner->teams()->attach($teamA->id, ['role' => 'admin']);
        $owner->teams()->attach($teamB->id, ['role' => 'admin']);

        Contact::factory()->create([
            'team_id' => $teamA->id,
            'user_id' => null,
            'phone' => '34600111222',
            'creator_id' => $owner->id,
            'responsible_id' => $owner->id,
        ]);
        Contact::factory()->create([
            'team_id' => $teamB->id,
            'user_id' => null,
            'phone' => '34600111222',
            'creator_id' => $owner->id,
            'responsible_id' => $owner->id,
        ]);

        $this->artisan('chat:assistant-phone', ['state' => 'off', 'phone' => '34600111222'])
            ->assertFailed();
    }
}
