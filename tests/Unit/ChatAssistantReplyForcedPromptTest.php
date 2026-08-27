<?php

namespace Tests\Unit;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use App\Services\AgentConversationContextService;
use App\Services\Assistant\AssistantActorContextService;
use App\Services\ChatAssistantReplyService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatAssistantReplyForcedPromptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CountrySeeder::class, LanguageSeeder::class, ModuleSeeder::class]);
    }

    public function test_forced_whatsapp_prompt_applies_without_context_user(): void
    {
        $team = Team::factory()->create();
        $module = Module::query()->where('key', 'chat')->first();
        $this->assertNotNull($module);
        Prompt::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'citas_y_ventas',
            'section_label' => 'Citas y ventas',
            'prompt_instruction' => 'Reservá citas con el prompt nuevo.',
            'is_active' => true,
            'order' => 0,
        ]);

        $service = $this->recordingReplyService();
        $reply = $service->getReply(
            'Hola',
            [],
            (int) $team->id,
            true,
            null,
            '34600000020',
            'chat:citas_y_ventas',
            null,
            false,
            AssistantActorContextService::CHANNEL_WHATSAPP,
        );

        $this->assertTrue($reply['success'] ?? false);
        $this->assertSame('Citas y ventas', $reply['routed_to'] ?? null);
        $this->assertStringContainsString('Reservá citas con el prompt nuevo.', $service->lastInstructions);
    }

    public function test_whatsapp_does_not_reuse_sticky_prompt_from_another_chat(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $module = Module::query()->where('key', 'chat')->first();
        $this->assertNotNull($module);
        Prompt::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'citas_y_ventas',
            'section_label' => 'Citas y ventas',
            'prompt_instruction' => 'Prompt de la primera charla.',
            'is_active' => true,
            'order' => 0,
        ]);
        Prompt::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'campanas',
            'section_label' => 'Campañas',
            'prompt_instruction' => 'Prompt de campañas actualizado.',
            'is_active' => true,
            'order' => 1,
        ]);

        app(AgentConversationContextService::class)->persistMessages(
            $user->id,
            'Hola',
            '¿Cómo te llamás?',
            'Citas y ventas',
            [],
            [],
            [],
            [],
            (int) $team->id,
            true,
            'chat:citas_y_ventas',
        );

        $service = $this->recordingReplyService();
        $reply = $service->getReply(
            'Hola',
            [],
            (int) $team->id,
            true,
            $user->id,
            '34600000020',
            'chat:campanas',
            null,
            false,
            AssistantActorContextService::CHANNEL_WHATSAPP,
        );

        $this->assertSame('Campañas', $reply['routed_to'] ?? null);
        $this->assertStringContainsString('Prompt de campañas actualizado.', $service->lastInstructions);
        $this->assertStringNotContainsString('Prompt de la primera charla.', $service->lastInstructions);
    }

    public function test_whatsapp_catalog_omits_staff_tools_and_crm_prompt(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $prompt = Prompt::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('section_key', 'assistant_catalogo')
            ->first();
        $this->assertNotNull($prompt);
        $prompt->update([
            'prompt_instruction' => 'Vendé el catálogo real.',
            'is_active' => true,
        ]);

        $service = $this->recordingReplyService();
        $reply = $service->getReply(
            'Ok',
            [
                ['direction' => 'inbound', 'body' => 'qué venden'],
                ['direction' => 'outbound', 'body' => 'Tenemos más de 10.000 productos.'],
            ],
            (int) $team->id,
            true,
            $user->id,
            '34600000020',
            'products:assistant_catalogo',
            null,
            false,
            AssistantActorContextService::CHANNEL_WHATSAPP,
        );

        $this->assertTrue($reply['success'] ?? false);
        $this->assertSame('Venta desde la tienda', $reply['routed_to'] ?? null);
        $this->assertContains('list_product_catalog', $service->lastToolNames);
        $this->assertContains('add_to_whatsapp_cart', $service->lastToolNames);
        $this->assertContains('send_product_image', $service->lastToolNames);
        $this->assertContains('assign_contact_to_category', $service->lastToolNames);
        $this->assertNotContains('get_account_report', $service->lastToolNames);
        $this->assertNotContains('create_message', $service->lastToolNames);
        $this->assertNotContains('send_whatsapp_message', $service->lastToolNames);
        $this->assertLessThan(16, count($service->lastToolNames));
        $this->assertStringContainsString('Vendé el catálogo real.', $service->lastInstructions);
        $this->assertStringContainsString('No vuelvas a listar el catálogo', $service->lastInstructions);
        $this->assertStringContainsString('assign_contact_to_category', $service->lastInstructions);
        $this->assertStringNotContainsString('Importar en lote', $service->lastInstructions);
        $this->assertStringNotContainsString('Conversation flow (discovery mode)', $service->lastInstructions);
        $this->assertCount(2, $service->lastHistory);
    }

    private function recordingReplyService(): ChatAssistantReplyService
    {
        return new class(app(\App\Services\AssistantToolsService::class), app(\App\Services\AssistantToolIntentPromptService::class), app(AgentConversationContextService::class), app(\App\Services\CollectionAssistantContextService::class), app(\App\Services\ContactAssistantContextService::class), app(\App\Services\AssistantToolAuthorizationService::class), app(AssistantActorContextService::class), app(\App\Services\BusinessAssistantContextService::class)) extends ChatAssistantReplyService
        {
            public string $lastInstructions = '';

            /** @var list<string> */
            public array $lastToolNames = [];

            /** @var array<int, array<string, mixed>> */
            public array $lastHistory = [];

            public function useStub(?int $teamId = null): bool
            {
                return false;
            }

            protected function getReplyWithLaravelAi(string $message, array $history, string $instructions, array $tools = [], ?string $routedTo = null): array
            {
                $this->lastInstructions = $instructions;
                $this->lastHistory = $history;
                $this->lastToolNames = array_map(
                    fn ($tool) => $tool->name(),
                    $tools,
                );

                return [
                    'success' => true,
                    'text' => 'ok',
                    'routed_to' => $routedTo,
                    'usage' => [],
                    'tool_calls' => [],
                    'tool_results' => [],
                    'meta' => [],
                ];
            }
        };
    }
}
