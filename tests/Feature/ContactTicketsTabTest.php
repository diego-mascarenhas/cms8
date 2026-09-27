<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Module;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactTicketsTabTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ContactStatusSeeder::class, CountrySeeder::class, LanguageSeeder::class]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            [
                'name' => 'Contacts',
                'icon' => 'users',
                'description' => 'CRM contacts',
                'status' => 1,
            ],
        );
    }

    public function test_contact_profile_lists_that_clients_tickets(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $admin->assignRole('admin');
        $team = $admin->ownedTeams()->first();
        $team->enableModule('contacts');
        $admin->forceFill(['current_team_id' => $team->id])->save();

        $client = User::factory()->create([
            'email' => 'cliente@example.com',
        ]);

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Cliente',
            'email' => 'cliente@example.com',
            'user_id' => $client->id,
            'creator_id' => $admin->id,
            'responsible_id' => $admin->id,
            'status_id' => 1,
        ]);

        Ticket::factory()->create([
            'team_id' => $team->id,
            'user_id' => $client->id,
            'subject' => 'Prueba desde mobile',
            'status' => 'in_progress',
            'priority' => 'low',
        ]);

        Ticket::factory()->create([
            'team_id' => $team->id,
            'subject' => 'Ticket de otra persona',
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->get(route('contact.show', $contact->id))
            ->assertOk()
            ->assertSee('id="tickets-tab"', false)
            ->assertDontSee('id="emotional-balance-tab"', false)
            ->assertSee('Histórico emocional', false)
            ->assertSee('Aún no se registró ningún estado emocional.', false)
            ->assertSee('Prueba desde mobile')
            ->assertDontSee('Ticket de otra persona');
    }
}
