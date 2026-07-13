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

class CollaboratorCannotChangeAdvisorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ContactStatusSeeder::class, CountrySeeder::class, LanguageSeeder::class]);

        foreach (['admin', 'collaborator'] as $roleName)
        {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

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

    public function test_collaborator_cannot_change_contact_advisor_on_update(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $admin->assignRole('admin');
        $team = $admin->ownedTeams()->first();
        $team->enableModule('contacts');
        $admin->forceFill(['current_team_id' => $team->id])->save();

        $collaborator = User::factory()->create();
        $collaborator->assignRole('collaborator');
        $team->users()->attach($collaborator->id, ['role' => 'collaborator']);
        $collaborator->forceFill(['current_team_id' => $team->id])->save();

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Keep',
            'surname' => 'Advisor',
            'email' => 'keep.advisor@example.com',
            'creator_id' => $admin->id,
            'responsible_id' => $admin->id,
            'status_id' => 1,
            'country' => 724,
            'language' => 'es',
        ]);

        $this->assertFalse($collaborator->can('assignAdvisor', $contact));

        $payload = [
            'name' => 'Keep',
            'surname' => 'Advisor',
            'email' => 'keep.advisor@example.com',
            'birthday' => null,
            'status_id' => 1,
            'country' => '724',
            'language' => 'es',
            'responsible_id' => $collaborator->id,
        ];

        $this->actingAs($collaborator)
            ->put(route('contact.update', $contact->id), $payload)
            ->assertRedirect();

        $this->assertSame($admin->id, $contact->fresh()->responsible_id);
    }

    public function test_admin_can_change_contact_advisor_on_update(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $admin->assignRole('admin');
        $team = $admin->ownedTeams()->first();
        $team->enableModule('contacts');
        $admin->forceFill(['current_team_id' => $team->id])->save();

        $collaborator = User::factory()->create();
        $collaborator->assignRole('collaborator');
        $team->users()->attach($collaborator->id, ['role' => 'collaborator']);

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Switch',
            'surname' => 'Advisor',
            'email' => 'switch.advisor@example.com',
            'creator_id' => $admin->id,
            'responsible_id' => $admin->id,
            'status_id' => 1,
            'country' => 724,
            'language' => 'es',
        ]);

        $payload = [
            'name' => 'Switch',
            'surname' => 'Advisor',
            'email' => 'switch.advisor@example.com',
            'birthday' => null,
            'status_id' => 1,
            'country' => '724',
            'language' => 'es',
            'responsible_id' => $collaborator->id,
        ];

        $this->actingAs($admin)
            ->put(route('contact.update', $contact->id), $payload)
            ->assertRedirect();

        $this->assertSame($collaborator->id, $contact->fresh()->responsible_id);
    }
}
