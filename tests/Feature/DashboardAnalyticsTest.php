<?php

namespace Tests\Feature;

use App\Enums\ContactInteractionType;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\ContactInteraction;
use App\Models\Enterprise;
use App\Models\List60;
use App\Models\List60Status;
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
use Database\Seeders\List60StatusesSeeder;
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

    public function test_dashboard_does_not_show_invoice_summary_cards(): void
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

        $this->actingAs($user);
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Pendientes de pago', false);
        $response->assertDontSee('Vencidas', false);
        $response->assertDontSee(__('app.invoice_summary_expenses_title'), false);
        $response->assertDontSee(__('app.invoice_summary_profit_title'), false);
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
        $response->assertSee('dashboard-insight-actions', false);
        $response->assertSee(route('strategy.index'), false);
        $response->assertSee(__('app.weekly_plan_strategy_link'), false);
        $response->assertSee('@container (max-width: 26rem)', false);
        $response->assertSee('col-md-4 order-md-2', false);
        $response->assertDontSee('col-lg-4 order-lg-2', false);
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
        $response->assertSee('data-dashboard-panel="interactions-breakdown" aria-pressed="true"', false);
        $response->assertSee('dashboardContactInteractionsTrendChart', false);
        $response->assertSee("showPanel('interactions-breakdown')", false);
        $response->assertSee('data-panel="latest-contacts"', false);
        $response->assertSee(__('app.dashboard_interactions_chart_subtitle'), false);

        preg_match('/const interactionsTrendData = (\{.*?\});/s', $response->getContent(), $interactionsMatch);
        $this->assertNotEmpty($interactionsMatch[1] ?? null);
        $interactions = json_decode($interactionsMatch[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $interactions['total']);
        $this->assertNotEmpty($interactions['series']);

        preg_match('/const trendData = (\{.*?\});/s', $response->getContent(), $trendMatch);
        $this->assertNotEmpty($trendMatch[1] ?? null);
        $trend = json_decode($trendMatch[1], true, 512, JSON_THROW_ON_ERROR);
        $yesterdayIndex = count($trend['values']) - 2;
        $this->assertSame(3, $trend['values'][$yesterdayIndex]);
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

        $this->assertSame(2, $comparisons['contacts-trend']['current']);
        $this->assertSame(1, $comparisons['contacts-trend']['previous']);
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

    public function test_dashboard_keeps_overdue_list60_calls_on_today_and_future_calls_upcoming(): void
    {
        Carbon::setTestNow('2026-05-19 12:00:00');

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            EnterpriseTypeSeeder::class,
            List60StatusesSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        foreach (['calendar', 'list60'] as $moduleKey)
        {
            Module::query()->firstOrCreate(
                ['key' => $moduleKey],
                [
                    'name' => $moduleKey,
                    'icon' => 'calendar-event',
                    'description' => $moduleKey,
                    'status' => 1,
                ],
            );
            $team->enableModule($moduleKey);
        }

        $overdue = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Ana',
            'surname' => 'Pendiente',
            'responsible_id' => $user->id,
            'creator_id' => $user->id,
        ]);
        $future = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Bruno',
            'surname' => 'Manana',
            'responsible_id' => $user->id,
            'creator_id' => $user->id,
        ]);

        $statusId = List60Status::query()->value('id');

        List60::query()->create([
            'contact_id' => $overdue->id,
            'type_id' => 1,
            'date_next' => '2026-05-16 09:00:00',
            'status_id' => $statusId,
            'responsible_id' => $user->id,
        ]);
        List60::query()->create([
            'contact_id' => $future->id,
            'type_id' => 1,
            'date_next' => '2026-05-22 09:00:00',
            'status_id' => $statusId,
            'responsible_id' => $user->id,
        ]);

        CalendarEvent::query()->create([
            'team_id' => $team->id,
            'title' => 'Reunion de ayer',
            'start' => Carbon::parse('2026-05-18 10:00:00'),
            'end' => Carbon::parse('2026-05-18 11:00:00'),
            'all_day' => false,
            'label' => 'Business',
        ]);

        $this->actingAs($user);
        $content = $this->get(route('dashboard'))->assertOk()->getContent();

        $todayPane = $this->dashboardCalendarPane($content, 'dashboard-cal-pane-today', 'dashboard-cal-pane-upcoming');
        $upcomingPane = $this->dashboardCalendarPane($content, 'dashboard-cal-pane-upcoming', null);

        $this->assertStringContainsString('Ana Pendiente', $todayPane);
        $this->assertStringContainsString(__('app.dashboard_calendar_follow_up'), $todayPane);
        $this->assertStringNotContainsString('Bruno Manana', $todayPane);
        $this->assertStringNotContainsString('Reunion de ayer', $todayPane);

        $this->assertStringContainsString('Bruno Manana', $upcomingPane);
        $this->assertStringNotContainsString('Ana Pendiente', $upcomingPane);
        $this->assertStringNotContainsString('<th', $todayPane);

        Carbon::setTestNow();
    }

    public function test_dashboard_today_calls_show_only_the_first_four(): void
    {
        Carbon::setTestNow('2026-05-19 12:00:00');

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            EnterpriseTypeSeeder::class,
            List60StatusesSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        foreach (['calendar', 'list60'] as $moduleKey)
        {
            Module::query()->firstOrCreate(
                ['key' => $moduleKey],
                [
                    'name' => $moduleKey,
                    'icon' => 'calendar-event',
                    'description' => $moduleKey,
                    'status' => 1,
                ],
            );
            $team->enableModule($moduleKey);
        }

        $statusId = List60Status::query()->value('id');

        foreach (range(1, 5) as $index)
        {
            $contact = Contact::factory()->create([
                'team_id' => $team->id,
                'name' => 'Cola',
                'surname' => 'Numero'.$index,
                'responsible_id' => $user->id,
                'creator_id' => $user->id,
            ]);

            List60::query()->create([
                'contact_id' => $contact->id,
                'type_id' => 1,
                'date_next' => Carbon::parse('2026-05-19')->subDays(6 - $index)->toDateTimeString(),
                'status_id' => $statusId,
                'responsible_id' => $user->id,
            ]);
        }

        $this->actingAs($user);
        $content = $this->get(route('dashboard'))->assertOk()->getContent();
        $todayPane = $this->dashboardCalendarPane($content, 'dashboard-cal-pane-today', 'dashboard-cal-pane-upcoming');

        foreach (range(1, 4) as $index)
        {
            $this->assertStringContainsString('Cola Numero'.$index, $todayPane);
        }

        $this->assertStringNotContainsString('Cola Numero5', $todayPane);
        $this->assertStringNotContainsString(__('app.dashboard_calendar_col_event'), $todayPane);

        Carbon::setTestNow();
    }

    private function dashboardCalendarPane(string $html, string $startId, ?string $endId): string
    {
        $start = strpos($html, 'id="'.$startId.'"');
        $this->assertNotFalse($start);

        $end = $endId === null ? strlen($html) : strpos($html, 'id="'.$endId.'"', $start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
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

        $older = Carbon::today()->subDays(2)->format('Y-m-d');
        $newer = Carbon::today()->subDays(1)->format('Y-m-d');
        $fakeData = collect([
            [
                'date' => Carbon::today()->subDay(),
                'activeUsers' => 15,
                'screenPageViews' => 30,
                'pageTitle' => 'Pricing',
                'fullPageUrl' => 'example.com/pricing',
                'country' => 'Argentina',
                'newVsReturning' => 'returning',
            ],
            [
                'date' => Carbon::today()->subDays(2),
                'activeUsers' => 10,
                'screenPageViews' => 25,
                'pageTitle' => 'Home',
                'fullPageUrl' => 'example.com/',
                'country' => 'Spain',
                'newVsReturning' => 'new',
            ],
        ]);
        Analytics::fake($fakeData);

        $this->actingAs($user);
        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('analyticsChart', false);
        $chart = $response->getContent();
        $this->assertNotFalse(strpos($chart, $older));
        $this->assertLessThan(strpos($chart, $newer), strpos($chart, $older));
        $response->assertSee(__('Visitantes'), false);
        $response->assertSee(__('Páginas vistas'), false);
        $response->assertSee(__('Páginas top'), false);
        $response->assertSee(__('Países top'), false);
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

    public function test_creating_a_lead_refreshes_the_cached_contacts_trend(): void
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

        $this->actingAs($user);
        $this->get(route('dashboard'))->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has("dashboard.aggregates.{$team->id}"));

        Contact::factory()->create([
            'team_id' => $team->id,
            'responsible_id' => $user->id,
            'creator_id' => $user->id,
            'status_id' => 1,
            'created_at' => Carbon::now(),
        ]);
        Contact::factory()->create([
            'team_id' => $team->id,
            'responsible_id' => $user->id,
            'creator_id' => $user->id,
            'status_id' => 5,
            'created_at' => Carbon::now(),
        ]);

        $this->assertFalse(\Illuminate\Support\Facades\Cache::has("dashboard.aggregates.{$team->id}"));

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        preg_match('/const trendData = (\{.*?\});/s', $response->getContent(), $trendMatch);
        $this->assertNotEmpty($trendMatch[1] ?? null);
        $trend = json_decode($trendMatch[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(2, $trend['values'][count($trend['values']) - 1]);
    }

    public function test_root_dashboard_shows_usage_billing_attentions_for_draft_invoices(): void
    {
        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('root');

        $clientTeam = \App\Models\Team::factory()->create(['name' => 'Cliente Cobro SL']);
        $otherTeam = \App\Models\Team::factory()->create(['name' => 'Otro Cliente SA']);

        \App\Models\TeamUsageInvoice::factory()->create([
            'team_id' => $clientTeam->id,
            'status' => \App\Models\TeamUsageInvoice::STATUS_DRAFT,
            'billed_cents' => 1250,
            'currency' => 'EUR',
            'stripe_invoice_id' => 'in_test_draft_attention',
            'period_from' => now()->subMonths(2)->startOfMonth(),
            'period_to' => now()->subMonth()->startOfMonth(),
        ]);

        \App\Models\TeamUsageInvoice::factory()->create([
            'team_id' => $otherTeam->id,
            'status' => \App\Models\TeamUsageInvoice::STATUS_DRAFT,
            'billed_cents' => 4500,
            'currency' => 'EUR',
            'stripe_invoice_id' => 'in_test_draft_second',
            'period_from' => now()->subMonths(2)->startOfMonth(),
            'period_to' => now()->subMonth()->startOfMonth(),
        ]);

        \App\Models\TeamUsageInvoice::factory()->create([
            'team_id' => $otherTeam->id,
            'status' => \App\Models\TeamUsageInvoice::STATUS_DRAFT,
            'billed_cents' => 999,
            'currency' => 'EUR',
            'stripe_invoice_id' => null,
            'period_from' => now()->subMonths(3)->startOfMonth(),
            'period_to' => now()->subMonths(2)->startOfMonth(),
        ]);

        \App\Models\TeamUsageInvoice::factory()->create([
            'team_id' => $clientTeam->id,
            'status' => \App\Models\TeamUsageInvoice::STATUS_OPEN,
            'billed_cents' => 8000,
            'currency' => 'EUR',
            'stripe_invoice_id' => 'in_test_open_hidden',
            'period_from' => now()->subMonths(3)->startOfMonth(),
            'period_to' => now()->subMonths(2)->startOfMonth(),
        ]);

        $this->actingAs($user);
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Cobros de consumo', false);
        $response->assertSee('Borradores de Stripe (todos los equipos)', false);
        $response->assertSee(__('View drafts'), false);
        $response->assertSee(route('invoice.index', ['summary_filter' => 'draft'], false), false);
        $response->assertSee('Cliente Cobro SL', false);
        $response->assertSee('Otro Cliente SA', false);
        $response->assertSee('12,50', false);
        $response->assertSee('45,00', false);
        $response->assertSee('in_test_draft_attention', false);
        $response->assertSee('in_test_draft_second', false);
        $response->assertDontSee('in_test_open_hidden', false);
        $response->assertDontSee('9,99', false);
    }

    public function test_root_dashboard_hides_usage_drafts_already_issued_as_invoices(): void
    {
        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('root');

        $issuedTeam = \App\Models\Team::factory()->create(['name' => 'Ya Facturado SL']);
        $pendingTeam = \App\Models\Team::factory()->create(['name' => 'Sigue Borrador SL']);

        $issued = \App\Models\TeamUsageInvoice::factory()->create([
            'team_id' => $issuedTeam->id,
            'status' => \App\Models\TeamUsageInvoice::STATUS_DRAFT,
            'billed_cents' => 7769,
            'currency' => 'EUR',
            'stripe_invoice_id' => 'in_already_issued',
            'period_from' => now()->subMonth()->startOfMonth(),
            'period_to' => now()->startOfMonth(),
        ]);

        \App\Models\InvoiceSync::query()->create([
            'team_id' => $issuedTeam->id,
            'provider' => 'stripe',
            'external_id' => 'in_already_issued',
            'status' => 'paid',
            'paid' => true,
            'number' => 'A-2026-014',
            'currency' => 'eur',
        ]);

        \App\Models\TeamUsageInvoice::factory()->create([
            'team_id' => $pendingTeam->id,
            'status' => \App\Models\TeamUsageInvoice::STATUS_DRAFT,
            'billed_cents' => 100,
            'currency' => 'EUR',
            'stripe_invoice_id' => 'in_still_draft',
            'period_from' => now()->subMonth()->startOfMonth(),
            'period_to' => now()->startOfMonth(),
        ]);

        \App\Models\InvoiceSync::query()->create([
            'team_id' => $pendingTeam->id,
            'provider' => 'stripe',
            'external_id' => 'in_still_draft',
            'status' => 'draft',
            'paid' => false,
            'number' => null,
            'currency' => 'eur',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Sigue Borrador SL', false)
            ->assertDontSee('Ya Facturado SL', false)
            ->assertDontSee('77,69', false);

        $this->assertSame(\App\Models\TeamUsageInvoice::STATUS_PAID, $issued->fresh()->status);
    }

    public function test_root_as_guest_on_another_team_hides_usage_billing_attentions(): void
    {
        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);

        $root = User::factory()->withPersonalTeam()->create();
        $homeTeam = $root->ownedTeams()->first();
        $root->forceFill(['current_team_id' => $homeTeam->id])->save();
        $root->assignRole('root');

        $customerOwner = User::factory()->withPersonalTeam()->create(['name' => 'Respuestos Owner']);
        $customerTeam = $customerOwner->ownedTeams()->first();
        $customerTeam->forceFill(['name' => 'Respuestos AV'])->save();

        $customerTeam->users()->attach($root, ['role' => 'guest']);
        $root->forceFill(['current_team_id' => $customerTeam->id])->save();

        \App\Models\TeamUsageInvoice::factory()->create([
            'team_id' => $customerTeam->id,
            'status' => \App\Models\TeamUsageInvoice::STATUS_DRAFT,
            'billed_cents' => 7769,
            'currency' => 'EUR',
            'stripe_invoice_id' => 'in_guest_should_hide',
            'period_from' => now()->subMonth()->startOfMonth(),
            'period_to' => now()->startOfMonth(),
        ]);

        $this->actingAs($root);
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Cobros de consumo', false);
        $response->assertDontSee('in_guest_should_hide', false);
    }

    public function test_non_root_dashboard_hides_usage_billing_attentions(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        \App\Models\TeamUsageInvoice::factory()->create([
            'team_id' => $team->id,
            'status' => \App\Models\TeamUsageInvoice::STATUS_DRAFT,
            'billed_cents' => 9999,
        ]);

        $this->actingAs($user);
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Cobros de consumo', false);
    }
}
