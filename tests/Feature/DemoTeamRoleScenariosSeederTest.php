<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\DemoTeamRoleScenariosSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DemoTeamRoleScenariosSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'client', 'collaborator'] as $roleName)
        {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $this->seed([ContactStatusSeeder::class, CountrySeeder::class, LanguageSeeder::class]);
    }

    public function test_demo_team_role_scenarios_seeder_creates_qa_users(): void
    {
        $admin = User::factory()->withPersonalTeam()->create([
            'email' => 'admin@humano.app',
        ]);
        $admin->assignRole('admin');

        $team = $admin->ownedTeams()->create([
            'name' => 'Demo',
            'personal_team' => false,
        ]);
        $admin->teams()->attach($team->id, ['role' => 'admin']);
        $admin->forceFill(['current_team_id' => $team->id])->save();

        Contact::query()->create([
            'team_id' => $team->id,
            'name' => 'Carlos',
            'surname' => 'García',
            'email' => DemoTeamRoleScenariosSeeder::PORTAL_CONTACT_EMAIL,
            'language' => 'es',
            'country' => 724,
            'creator_id' => $admin->id,
            'responsible_id' => $admin->id,
            'status_id' => 1,
        ]);

        for ($i = 1; $i <= 5; $i++)
        {
            Contact::query()->create([
                'team_id' => $team->id,
                'name' => "Demo Lead {$i}",
                'email' => "demo.lead{$i}@example.com",
                'language' => 'es',
                'country' => 724,
                'creator_id' => $admin->id,
                'responsible_id' => $admin->id,
                'status_id' => 1,
            ]);
        }

        $this->seed(DemoTeamRoleScenariosSeeder::class);

        $client = User::query()->where('email', DemoTeamRoleScenariosSeeder::QA_CLIENT_EMAIL)->first();
        $collaborator = User::query()->where('email', DemoTeamRoleScenariosSeeder::QA_COLLABORATOR_EMAIL)->first();

        $this->assertNotNull($client);
        $this->assertNotNull($collaborator);
        $this->assertTrue($client->hasRole('client'));
        $this->assertFalse($client->hasRole('collaborator'));
        $this->assertTrue($collaborator->hasRole('collaborator'));
        $this->assertFalse($collaborator->canAccessBilling());
        $this->assertFalse($collaborator->canAccessInfrastructure());
        $this->assertTrue($client->hasTeamRole($team, 'client'));

        $linkedContact = Contact::withoutGlobalScopes()
            ->where('email', DemoTeamRoleScenariosSeeder::PORTAL_CONTACT_EMAIL)
            ->first();

        $this->assertSame($client->id, $linkedContact?->user_id);

        $assignedCount = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('responsible_id', $collaborator->id)
            ->count();

        $this->assertGreaterThan(0, $assignedCount);
    }
}
