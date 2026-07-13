<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactSentimentHistory;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\ContactSentimentSeeder;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\DemoTeamRoleScenariosSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CollaboratorDashboardVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            ContactStatusSeeder::class,
            ContactSentimentSeeder::class,
            CountrySeeder::class,
            LanguageSeeder::class,
        ]);

        foreach (['admin', 'client', 'collaborator'] as $roleName)
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

    public function test_collaborator_dashboard_shows_assigned_contact_metrics(): void
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
        $team->enableModule('contacts');

        for ($i = 1; $i <= 12; $i++)
        {
            $contact = Contact::query()->create([
                'team_id' => $team->id,
                'name' => "Dashboard Lead {$i}",
                'email' => "dashboard.lead{$i}@example.com",
                'language' => 'es',
                'country' => 724,
                'creator_id' => $admin->id,
                'responsible_id' => $admin->id,
                'status_id' => ($i % 5) + 1,
                'created_at' => now()->subDays($i % 6),
            ]);

            ContactSentimentHistory::query()->create([
                'contact_id' => $contact->id,
                'sentiment_id' => ($i % 5) + 1,
                'notes' => 'Demo sentiment',
            ]);
        }

        Contact::query()->create([
            'team_id' => $team->id,
            'name' => 'Carlos',
            'email' => DemoTeamRoleScenariosSeeder::PORTAL_CONTACT_EMAIL,
            'language' => 'es',
            'country' => 724,
            'creator_id' => $admin->id,
            'responsible_id' => $admin->id,
            'status_id' => 1,
        ]);

        $this->seed(DemoTeamRoleScenariosSeeder::class);

        $collaborator = User::query()->where('email', DemoTeamRoleScenariosSeeder::QA_COLLABORATOR_EMAIL)->firstOrFail();

        $assignedCount = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('responsible_id', $collaborator->id)
            ->count();

        $this->assertGreaterThanOrEqual(10, $assignedCount);

        $response = $this->actingAs($collaborator)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('data-dashboard-panel="status-breakdown"', false);
        $response->assertSee('Dashboard Lead', false);
    }
}
