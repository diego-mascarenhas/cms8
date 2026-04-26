<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Local / QA: adds a dedicated keyword trigger prompt and enables chat assistant keyword routing.
 * Does not remove other prompts (uses updateOrCreate on team + chat + section_key).
 *
 * @example php artisan db:seed --class=ChatAssistantDebugFlowSeeder
 */
class ChatAssistantDebugFlowSeeder extends Seeder
{
    public const DEBUG_SECTION_KEY = 'humano-disparador-debug';

    public function run(): void
    {
        $chatModule = Module::query()->where('key', 'chat')->first();
        if ($chatModule === null)
        {
            $this->command?->warn('Module "chat" not found. Skipping ChatAssistantDebugFlowSeeder.');

            return;
        }

        $instruction = <<<'TXT'
Este flujo solo sirve para verificar enrutado por palabras clave en el chat.

Si el usuario escribe la frase de disparador (contiene las palabras del identificador de flujo),
responde exactamente una sola línea, sin markdown ni comillas:
[HUMANO_FLOW_OK]

No añadas saludo ni explicación.
TXT;

        $helper = <<<'MD'
**Disparador:** escribe en el chat del asistente una frase que incluya **humano disparador debug** (la clave `humano-disparador-debug` se normaliza así).

**Ruta:** `chat:humano-disparador-debug`

**Ajustes:** en el sidebar del chat, activar *Enrutado por palabras clave*; desactivar *Prueba con textos predefinidos*.

Este seeder también activa keyword routing en `team_settings` para cada equipo.
MD;

        /** @var Team $team */
        foreach (Team::query()->orderBy('id')->get() as $team)
        {
            $team->setSetting('assistant_keyword_intent_routing', true, [
                'group' => 'chat',
                'type' => 'boolean',
                'is_encrypted' => false,
            ]);
            $team->setSetting('assistant_chat_stub', false, [
                'group' => 'chat',
                'type' => 'boolean',
                'is_encrypted' => false,
            ]);
            $team->setSetting('chat_ai_assistance_blocked', false, [
                'group' => 'chat',
                'type' => 'boolean',
                'is_encrypted' => false,
            ]);

            Prompt::withoutGlobalScope('team')->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'module_id' => $chatModule->id,
                    'section_key' => self::DEBUG_SECTION_KEY,
                ],
                [
                    'section_label' => 'Debug flujo (Humano)',
                    'prompt_instruction' => $instruction,
                    'helper_text' => $helper,
                    'is_active' => true,
                    'order' => 999,
                ],
            );
        }

        $this->command?->info('ChatAssistantDebugFlowSeeder: prompt "'.self::DEBUG_SECTION_KEY.'" por equipo + keyword routing ON.');
    }
}
