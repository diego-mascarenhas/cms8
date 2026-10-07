<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\Finance\FinanceCfoBriefService;
use App\Services\Finance\InvoiceAnalyticsService;
use Carbon\Carbon;
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
            ->assertSee(__('Ask the CFO'), false)
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
}
