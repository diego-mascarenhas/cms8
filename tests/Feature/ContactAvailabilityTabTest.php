<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactAvailabilityTabTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ContactStatusSeeder::class, CountrySeeder::class, LanguageSeeder::class]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);
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

    public function test_admin_and_collaborator_see_the_availability_tab(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $admin->assignRole('admin');
        $team = $admin->ownedTeams()->first();
        $team->enableModule('contacts');
        $admin->forceFill(['current_team_id' => $team->id])->save();

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Cliente',
            'email' => 'cliente@example.com',
            'creator_id' => $admin->id,
            'responsible_id' => $admin->id,
            'status_id' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('contact.show', $contact->id))
            ->assertOk()
            ->assertSee('id="availability-tab"', false)
            ->assertSee('Disponibilidad', false);

        $collaborator = User::factory()->create();
        $collaborator->assignRole('collaborator');
        $team->users()->attach($collaborator, ['role' => 'collaborator']);
        $collaborator->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($collaborator)
            ->get(route('contact.show', $contact->id))
            ->assertOk()
            ->assertSee('id="availability-tab"', false);
    }
}
