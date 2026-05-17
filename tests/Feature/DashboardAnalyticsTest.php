<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Analytics\Facades\Analytics;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_does_not_show_analytics_chart_when_team_has_no_analytics(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('analyticsChart', false);
        $response->assertSee(__('Recent contact activity'), false);
    }

    public function test_dashboard_shows_contact_summary_metrics_and_trend_chart(): void
    {
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            [
                'name' => 'Contacts',
                'icon' => 'users',
                'description' => 'CRM contacts',
                'status' => 1,
            ],
        );
        $team->enableModule('contacts');

        Contact::factory()->count(2)->create([
            'team_id' => $team->id,
            'responsible_id' => $user->id,
            'creator_id' => $user->id,
            'created_at' => Carbon::now()->subDay(),
        ]);

        $this->actingAs($user);
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(__('app.dashboard_contacts_row_total'), false);
        $response->assertSee(__('app.dashboard_metric_new_leads'), false);
        $response->assertSee(__('app.dashboard_metric_recent_activity'), false);
        $response->assertSee('dashboardContactsTrendChart', false);
        $this->assertMatchesRegularExpression('/text-primary[^>]*>2</', $response->getContent());
        $this->assertMatchesRegularExpression('/text-success[^>]*>2</', $response->getContent());
    }

    public function test_dashboard_shows_clients_metric_when_clients_module_enabled(): void
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::query()->firstOrCreate(
            ['key' => 'clients'],
            [
                'name' => 'Clients',
                'icon' => 'user-heart',
                'description' => 'CRM clients',
                'status' => 1,
            ],
        );
        $team->enableModule('clients');

        Enterprise::withoutEvents(fn () => Enterprise::factory()->count(3)->forTeam($team->id)->create([
            'type_id' => 1,
            'status_id' => 1,
            'payment_type_id' => null,
            'invoice_type_id' => null,
        ]));

        $this->actingAs($user);
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(__('app.clients'), false);
        $response->assertSee('ti-user-heart', false);
        $this->assertMatchesRegularExpression('/text-danger[^>]*>3</', $response->getContent());
    }

    public function test_dashboard_hides_ongoing_projects_card_when_projects_module_disabled(): void
    {
        Module::query()->create([
            'name' => 'Projects',
            'key' => 'projects',
            'icon' => 'folder',
            'description' => 'Test',
            'is_core' => true,
            'status' => 1,
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee(__('Ongoing Projects'), false);
    }

    public function test_dashboard_shows_analytics_chart_when_team_has_analytics_configured(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $team->setSetting('analytics_property_id', '123456789', ['group' => 'analytics', 'is_encrypted' => false]);
        $team->setSetting('analytics_credentials_json', json_encode(['type' => 'service_account']), ['group' => 'analytics', 'is_encrypted' => true]);

        $fakeData = collect([
            [
                'date' => Carbon::today()->subDays(2),
                'activeUsers' => 10,
                'screenPageViews' => 25,
            ],
            [
                'date' => Carbon::today()->subDays(1),
                'activeUsers' => 15,
                'screenPageViews' => 30,
            ],
        ]);
        Analytics::fake($fakeData);

        $this->actingAs($user);
        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('analyticsChart', false);
    }

    public function test_team_settings_analytics_group_can_be_edited(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->actingAs($user);

        $response = $this->get(route('team-settings.edit', ['team' => $team, 'group' => 'analytics']));

        $response->assertStatus(200);
        $response->assertSee('Google Analytics', false);
        $response->assertSee('GA4 Property ID', false);
        $response->assertSee('Service account credentials', false);
    }

    public function test_team_settings_analytics_can_be_saved(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->actingAs($user);

        $response = $this->put(route('team-settings.update', $team), [
            '_token' => csrf_token(),
            '_method' => 'PUT',
            'analytics' => [
                'analytics_property_id' => '987654321',
                'analytics_credentials_json' => json_encode(['type' => 'service_account', 'project_id' => 'test']),
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals('987654321', $team->fresh()->getSetting('analytics_property_id'));
        $this->assertNotEmpty($team->fresh()->getSetting('analytics_credentials_json'));
    }
}
