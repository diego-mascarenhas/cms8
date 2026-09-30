<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Services\AssistantChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;
use Tests\TestCase;

class AssistantChatHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_continuing_session_sends_only_the_latest_message(): void
    {
        $team = $this->teamWithPrompt('PRIMER_MENSAJE_MARKER offer a visit or the catalog.');

        Ai::fakeAgent(AnonymousAgent::class, ['Seguimos.']);

        $result = app(AssistantChatService::class)->run('Quiero una cita', $team->id, null, null, false, 'chat:landing', [
            ['direction' => 'outbound', 'body' => 'Hola. ¿En qué te puedo ayudar?'],
            ['direction' => 'inbound', 'body' => 'Hola'],
            ['direction' => 'outbound', 'body' => '¿Me decís tu nombre?'],
            ['direction' => 'inbound', 'body' => '   '],
        ]);

        $this->assertSame('Seguimos.', $result['response']);

        Ai::assertAgentWasPrompted(AnonymousAgent::class, function ($prompt): bool
        {
            $bodies = [];
            foreach ($prompt->agent->messages() as $message)
            {
                $bodies[] = $message->content;
            }

            return $prompt->prompt === 'Quiero una cita'
                && ! str_contains($prompt->prompt, 'PRIMER_MENSAJE_MARKER')
                && ! str_contains($prompt->prompt, 'Entrada del usuario')
                && str_contains($prompt->agent->instructions(), 'PRIMER_MENSAJE_MARKER')
                && $bodies === [
                    'Hola. ¿En qué te puedo ayudar?',
                    'Hola',
                    '¿Me decís tu nombre?',
                ];
        });
    }

    public function test_single_shot_still_embeds_the_instruction_in_the_user_turn(): void
    {
        $team = $this->teamWithPrompt('PRIMER_MENSAJE_MARKER offer a visit or the catalog.');

        Ai::fakeAgent(AnonymousAgent::class, ['Hola.']);

        app(AssistantChatService::class)->run('Hola', $team->id, null, null, false, 'chat:landing');

        Ai::assertAgentWasPrompted(AnonymousAgent::class, function ($prompt): bool
        {
            $count = 0;
            foreach ($prompt->agent->messages() as $message)
            {
                $count++;
            }

            return $count === 0
                && str_contains($prompt->prompt, 'PRIMER_MENSAJE_MARKER')
                && str_contains($prompt->prompt, 'Entrada del usuario')
                && str_contains($prompt->prompt, 'Hola');
        });
    }

    private function teamWithPrompt(string $instruction): Team
    {
        $team = Team::factory()->create();
        $module = Module::query()->create([
            'name' => 'Chat',
            'key' => 'chat',
            'icon' => null,
            'description' => null,
            'is_core' => false,
            'group' => null,
            'order' => 0,
            'status' => 1,
        ]);

        Prompt::query()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'landing',
            'section_label' => 'Landing',
            'prompt_instruction' => $instruction,
            'is_active' => true,
            'order' => 1,
        ]);

        return $team;
    }
}
