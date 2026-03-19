<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use App\Services\AssistantCapabilitiesOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantCapabilitiesOverviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_matches_exploration_intent_in_spanish(): void
    {
        $s = app(AssistantCapabilitiesOverviewService::class);

        $this->assertTrue($s->matchesExplorationIntent('Quiero probar el asistente de compra'));
        $this->assertTrue($s->matchesExplorationIntent('demo del chat'));
        $this->assertTrue($s->matchesExplorationIntent('¿Qué podés hacer?'));
        $this->assertTrue($s->matchesExplorationIntent('FUNCIONES del asistente'));
        $this->assertFalse($s->matchesExplorationIntent('Hola, necesito el estado de mi pedido'));
    }

    public function test_should_offer_overview_only_early_in_thread(): void
    {
        $s = app(AssistantCapabilitiesOverviewService::class);

        $this->assertTrue($s->shouldOfferOverview([], 'quiero probar', true));
        $this->assertTrue($s->shouldOfferOverview([
            ['direction' => 'inbound', 'body' => 'Hola'],
            ['direction' => 'outbound', 'body' => 'Hi'],
        ], 'demo', true));

        $this->assertFalse($s->shouldOfferOverview([], 'factura', true));
        $this->assertFalse($s->shouldOfferOverview([
            ['direction' => 'inbound', 'body' => 'a'],
            ['direction' => 'outbound', 'body' => 'b'],
            ['direction' => 'inbound', 'body' => 'c'],
            ['direction' => 'outbound', 'body' => 'd'],
            ['direction' => 'inbound', 'body' => 'e'],
        ], 'demo', true));
    }

    public function test_build_overview_mentions_products_when_module_enabled(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $module = Module::query()->firstOrCreate(
            ['key' => 'products'],
            ['name' => 'Products', 'is_core' => false, 'status' => 1],
        );
        $team->modules()->attach($module->id, ['status' => 1, 'settings' => null]);

        $team->refresh()->load('modules');
        $text = app(AssistantCapabilitiesOverviewService::class)->buildOverviewMessage($team);

        $this->assertStringContainsString('Compras / catálogo', $text);
        $this->assertStringContainsString('carrito', $text);
        $this->assertStringContainsString('checkout', $text);
    }

    public function test_build_overview_notes_when_products_module_disabled(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $text = app(AssistantCapabilitiesOverviewService::class)->buildOverviewMessage($team->fresh());

        $this->assertStringContainsString('no está activo', $text);
    }
}
