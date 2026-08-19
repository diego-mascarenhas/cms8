<?php

namespace Tests\Feature;

use App\Enums\ContactInteractionType;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\ContactInteraction;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Analytics\Facades\Analytics;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function grantContactDashboardPermissions(User $user): void
    {
        foreach (['contact.list', 'contact.show'] as $permission)
        {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $user->givePermissionTo($permission);
        }
    }

    public function test_dashboard_shows_invoice_summary_cards_when_invoices_module_enabled(): void
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::query()->firstOrCreate(
            ['key' => 'invoices'],
            [
                'name' => 'Invoices',
                'icon' => 'file-invoice',
                'description' => 'Team invoices',
                'status' => 1,
            ],
        );
        $team->enableModule('invoices');

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        \App\Models\Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-UNPAID',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 1,
        ]);

        \App\Models\Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-OVERDUE',
            'date' => now()->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'gross_amount' => 50,
            'discount' => 0,
            'total_amount' => 50,
            'balance' => 50,
            'status' => 2,
        ]);

        $this->actingAs($user);
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Pendientes de pago', false);
        $response->assertSee('Vencidas', false);
        $response->assertSee('Gastos', false);
        $response->assertSee('Beneficio anual', false);
        $response->assertSee('1 factura', false);
        $response->assertSee(route('invoice.index', ['summary_filter' => 'unpaid']), false);
        $response->assertSee(route('invoice.index', ['summary_filter' => 'overdue']), false);
        $response->assertSee(route('finance-dashboard.projection', ['year' => now()->year]), false);
        $response->assertDontSee('Notas de crédito', false);
    }

    public function test_dashboard_shows_invoice_summary_cards_in_english_locale(): void
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::query()->firstOrCreate(
            ['key' => 'invoices'],
            [
                'name' => 'Invoices',
                'icon' => 'file-invoice',
                'description' => 'Team invoices',
                'status' => 1,
            ],
        );
        $team->enableModule('invoices');

        app()->setLocale('en');
        session()->put('locale', 'en');

        $this->actingAs($user);
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(__('app.invoice_summary_unpaid_title'), false);
        $response->assertSee(__('app.invoice_summary_overdue_title'), false);
        $response->assertSee(__('app.invoice_summary_expenses_title'), false);
        $response->assertSee(__('app.invoice_summary_profit_title'), false);
    }

    public function test_dashboard_hides_invoice_summary_cards_when_invoices_module_disabled(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $this->actingAs($user);
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Pendientes de pago', false);
    }

    public function test_dashboard_does_not_show_analytics_chart_when_team_has_no_analytics(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('analyticsChart', false);
        $response->assertSee(__('app.dashboard_panel_contacts_trend_title'), false);
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
        $this->grantContactDashboardPermissions($user);

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
            'status_id' => 1,
            'created_at' => Carbon::now()->subDay(),
        ]);

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'responsible_id' => $user->id,
            'creator_id' => $user->id,
            'status_id' => 2,
            'created_at' => Carbon::now()->subDay(),
        ]);

        ContactInteraction::factory()->create([
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'type' => ContactInteractionType::Call,
            'occurred_at' => Carbon::now()->subDay(),
        ]);

        $this->actingAs($user);
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(__('app.dashboard_contacts_row_total'), false);
        $response->assertSee(__('app.dashboard_metric_new_leads'), false);
        $response->assertSee(__('app.dashboard_metric_recent_activity'), false);
        $response->assertSee('dashboardContactsTrendChart', false);
        $response->assertSee('dashboardContactStatusChart', false);
        $response->assertSee('data-dashboard-panel="contacts-trend"', false);
        $response->assertSee('data-dashboard-panel="status-breakdown"', false);
        $response->assertSee('data-dashboard-panel="latest-contacts"', false);
        $response->assertSee('data-dashboard-panel="interactions-breakdown"', false);
        $response->assertSee('dashboardContactInteractionsTrendChart', false);
        $response->assertSee('data-panel="latest-contacts"', false);
        $response->assertSee(__('app.dashboard_contacts_chart_subtitle_30'), false);

        preg_match('/const interactionsTrendData = (\{.*?\});/s', $response->getContent(), $interactionsMatch);
        $this->assertNotEmpty($interactionsMatch[1] ?? null);
        $interactions = json_decode($interactionsMatch[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $interactions['total']);
        $this->assertNotEmpty($interactions['series']);

        preg_match('/const trendData = (\{.*?\});/s', $response->getContent(), $trendMatch);
        $this->assertNotEmpty($trendMatch[1] ?? null);
        $trend = json_decode($trendMatch[1], true, 512, JSON_THROW_ON_ERROR);
        $yesterdayIndex = count($trend['values']) - 2;
        $this->assertSame(2, $trend['values'][$yesterdayIndex]);
    }

    public function test_dashboard_includes_month_comparison_for_contact_panels(): void
    {
        Carbon::setTestNow('2026-05-18 12:00:00');

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
        $this->grantContactDashboardPermissions($user);

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
            'status_id' => 1,
            'created_at' => Carbon::parse('2026-05-10 10:00:00'),
        ]);

        Contact::factory()->create([
            'team_id' => $team->id,
            'responsible_id' => $user->id,
            'creator_id' => $user->id,
            'status_id' => 2,
            'created_at' => Carbon::parse('2026-04-15 10:00:00'),
        ]);

        $this->actingAs($user);
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('dashboardContactPanelMonthChange', false);
        $response->assertSee('panelMonthComparisons', false);

        preg_match('/const panelMonthComparisons = (\{.*?\});/s', $response->getContent(), $comparisonMatch);
        $this->assertNotEmpty($comparisonMatch[1] ?? null);
        $comparisons = json_decode($comparisonMatch[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(2, $comparisons['status-breakdown']['current']);
        $this->assertSame(1, $comparisons['status-breakdown']['previous']);
        $this->assertSame(1, $comparisons['status-breakdown']['difference']);
        $this->assertEquals(100.0, $comparisons['status-breakdown']['percent_change']);
        $this->assertSame('up', $comparisons['status-breakdown']['direction']);

        Carbon::setTestNow();
    }

    public function test_dashboard_shows_calendar_tabs_with_today_events(): void
    {
        Carbon::setTestNow('2026-05-18 12:00:00');

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::query()->firstOrCreate(
            ['key' => 'calendar'],
            [
                'name' => 'Calendar',
                'icon' => 'calendar-event',
                'description' => 'Team calendar',
                'status' => 1,
            ],
        );
        $team->enableModule('calendar');

        CalendarEvent::query()->create([
            'team_id' => $team->id,
            'title' => 'Dashboard calendar event',
            'start' => Carbon::parse('2026-05-18 10:00:00'),
            'end' => Carbon::parse('2026-05-18 11:00:00'),
            'all_day' => false,
            'label' => 'Business',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(__('app.dashboard_calendar_tab_today'), false);
        $response->assertSee(__('app.dashboard_calendar_tab_upcoming'), false);
        $response->assertSee(__('app.dashboard_calendar_tab_calendar'), false);
        $response->assertSee('Dashboard calendar event', false);
        $response->assertSee('dashboard-cal-pane-today', false);
        $response->assertSee('id="dashboard-cal-link-calendar"', false);
        $response->assertSee(route('app-calendar'), false);
        $response->assertDontSee('dashboard-cal-pane-calendar', false);

        Carbon::setTestNow();
    }

    public function test_dashboard_panel_triggers_work_without_contact_list_permission(): void
    {
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('collaborator');

        $this->actingAs($user);
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('data-dashboard-panel="contacts-trend"', false);
        $response->assertSee('data-dashboard-panel="status-breakdown"', false);
        $response->assertSee('data-dashboard-panel="latest-contacts"', false);
    }

    public function test_dashboard_shows_latest_registered_contacts_in_panel(): void
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
        $this->grantContactDashboardPermissions($user);

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

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'responsible_id' => $user->id,
            'creator_id' => $user->id,
            'name' => 'PanelTest',
            'surname' => 'Contact',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('PanelTest Contact', false);
        $response->assertSee('dashboard-contact-panel', false);
        $response->assertSee('dashboardLatestContactsTable', false);
    }

    public function test_dashboard_ongoing_projects_table_links_to_project_and_client(): void
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ProjectStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::query()->firstOrCreate(
            ['key' => 'projects'],
            [
                'name' => 'Projects',
                'icon' => 'folder',
                'description' => 'Team projects',
                'status' => 1,
            ],
        );
        $team->enableModule('projects');

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Kydep',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $project = Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'name' => 'Presupuesto de Reestructuración Web',
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);

        $this->actingAs($user);
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(__('Ongoing Projects'), false);
        $response->assertSee(route('project.show', $project->id), false);
        $response->assertSee(route('client.show', $enterprise->id), false);
        $response->assertSee('Presupuesto de Reestructuración Web', false);
        $response->assertSee('Kydep', false);
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
        $response->assertSee(__('team_settings.groups.analytics.title'), false);
        $response->assertSee(__('team_settings.fields.analytics_property_id.label'), false);
        $response->assertSee(__('team_settings.fields.analytics_credentials_json.label'), false);
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

    public function test_dashboard_reuses_cached_aggregates_on_second_request(): void
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
        $this->grantContactDashboardPermissions($user);

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

        Contact::factory()->count(3)->create([
            'team_id' => $team->id,
            'responsible_id' => $user->id,
            'creator_id' => $user->id,
            'status_id' => 1,
            'created_at' => Carbon::now()->subDay(),
        ]);

        $this->actingAs($user);

        $this->get(route('dashboard'))->assertOk();

        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();

        $this->get(route('dashboard'))->assertOk();

        $contactDateGroupQueries = collect(\Illuminate\Support\Facades\DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'date(created_at)'))
            ->count();

        $this->assertSame(0, $contactDateGroupQueries);
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has("dashboard.aggregates.{$team->id}"));
    }
}
