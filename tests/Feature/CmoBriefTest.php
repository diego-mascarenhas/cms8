<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\User;
use App\Services\CmoBriefLauncher;
use App\Services\Marketing\CmoBriefService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CmoBriefTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_commercial_context_uses_invoices_and_skips_a_currency_that_is_missing(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->actingAs($user);
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Acme B2B',
            'country' => 'España',
            'locality' => 'Madrid',
        ]);

        foreach ([100, 150] as $index => $amount)
        {
            Invoice::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'enterprise_id' => $enterprise->id,
                'type_id' => 1,
                'operation' => 'sell',
                'status' => 1,
                'date' => '2026-03-0'.($index + 1),
                'due_date' => '2026-04-01',
                'gross_amount' => $amount,
                'discount' => 0,
                'total_amount' => $amount,
                'balance' => $amount,
                'currency_id' => 978,
                'number' => 'CMO-'.$index,
            ]);
        }

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'status' => 1,
            'date' => '2026-05-01',
            'due_date' => '2026-06-01',
            'gross_amount' => 9999,
            'discount' => 0,
            'total_amount' => 9999,
            'balance' => 9999,
            'currency_id' => null,
            'number' => 'CMO-RAW',
        ]);

        $context = app(CmoBriefService::class)->context($team, 2026);

        $this->assertSame(3, $context['purchase']['invoices']);
        $this->assertSame(1, $context['purchase']['repeat_clients']);
        $this->assertSame(0, $context['purchase']['single_clients']);
        $this->assertEqualsWithDelta(250.0, $context['purchase']['amount'], 0.01);
        $this->assertSame(1, $context['purchase']['unconverted_invoices']);
        $this->assertSame('Acme B2B', $context['top_clients'][0]['name']);
        $this->assertSame('España', $context['top_clients'][0]['country']);
        $this->assertEqualsWithDelta(250.0, $context['top_clients'][0]['amount'], 0.01);
        $this->assertSame(
            ['name', 'country', 'locality', 'invoices', 'amount'],
            array_keys($context['top_clients'][0]),
        );
        $this->assertSame('España', $context['clients_by_country'][0]['country']);
        $this->assertSame(0, $context['customer_interviews']);
        $this->assertFalse($context['not_loaded']['macro_environment']);
        $this->assertFalse($context['not_loaded']['marketing_budget']);
        $this->assertSame(6, $context['constraints']['cushion_months']);
        $this->assertEqualsWithDelta(3000.0, $context['constraints']['share_capital'], 0.01);
        $this->assertArrayNotHasKey('email', $context);
        $this->assertArrayNotHasKey('whatsapp', $context);
    }

    public function test_a_model_reply_becomes_the_fifteen_blocks(): void
    {
        $analysis = app(CmoBriefService::class)->present(json_encode([
            'situation' => 'Dos facturas repetidas.',
            'dafo' => ['fortalezas' => 'Margen de empresa.'],
            'came' => ['corregir' => 'Corregir la agenda vacía.'],
            'pest' => 'El entorno macro no está cargado.',
        ], JSON_UNESCAPED_UNICODE));

        $this->assertSame('Dos facturas repetidas.', $analysis['situation']);
        $this->assertSame('Margen de empresa.', $analysis['dafo']['fortalezas']);
        $this->assertSame('', $analysis['dafo']['debilidades']);
        $this->assertSame('Corregir la agenda vacía.', $analysis['came']['corregir']);
        $this->assertSame('', $analysis['empathy']['thinks']);
        $this->assertSame('El entorno macro no está cargado.', $analysis['pest']);
        $this->assertTrue(CmoBriefService::hasStoredSections($analysis));
    }

    public function test_the_analysis_page_shows_the_cmo_reading_and_the_ask_button(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $team->setSetting('marketing_cmo_brief_'.now()->year, [
            'situation' => 'Dos facturas repetidas.',
            'came' => ['corregir' => 'Corregir la agenda vacía.'],
            'icp' => 'Empresas en España, sin empleados cargados.',
            'pest' => 'El entorno macro no está cargado.',
            'generated_at' => '2026-10-07T12:00:00+00:00',
        ], [
            'type' => 'json',
            'group' => 'marketing',
        ]);

        $this->actingAs($user)
            ->get(route('strategy.analysis'))
            ->assertOk()
            ->assertSee('id="cmo-analysis"', false)
            ->assertSee('id="cmo-brief-button"', false)
            ->assertSee(__('Ask the CMO'), false)
            ->assertSee(__('Asking the CMO...'), false)
            ->assertSee('https://adquiria.net/', false)
            ->assertSee('Dos facturas repetidas.', false)
            ->assertSee('Corregir la agenda vacía.', false)
            ->assertSee('Empresas en España, sin empleados cargados.', false)
            ->assertSee('El entorno macro no está cargado.', false)
            ->assertSee(__('app.cmo_situation'), false)
            ->assertSee(__('app.cmo_pest'), false);

        $launcher = $this->createMock(CmoBriefLauncher::class);
        $launcher->expects($this->once())->method('start')->with($team->id, (int) now()->year);
        $this->app->instance(CmoBriefLauncher::class, $launcher);

        $this->post(route('strategy.analysis.cmo-brief'), [
            'year' => now()->year,
            'refresh' => 1,
        ])->assertRedirect(route('strategy.analysis'));

        $this->get(route('strategy.analysis'))
            ->assertOk()
            ->assertSee('id="cmo-run"', false)
            ->assertSee(__('app.cmo_phase_queued'), false)
            ->assertSee(__('app.cmo_running_title'), false)
            ->assertSee(route('strategy.analysis.cmo-status', ['year' => now()->year]), false);

        $this->getJson(route('strategy.analysis.cmo-status', ['year' => now()->year]))
            ->assertOk()
            ->assertJsonPath('state', 'running')
            ->assertJsonPath('phase', 'queued');

        $team->setSetting('marketing_cmo_run_'.now()->year, [
            'state' => 'running',
            'phase' => 'context',
            'started_at' => now()->subMinutes(20)->toIso8601String(),
        ], [
            'type' => 'json',
            'group' => 'marketing',
        ]);

        $this->assertSame('idle', app(CmoBriefService::class)->runStatus($team->fresh(), (int) now()->year)['state']);
    }
}
