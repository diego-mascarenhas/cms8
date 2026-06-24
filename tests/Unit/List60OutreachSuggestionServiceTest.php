<?php

namespace Tests\Unit;

use App\Models\Contact;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use App\Services\AgentConversationContextService;
use App\Services\ChatAssistantReplyService;
use App\Services\DefaultAssistantFlowPromptsService;
use App\Services\List60OutreachSuggestionService;
use App\Services\UserResolverService;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class List60OutreachSuggestionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_routing_key_is_first_contact_when_sin_contactar(): void
    {
        $service = $this->makeService();

        $this->assertSame(
            List60OutreachSuggestionService::ROUTING_KEY_FIRST_CONTACT,
            $service->routingKeyForStatus('Sin contactar'),
        );
        $this->assertSame(
            List60OutreachSuggestionService::ROUTING_KEY_FIRST_CONTACT,
            $service->routingKeyForStatus(null),
        );
    }

    public function test_routing_key_is_follow_up_when_already_contacted(): void
    {
        $service = $this->makeService();

        $this->assertSame(
            List60OutreachSuggestionService::ROUTING_KEY_FOLLOW_UP,
            $service->routingKeyForStatus('2 Contactos'),
        );
    }

    public function test_build_instruction_uses_module_prompt_for_first_contact(): void
    {
        $teamId = $this->seedTeamWithList60Prompts();

        Prompt::withoutGlobalScope('team')
            ->forTeam($teamId)
            ->where('section_key', 'primer_contacto')
            ->first()
            ?->update(['prompt_instruction' => 'PROMPT PERSONALIZADO PRIMER CONTACTO']);

        $instruction = $this->buildInstructionForContact(
            teamId: $teamId,
            channel: 'whatsapp',
            statusName: 'Sin contactar',
        );

        $this->assertStringContainsString('PROMPT PERSONALIZADO PRIMER CONTACTO', $instruction);
        $this->assertStringContainsString('- Estado en Lista de 60: Sin contactar', $instruction);
        $this->assertStringContainsString('Clave: "message"', $instruction);
    }

    public function test_build_instruction_uses_module_prompt_for_follow_up(): void
    {
        $teamId = $this->seedTeamWithList60Prompts();

        Prompt::withoutGlobalScope('team')
            ->forTeam($teamId)
            ->where('section_key', 'seguimiento')
            ->first()
            ?->update(['prompt_instruction' => 'PROMPT PERSONALIZADO SEGUIMIENTO']);

        $instruction = $this->buildInstructionForContact(
            teamId: $teamId,
            channel: 'email',
            statusName: '2 Contactos',
        );

        $this->assertStringContainsString('PROMPT PERSONALIZADO SEGUIMIENTO', $instruction);
        $this->assertStringContainsString('Claves: "subject"', $instruction);
        $this->assertStringContainsString('y "body"', $instruction);
    }

    public function test_build_instruction_falls_back_when_prompt_is_inactive(): void
    {
        $teamId = $this->seedTeamWithList60Prompts();

        Prompt::withoutGlobalScope('team')
            ->forTeam($teamId)
            ->where('section_key', 'primer_contacto')
            ->first()
            ?->update(['is_active' => false]);

        $instruction = $this->buildInstructionForContact(
            teamId: $teamId,
            channel: 'whatsapp',
            statusName: 'Sin contactar',
        );

        $this->assertStringContainsString('Isra Bravo', $instruction);
    }

    private function seedTeamWithList60Prompts(): int
    {
        $this->seed(ModuleSeeder::class);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        DefaultAssistantFlowPromptsService::syncForTeam((int) $team->id);

        return (int) $team->id;
    }

    private function buildInstructionForContact(int $teamId, string $channel, ?string $statusName): string
    {
        $contact = new Contact(['name' => 'Miguel Ruiz']);

        $service = $this->makeService();
        $method = new ReflectionMethod(List60OutreachSuggestionService::class, 'buildInstruction');

        return $method->invoke(
            $service,
            $teamId,
            $channel,
            $contact,
            'Interesado en plan Business.',
            ['name' => 'Positivo', 'emoji' => '🙂', 'notes' => 'Muy receptivo'],
            ['Cliente potencial'],
            $statusName,
        );
    }

    private function makeService(): List60OutreachSuggestionService
    {
        return new List60OutreachSuggestionService(
            $this->createMock(UserResolverService::class),
            $this->createMock(AgentConversationContextService::class),
            $this->createMock(ChatAssistantReplyService::class),
        );
    }
}
