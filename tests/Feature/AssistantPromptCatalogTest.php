<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\User;
use App\Services\AssistantPromptCatalog;
use App\Services\DefaultAssistantFlowPromptsService;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class AssistantPromptCatalogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: AssistantPromptCatalog, 1: \App\Models\Team}
     */
    private function catalogForPersonalTeam(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $this->seed(ModuleSeeder::class);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $this->assertNotNull($team);

        return [app(AssistantPromptCatalog::class), $team];
    }

    public function test_catalog_skips_list60_and_hides_own_brand_from_regular_teams(): void
    {
        config(['humano_pricing.plan_access_team_ids' => []]);
        [$catalog, $team] = $this->catalogForPersonalTeam();

        $keys = collect($catalog->items())->pluck('key');
        $this->assertTrue($keys->contains('calendar:assistant_citas'));
        $this->assertTrue($keys->contains('products:assistant_catalogo'));
        $this->assertTrue($keys->contains('products:assistant_embudo'));
        $this->assertTrue($keys->contains('invoices:collections'));
        $this->assertTrue($keys->contains('communications:assistant_campanas'));
        $this->assertTrue($keys->contains('products:humano_assistant'));
        $this->assertTrue($keys->contains('products:pumpstall'));
        $this->assertFalse($keys->contains('chat:mi-flujo-demo'));
        $this->assertFalse($keys->contains('financial:assistant_finanzas'));
        $this->assertFalse($keys->contains(fn (string $key) => str_starts_with($key, 'list60:')));

        $visible = collect($catalog->groupsFor($team))->flatMap(fn (array $group) => $group['items'])->pluck('key');
        $this->assertTrue($visible->contains('calendar:assistant_citas'));
        $this->assertTrue($visible->contains('products:assistant_embudo'));
        $this->assertTrue($visible->contains('invoices:collections'));
        $this->assertFalse($visible->contains('chat:mi-flujo-demo'));
        $this->assertFalse($visible->contains('products:humano_assistant'));
        $this->assertFalse($visible->contains('products:wapify_me'));
        $this->assertFalse($visible->contains('products:pumpstall'));
        $this->assertSame(
            ['agenda', 'ventas', 'cobranzas', 'equipo'],
            collect($catalog->groupsFor($team))->pluck('group')->all(),
        );
    }

    public function test_apply_overwrites_only_that_team_copy_with_the_php_default(): void
    {
        [$catalog, $team] = $this->catalogForPersonalTeam();

        $prompt = Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'assistant_citas')
            ->first();
        $this->assertNotNull($prompt);
        $prompt->prompt_instruction = 'TEXTO DEL EQUIPO';
        $prompt->save();

        $this->assertTrue(
            collect($catalog->groupsFor($team->fresh()))
                ->flatMap(fn (array $group) => $group['items'])
                ->firstWhere('key', 'calendar:assistant_citas')['drifted'] ?? false,
        );

        $this->assertSame('calendar:assistant_citas', $catalog->apply($team, 'calendar:assistant_citas'));

        $expected = collect(DefaultAssistantFlowPromptsService::definitions())
            ->firstWhere('section_key', 'assistant_citas')['prompt_instruction'] ?? null;
        $this->assertSame($expected, $prompt->fresh()->prompt_instruction);
    }

    public function test_apply_restores_a_team_copy_even_if_the_module_key_differs(): void
    {
        [$catalog, $team] = $this->catalogForPersonalTeam();
        $module = Module::query()->where('key', 'documentation')->first();
        $this->assertNotNull($module);

        $prompt = Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'assistant_citas')
            ->first();
        $this->assertNotNull($prompt);
        $prompt->module_id = $module->id;
        $prompt->section_label = 'Citas (Modificado)';
        $prompt->prompt_instruction = 'TEXTO DEL EQUIPO';
        $prompt->save();

        $this->assertSame('documentation:assistant_citas', $catalog->apply($team, 'documentation:assistant_citas'));
        $this->assertStringContainsString('create_calendar_event', (string) $prompt->fresh()->prompt_instruction);
        $this->assertSame('Asistente: citas y calendario', $prompt->fresh()->section_label);
    }

    public function test_apply_rejects_own_brand_for_a_regular_team(): void
    {
        config(['humano_pricing.plan_access_team_ids' => []]);
        [$catalog, $team] = $this->catalogForPersonalTeam();

        $this->expectException(InvalidArgumentException::class);
        $catalog->apply($team, 'products:humano_assistant');
    }

    public function test_ensure_on_team_copies_a_catalog_default_without_overwriting_a_custom_copy(): void
    {
        [$catalog, $team] = $this->catalogForPersonalTeam();

        $this->assertSame('invoices:collections', $catalog->ensureOnTeam($team, 'invoices:collections'));
        $prompt = Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'collections')
            ->first();
        $this->assertNotNull($prompt);
        $this->assertStringContainsString('factura', mb_strtolower((string) $prompt->prompt_instruction));

        $prompt->prompt_instruction = 'TEXTO DEL EQUIPO';
        $prompt->save();

        $this->assertSame('invoices:collections', $catalog->ensureOnTeam($team, 'invoices:collections'));
        $this->assertSame('TEXTO DEL EQUIPO', $prompt->fresh()->prompt_instruction);
    }

    public function test_ensure_on_team_copies_own_brand_for_an_allowed_team(): void
    {
        [$catalog, $team] = $this->catalogForPersonalTeam();
        config(['humano_pricing.plan_access_team_ids' => [(int) $team->id]]);

        $this->assertSame('products:humano_assistant', $catalog->ensureOnTeam($team, 'products:humano_assistant'));
        $copied = Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'humano_assistant')
            ->first();
        $this->assertNotNull($copied);
        $this->assertStringContainsString('https://assistant.idoneo.dev/register', (string) $copied->prompt_instruction);
    }
}
