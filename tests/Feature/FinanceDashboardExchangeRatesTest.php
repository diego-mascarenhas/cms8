<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\Enterprise;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\StripeSubscription;
use App\Models\User;
use App\Services\Finance\FinanceCfoBriefService;
use App\Services\Finance\InvoiceAnalyticsService;
use App\Services\WeeklyAnalysisLauncher;
use App\Services\WeeklyWorkPlanService;
use Carbon\Carbon;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinanceDashboardExchangeRatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_links_to_exchange_rates_and_shows_the_last_update(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();
        $this->actingAs($user);

        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'ARS',
            'rate' => 1500,
            'date' => '2026-07-24',
            'fetched_at' => '2026-10-01 02:00:00',
        ]);
        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => 0.86,
            'date' => '2026-07-24',
            'fetched_at' => '2026-10-01 02:00:00',
        ]);

        $dashboard = $this->get(route('finance-dashboard.index'))
            ->assertOk()
            ->assertSee(route('finance-dashboard.exchange-rates'), false)
            ->assertSee('01/10/2026 04:00', false)
            ->assertSee(__('Accountant link'), false)
            ->assertDontSee('cfoBriefModal', false)
            ->assertDontSee('id="cfo-brief-card"', false);

        $html = $dashboard->getContent();
        $accountantLink = strpos($html, __('Accountant link'));
        $report = strpos($html, __('Report'));
        $this->assertNotFalse($accountantLink);
        $this->assertNotFalse($report);
        $this->assertLessThan($report, $accountantLink);

        $this->get(route('finance-dashboard.exchange-rates', ['year' => 2026]))
            ->assertOk()
            ->assertSee('24/07/2026', false)
            ->assertSee('1.500,0000', false)
            ->assertSee('0,860000', false)
            ->assertSee('1.744,1860', false);
    }

    public function test_cfo_brief_returns_the_suggestion(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();
        $this->actingAs($user);

        $this->mock(FinanceCfoBriefService::class, function ($mock): void
        {
            $mock->shouldReceive('remember')->once()->andReturn('Revisar los sueldos de este mes.');
        });

        $this->postJson(route('finance-dashboard.cfo-brief'), ['year' => 2026])
            ->assertOk()
            ->assertJson(['brief' => 'Revisar los sueldos de este mes.']);
    }

    public function test_cfo_brief_is_stored_for_the_team_and_shown_on_the_global_analysis(): void
    {
        Carbon::setTestNow('2026-10-06 12:00:00');
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->actingAs($user);

        $analysis = json_encode([
            'dafo' => [
                'fortalezas' => 'Margen 58%',
                'debilidades' => '11 mil sin clasificar',
                'oportunidades' => '654 leads',
                'amenazas' => 'Conversion 2,6%',
            ],
            'fifo' => 'Cobrar primero lo vencido.',
            'dagmar' => 'Subir conversion de 2,6% a 5%.',
            'actions' => [
                'Llamar a los 621 leads abiertos.',
                'Clasificar 11295 EUR.',
            ],
            'capacity' => [
                'resources' => 'Falta una persona de soporte.',
                'hours' => 'Hay 40 horas semanales y 12 sin cubrir.',
                'minimum_salary' => 'El salario mínimo que aguanta el margen es 1800 EUR.',
                'now' => 'Con Administración y Técnica se puede sostener el soporte actual.',
                'missing_departments' => 'No hace falta abrir otro departamento.',
            ],
        ], JSON_UNESCAPED_UNICODE);

        $service = \Mockery::mock(FinanceCfoBriefService::class, [
            $this->app->make(InvoiceAnalyticsService::class),
        ])->makePartial();
        $service->shouldReceive('suggest')->twice()->andReturn($analysis, $analysis);
        $this->app->instance(FinanceCfoBriefService::class, $service);

        $this->postJson(route('finance-dashboard.cfo-brief'), ['year' => 2026])
            ->assertOk()
            ->assertJsonPath('brief', fn (string $brief): bool => str_contains($brief, 'Margen 58%')
                && str_contains($brief, 'Llamar a los 621 leads abiertos.'));

        $this->postJson(route('finance-dashboard.cfo-brief'), ['year' => 2026])
            ->assertOk();

        $stored = $team->fresh()->getSetting('finance_cfo_brief_2026');
        $this->assertIsArray($stored);
        $this->assertSame('Margen 58%', $stored['dafo']['fortalezas']);
        $this->assertSame('Cobrar primero lo vencido.', $stored['fifo']);
        $this->assertSame('Subir conversion de 2,6% a 5%.', $stored['dagmar']);
        $this->assertSame([
            'Llamar a los 621 leads abiertos.',
            'Clasificar 11295 EUR.',
        ], $stored['actions']);
        $this->assertSame('Hay 40 horas semanales y 12 sin cubrir.', $stored['capacity']['hours']);

        $this->get(route('finance-dashboard.index', ['year' => 2026]))
            ->assertOk()
            ->assertDontSee('id="cfo-brief-card"', false)
            ->assertDontSee('Margen 58%', false);

        $this->get(route('strategy.index'))
            ->assertOk()
            ->assertDontSee('id="cfo-analysis"', false)
            ->assertSee(route('strategy.analysis'), false);

        $this->get(route('strategy.analysis'))
            ->assertOk()
            ->assertSee(__('app.cfo_analysis_title'), false)
            ->assertSee('Margen 58%', false)
            ->assertSee('Cobrar primero lo vencido.', false)
            ->assertSee('Subir conversion de 2,6% a 5%.', false)
            ->assertSee('Llamar a los 621 leads abiertos.', false)
            ->assertSee('Hay 40 horas semanales y 12 sin cubrir.', false)
            ->assertSee('El salario mínimo que aguanta el margen es 1800 EUR.', false)
            ->assertSee('No hace falta abrir otro departamento.', false)
            ->assertSee(__('app.cfo_analysis_projection'), false)
            ->assertSee(route('help.cfo-analysis'), false)
            ->assertSee(__('Ask the CFO'), false)
            ->assertSee('id="cfo-brief-button"', false)
            ->assertSee(__('Asking the CFO...'), false)
            ->assertSee('spinner-border', false)
            ->assertSee(route('weekly-plan.index'), false);

        $this->get(route('weekly-plan.index'))
            ->assertOk()
            ->assertSee('Llamar a los 621 leads abiertos.', false)
            ->assertSee('Clasificar 11295 EUR.', false);

        $this->post(route('finance-dashboard.cfo-brief'), [
            'year' => 2026,
            'refresh' => 1,
        ])->assertRedirect(route('strategy.analysis'));
    }

    public function test_cfo_brief_accepts_nested_lists_from_the_model(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->actingAs($user);

        $analysis = json_encode([
            'dafo' => [
                'fortalezas' => ['Margen alto', 'Ingresos recurrentes'],
                'debilidades' => ['Gastos sin clasificar'],
                'oportunidades' => '654 leads',
                'amenazas' => ['Conversion baja'],
            ],
            'fifo' => ['Cobrar lo vencido', 'antes que lo nuevo'],
            'dagmar' => ['meta' => 'Subir conversion al 5%'],
            'actions' => [
                ['verbo' => 'Llamar', 'objeto' => 'a los 621 leads'],
                'Clasificar 11295 EUR.',
            ],
            'capacity' => [
                'hours' => ['40 horas asignadas', '12 sin cubrir'],
                'minimum_salary' => ['importe' => '1800 EUR'],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $service = \Mockery::mock(FinanceCfoBriefService::class, [
            $this->app->make(InvoiceAnalyticsService::class),
        ])->makePartial();
        $service->shouldReceive('suggest')->once()->andReturn($analysis);
        $this->app->instance(FinanceCfoBriefService::class, $service);

        $this->postJson(route('finance-dashboard.cfo-brief'), ['year' => 2026])
            ->assertOk();

        $stored = $team->fresh()->getSetting('finance_cfo_brief_2026');
        $this->assertIsArray($stored);
        $this->assertSame('Margen alto Ingresos recurrentes', $stored['dafo']['fortalezas']);
        $this->assertSame('Cobrar lo vencido antes que lo nuevo', $stored['fifo']);
        $this->assertSame('Subir conversion al 5%', $stored['dagmar']);
        $this->assertSame([
            'Llamar a los 621 leads',
            'Clasificar 11295 EUR.',
        ], $stored['actions']);
        $this->assertSame('40 horas asignadas 12 sin cubrir', $stored['capacity']['hours']);
        $this->assertSame('1800 EUR', $stored['capacity']['minimum_salary']);
    }

    public function test_cfo_projection_keeps_invoices_and_does_not_repeat_the_average(): void
    {
        Carbon::setTestNow('2026-10-06 12:00:00');
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $trend = [];

        foreach (range(1, 12) as $month)
        {
            $income = $month <= 2 ? 1000.0 : 0.0;
            $expense = $month <= 2 ? 400.0 : 0.0;
            $trend[] = [
                'label' => 'M',
                'month' => $month,
                'income' => $income,
                'expense' => $expense,
                'profit' => $income - $expense,
            ];
        }

        $analytics = $this->createMock(InvoiceAnalyticsService::class);
        $analytics->method('buildYearReport')->willReturn([
            'year' => 2026,
            'reporting_currency' => 'EUR',
            'monthly_trend' => $trend,
            'scenario' => [
                'avg_monthly_income' => 0,
                'avg_monthly_expense' => 0,
                'avg_monthly_profit' => 0,
            ],
        ]);

        $projection = (new FinanceCfoBriefService($analytics))->projection($team, 2026);

        $this->assertSame(2, $projection['months_with_data']);
        $this->assertEqualsWithDelta(1000.0, $projection['avg_monthly_income'], 0.01);
        $this->assertEqualsWithDelta(400.0, $projection['avg_monthly_expense'], 0.01);
        $this->assertEqualsWithDelta(-4800.0, $projection['year_profit'], 0.01);
        $this->assertCount(12, $projection['points']);
        $this->assertTrue($projection['points'][0]['projected']);
        $this->assertEqualsWithDelta(0.0, $projection['points'][0]['income'], 0.01);
        $this->assertEqualsWithDelta(400.0, $projection['points'][0]['expense'], 0.01);
        $this->assertTrue($projection['points'][0]['expense_projected']);
        $this->assertEqualsWithDelta(400.0, $projection['points'][11]['expense'], 0.01);
        $this->assertTrue($projection['points'][11]['projected']);

        $trend[9]['income'] = 100.0;
        $trend[9]['expense'] = 40.0;
        $trend[9]['profit'] = 60.0;
        $analytics = $this->createMock(InvoiceAnalyticsService::class);
        $analytics->method('buildYearReport')->willReturn([
            'year' => 2026,
            'reporting_currency' => 'EUR',
            'monthly_trend' => $trend,
            'scenario' => [
                'avg_monthly_income' => 0,
                'avg_monthly_expense' => 0,
                'avg_monthly_profit' => 0,
            ],
        ]);

        $withOpenMonth = (new FinanceCfoBriefService($analytics))->projection($team, 2026);

        $this->assertSame(2, $withOpenMonth['months_with_data']);
        $this->assertEqualsWithDelta(1000.0, $withOpenMonth['avg_monthly_income'], 0.01);
        $this->assertFalse($withOpenMonth['points'][0]['projected']);
        $this->assertEqualsWithDelta(100.0, $withOpenMonth['points'][0]['income'], 0.01);
        $this->assertEqualsWithDelta(40.0, $withOpenMonth['points'][0]['expense'], 0.01);
        $this->assertFalse($withOpenMonth['points'][0]['expense_projected']);
        $this->assertTrue($withOpenMonth['points'][1]['expense_projected']);
        $this->assertEqualsWithDelta(0.0, $withOpenMonth['points'][1]['income'], 0.01);
        $this->assertEqualsWithDelta(400.0, $withOpenMonth['points'][1]['expense'], 0.01);
        $this->assertEqualsWithDelta(-4340.0, $withOpenMonth['year_profit'], 0.01);
        $this->assertSame(0.0, $withOpenMonth['contracted_income']);
    }

    public function test_cfo_projection_places_project_finishes_and_annual_renewals(): void
    {
        Carbon::setTestNow('2026-10-06 12:00:00');
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ProjectStatusSeeder::class,
        ]);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'name' => 'Sitio nuevo',
            'price' => 2500,
            'responsible_id' => $user->id,
            'status_id' => ProjectStatus::STATUS_IN_PROGRESS,
            'date_end' => '2027-03-15',
        ]);
        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'name' => 'Con cuotas',
            'price' => 1000,
            'responsible_id' => $user->id,
            'status_id' => ProjectStatus::STATUS_IN_PROGRESS,
            'date_end' => '2027-03-20',
            'data' => [
                'balance_invoices' => [
                    ['due_date' => '2026-11-20', 'amount' => 400, 'charged' => false],
                ],
            ],
        ]);
        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'name' => 'Ya terminó',
            'price' => 900,
            'responsible_id' => $user->id,
            'status_id' => ProjectStatus::STATUS_IN_PROGRESS,
            'date_end' => '2026-09-01',
        ]);

        StripeSubscription::query()->create([
            'stripe_id' => 'sub_annual_hosting',
            'team_id' => $team->id,
            'type' => 'sell',
            'status' => 'active',
            'plan_name' => 'Hosting anual',
            'plan_interval' => 'year',
            'plan_interval_count' => 1,
            'amount_eur' => 120,
            'current_period_end' => '2027-01-10',
            'cancel_at_period_end' => false,
        ]);
        StripeSubscription::query()->create([
            'stripe_id' => 'sub_annual_domain',
            'team_id' => $team->id,
            'type' => 'buy',
            'status' => 'active',
            'plan_name' => 'Dominio anual',
            'plan_interval' => 'year',
            'plan_interval_count' => 1,
            'amount_eur' => 80,
            'current_period_end' => '2027-02-01',
            'cancel_at_period_end' => false,
        ]);
        StripeSubscription::query()->create([
            'stripe_id' => 'sub_annual_lapsed',
            'team_id' => $team->id,
            'type' => 'sell',
            'status' => 'active',
            'plan_name' => 'Renovación vencida',
            'plan_interval' => 'month',
            'plan_interval_count' => 12,
            'amount_eur' => 40,
            'current_period_end' => '2025-04-01',
            'cancel_at_period_end' => false,
        ]);
        StripeSubscription::query()->create([
            'stripe_id' => 'sub_monthly',
            'team_id' => $team->id,
            'type' => 'sell',
            'status' => 'active',
            'plan_name' => 'Mensual',
            'plan_interval' => 'month',
            'plan_interval_count' => 1,
            'amount_eur' => 999,
            'current_period_end' => '2026-11-01',
            'cancel_at_period_end' => false,
        ]);

        $trend = [];

        foreach (range(1, 12) as $month)
        {
            $trend[] = [
                'label' => 'M',
                'month' => $month,
                'income' => 0.0,
                'expense' => 0.0,
                'profit' => 0.0,
            ];
        }

        $analytics = $this->createMock(InvoiceAnalyticsService::class);
        $analytics->method('buildYearReport')->willReturn([
            'year' => 2026,
            'reporting_currency' => 'EUR',
            'monthly_trend' => $trend,
            'scenario' => [
                'avg_monthly_income' => 0,
                'avg_monthly_expense' => 0,
                'avg_monthly_profit' => 0,
            ],
        ]);

        $projection = (new FinanceCfoBriefService($analytics))->projection($team, 2026);

        $this->assertEqualsWithDelta(400.0, $projection['points'][1]['income'], 0.01);
        $this->assertEqualsWithDelta(120.0, $projection['points'][3]['income'], 0.01);
        $this->assertEqualsWithDelta(80.0, $projection['points'][4]['expense'], 0.01);
        $this->assertEqualsWithDelta(3100.0, $projection['points'][5]['income'], 0.01);
        $this->assertEqualsWithDelta(40.0, $projection['points'][6]['income'], 0.01);
        $this->assertEqualsWithDelta(3660.0, $projection['contracted_income'], 0.01);
        $this->assertEqualsWithDelta(80.0, $projection['contracted_expense'], 0.01);
        $this->assertEqualsWithDelta(3580.0, $projection['year_profit'], 0.01);
    }

    public function test_cfo_projection_converts_foreign_amounts_into_the_team_currency(): void
    {
        Carbon::setTestNow('2026-10-06 12:00:00');
        $this->seed([
            CurrencySeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ProjectStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        ExchangeRate::query()->create([
            'base_currency' => 'ARS',
            'target_currency' => 'EUR',
            'rate' => 0.001,
            'date' => '2026-10-01',
            'fetched_at' => '2026-10-01 02:00:00',
        ]);
        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => 0.5,
            'date' => '2026-10-01',
            'fetched_at' => '2026-10-01 02:00:00',
        ]);

        StripeSubscription::query()->create([
            'stripe_id' => 'sub_ars',
            'team_id' => $team->id,
            'type' => 'sell',
            'status' => 'active',
            'plan_name' => 'Hosting en pesos',
            'plan_interval' => 'year',
            'plan_interval_count' => 1,
            'price_currency' => 'ars',
            'amount_total' => 150000,
            'current_period_end' => '2027-01-10',
            'cancel_at_period_end' => false,
        ]);
        StripeSubscription::query()->create([
            'stripe_id' => 'sub_usd',
            'team_id' => $team->id,
            'type' => 'sell',
            'status' => 'active',
            'plan_name' => 'Hosting en dolares',
            'plan_interval' => 'year',
            'plan_interval_count' => 1,
            'price_currency' => 'usd',
            'amount_total' => 100,
            'current_period_end' => '2027-02-01',
            'cancel_at_period_end' => false,
        ]);
        StripeSubscription::query()->create([
            'stripe_id' => 'sub_eur',
            'team_id' => $team->id,
            'type' => 'sell',
            'status' => 'active',
            'plan_name' => 'Hosting en euros',
            'plan_interval' => 'year',
            'plan_interval_count' => 1,
            'price_currency' => 'eur',
            'amount_total' => 30,
            'amount_eur' => 30,
            'current_period_end' => '2027-03-01',
            'cancel_at_period_end' => false,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'ARS-1',
            'date' => '2026-11-20',
            'due_date' => '2026-11-20',
            'gross_amount' => 200000,
            'discount' => 0,
            'total_amount' => 200000,
            'balance' => 200000,
            'currency_id' => 32,
            'status' => 2,
        ]);
        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'name' => 'Cuota en pesos',
            'price' => 0,
            'responsible_id' => $user->id,
            'status_id' => ProjectStatus::STATUS_IN_PROGRESS,
            'data' => [
                'balance_invoices' => [
                    [
                        'due_date' => '2026-11-20',
                        'amount' => 200000,
                        'charged' => false,
                        'invoice_id' => $invoice->id,
                    ],
                ],
            ],
        ]);

        $trend = [];

        foreach (range(1, 12) as $month)
        {
            $trend[] = [
                'label' => 'M',
                'month' => $month,
                'income' => 0.0,
                'expense' => 0.0,
                'profit' => 0.0,
            ];
        }

        $analytics = $this->createMock(InvoiceAnalyticsService::class);
        $analytics->method('buildYearReport')->willReturn([
            'year' => 2026,
            'reporting_currency' => 'EUR',
            'monthly_trend' => $trend,
            'scenario' => [
                'avg_monthly_income' => 0,
                'avg_monthly_expense' => 0,
                'avg_monthly_profit' => 0,
            ],
        ]);

        $projection = (new FinanceCfoBriefService($analytics))->projection($team, 2026);

        $this->assertSame('EUR', $projection['currency']);
        $this->assertEqualsWithDelta(200.0, $projection['points'][1]['income'], 0.01);
        $this->assertEqualsWithDelta(150.0, $projection['points'][3]['income'], 0.01);
        $this->assertEqualsWithDelta(50.0, $projection['points'][4]['income'], 0.01);
        $this->assertEqualsWithDelta(30.0, $projection['points'][5]['income'], 0.01);
        $this->assertEqualsWithDelta(430.0, $projection['contracted_income'], 0.01);
    }

    public function test_cfo_counts_agenda_publications_and_ignores_google_without_credentials(): void
    {
        Carbon::setTestNow('2026-10-06 12:00:00');
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        CalendarEvent::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'title' => 'Post de Instagram',
            'start' => '2026-10-01 10:00:00',
            'end' => '2026-10-01 10:30:00',
            'label' => 'Publicación',
        ]);
        CalendarEvent::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'title' => 'Reunión con cliente',
            'start' => '2026-10-02 10:00:00',
            'end' => '2026-10-02 11:00:00',
            'label' => 'Business',
        ]);

        $service = app(FinanceCfoBriefService::class);
        $publications = $service->publications($team);

        $this->assertSame(1, $publications['posts_last_90_days']);
        $this->assertSame(0, $publications['posts_next_90_days']);
        $this->assertSame('Post de Instagram', $publications['events'][0]['title']);
        $this->assertNull($service->googleAnalytics($team));
    }

    public function test_cfo_keeps_only_unpaid_future_installments(): void
    {
        $totals = app(FinanceCfoBriefService::class)->contractedIncomeByMonth([
            ['due_date' => '2026-11-15', 'amount' => 800, 'charged' => false],
            ['due_date' => '2026-10-15', 'amount' => 800, 'charged' => false],
            ['due_date' => '2026-12-15', 'amount' => 400, 'charged' => true],
        ], Carbon::parse('2026-11-01'), 11);

        $this->assertEqualsWithDelta(800.0, $totals['2026-11'], 0.01);
        $this->assertArrayNotHasKey('2026-10', $totals);
        $this->assertArrayNotHasKey('2026-12', $totals);
    }

    public function test_weekly_analysis_stores_the_projection_for_the_page(): void
    {
        Carbon::setTestNow('2026-10-07 08:00:00');
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $plans = $this->createMock(WeeklyWorkPlanService::class);
        $plans->expects($this->atLeastOnce())->method('present');
        $this->app->instance(WeeklyWorkPlanService::class, $plans);

        $this->artisan('strategy:weekly-analysis', ['--team' => $team->id])
            ->assertSuccessful();

        $stored = $team->fresh()->getSetting('finance_cfo_projection_2026');
        $this->assertIsArray($stored);
        $this->assertCount(12, $stored['points']);
        $this->assertNotEmpty($stored['generated_at']);

        $team->setSetting('finance_cfo_projection_2026', [
            'currency' => 'EUR',
            'chart_income' => 12345.67,
            'chart_expense' => 890.12,
            'year_profit' => 11455.55,
            'months_with_data' => 3,
            'contracted_income' => 0,
            'contracted_expense' => 0,
            'points' => [
                ['label' => 'oct 2026', 'income' => 12345.67, 'expense' => 890.12, 'projected' => false],
            ],
            'generated_at' => '2026-10-07T06:30:00+00:00',
        ], [
            'type' => 'json',
            'group' => 'finance',
        ]);

        $this->actingAs($user)
            ->get(route('strategy.analysis'))
            ->assertOk()
            ->assertSee('12.345,67', false)
            ->assertSee('890,12', false)
            ->assertSee('11.455,55', false)
            ->assertSee(__('app.cfo_analysis_projection_updated', ['date' => '07/10/2026 06:30']), false)
            ->assertDontSee(__('app.cfo_analysis_projection_pending'), false);

        Carbon::setTestNow();
    }

    public function test_salary_load_uses_assigned_hours_and_refresh_does_not_ask_the_cfo(): void
    {
        $service = new FinanceCfoBriefService($this->createMock(InvoiceAnalyticsService::class));

        $this->assertEqualsWithDelta(1250.0, $service->salaryLoadCost(2000, 100, 160), 0.01);
        $this->assertSame(0.0, $service->salaryLoadCost(null, 154.7, 172));

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $adjusted = $service->withSalaryForecast([
            'currency' => 'EUR',
            'chart_income' => 1000,
            'chart_expense' => 400,
            'year_profit' => 600,
            'points' => [
                ['label' => 'oct 2026', 'income' => 1000, 'expense' => 400, 'projected' => false, 'expense_projected' => false],
            ],
        ], $team);

        $this->assertEqualsWithDelta(400.0, $adjusted['points'][0]['expense'], 0.01);
        $this->assertSame(0.0, $adjusted['salary_monthly']);

        $launcher = $this->createMock(WeeklyAnalysisLauncher::class);
        $launcher->expects($this->once())->method('start')->with($team->id);
        $this->app->instance(WeeklyAnalysisLauncher::class, $launcher);

        $this->actingAs($user)
            ->post(route('strategy.analysis.refresh'))
            ->assertRedirect(route('strategy.analysis'))
            ->assertSessionHas('success', __('app.cfo_analysis_refresh_started'));
    }
}
