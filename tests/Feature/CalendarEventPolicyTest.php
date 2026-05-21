<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\Team;
use App\Models\User;
use App\Services\AssistantToolsService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CalendarEventPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(LanguageSeeder::class);
        $this->seed(ContactStatusSeeder::class);
    }

    public function test_client_role_can_create_calendar_events_in_app_and_via_assistant(): void
    {
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $client = User::factory()->create();
        $client->teams()->attach($team->id, ['role' => 'client']);
        $client->assignRole('client');
        $client->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($client);
        $this->assertTrue($client->can('create', CalendarEvent::class));

        $tools = app(AssistantToolsService::class);
        $tools->clearRequestContext();
        $tools->setRequestContext($client->id, $team->id, null);

        $out = $tools->execute('create_calendar_event', [
            'title' => 'Reunión de seguimiento',
            'start' => '2026-05-22 10:00:00',
            'end' => '2026-05-22 11:00:00',
        ]);

        $this->assertStringContainsString('Calendar event created', $out);
    }

    public function test_client_can_create_contact_for_meeting_guest_via_assistant(): void
    {
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $client = User::factory()->create();
        $client->teams()->attach($team->id, ['role' => 'client']);
        $client->assignRole('client');
        $client->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($client);
        $this->assertTrue($client->can('create', \App\Models\Contact::class));

        $tools = app(\App\Services\AssistantToolsService::class);
        $tools->clearRequestContext();
        $tools->setRequestContext($client->id, $team->id, null);

        $search = $tools->execute('search_contacts', ['query' => 'Pepe']);
        $this->assertStringContainsString('No contacts found', $search);

        $created = $tools->execute('create_contact', ['name' => 'Pepe']);
        $this->assertStringContainsString('Contact created', $created);
    }

    public function test_guest_team_member_cannot_create_calendar_events(): void
    {
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $guest = User::factory()->create();
        $guest->teams()->attach($team->id, ['role' => 'guest']);
        $guest->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($guest);
        $this->assertFalse($guest->can('create', CalendarEvent::class));
    }
}
