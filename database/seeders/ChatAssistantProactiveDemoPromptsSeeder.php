<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Demo / QA: active Chat-module prompts whose keys match the admin proactive WhatsApp syntax
 * (keyword + phone in the assistant chat). Run after ModuleSeeder.
 *
 * @example php artisan db:seed --class=ChatAssistantProactiveDemoPromptsSeeder
 */
class ChatAssistantProactiveDemoPromptsSeeder extends Seeder
{
    /**
     * @return list<array{section_key: string, section_label: string, order: int, instruction: string}>
     */
    private function promptDefinitions(): array
    {
        return [
            [
                'section_key' => 'demo',
                'section_label' => 'Demo outreach WhatsApp',
                'order' => 100,
                'instruction' => <<<'TXT'
Flujo demo: saluda brevemente por WhatsApp y explica que es un mensaje de prueba del equipo (Humano).
No agregues marcas internas ni códigos de seguimiento al texto que lee el cliente.
TXT,
            ],
            [
                'section_key' => 'cobrar',
                'section_label' => 'Cobranza amable demo',
                'order' => 101,
                'instruction' => <<<'TXT'
Flujo cobranza demo: tono cordial, recuerda un pago pendiente genérico sin inventar importes ni enlaces.
Cierra con [DEMO_FLOW:cobrar].
TXT,
            ],
            [
                'section_key' => 'reunion',
                'section_label' => 'Reunión o cita demo',
                'order' => 102,
                'instruction' => <<<'TXT'
Flujo reunión demo: ofrece coordinar una breve llamada o reunión según disponibilidad del equipo.
Cierra con [DEMO_FLOW:reunion].
TXT,
            ],
            [
                'section_key' => 'registar',
                'section_label' => 'Registro lead demo',
                'order' => 103,
                'instruction' => <<<'TXT'
Flujo registro demo: invita a dejar datos de contacto o interés sin formularios externos inventados.
Cierra con [DEMO_FLOW:registar].
TXT,
            ],
            [
                'section_key' => 'mi-flujo-demo',
                'section_label' => 'Mi flujo demo palabras compuestas',
                'order' => 104,
                'instruction' => <<<'TXT'
Flujo con clave compuesta: responde en una sola frase que reconoces el disparador «mi flujo demo».
Cierra con [DEMO_FLOW:mi-flujo-demo].
TXT,
            ],
            [
                'section_key' => 'solo-etiqueta-match',
                'section_label' => 'Outreach solo por etiqueta larga demo',
                'order' => 105,
                'instruction' => <<<'TXT'
Si el operador eligió este flujo por coincidencia de etiqueta, confirma que es el flujo «solo etiqueta».
Cierra con [DEMO_FLOW:solo-etiqueta].
TXT,
            ],
        ];
    }

    public function run(): void
    {
        $chatModule = Module::query()->where('key', 'chat')->first();
        if ($chatModule === null)
        {
            $this->command?->warn('Module "chat" not found. Run ModuleSeeder first.');

            return;
        }

        $helper = <<<'MD'
**Proactive admin (chat asistente, admin/root):** escribí `clave +teléfono` o `clave: +teléfono` (el teléfono puede llevar espacios, guiones y paréntesis).

**Claves de este seeder (módulo Chat):**
- `demo` → `chat:demo`
- `cobrar` → `chat:cobrar`
- `reunion` → `chat:reunion`
- `registar` → `chat:registar` (clave a propósito con la forma habitual del disparador)
- `mi flujo demo` o `mi-flujo-demo` → `chat:mi-flujo-demo`
- Palabra clave por etiqueta: usá literalmente **Outreach solo por etiqueta larga demo** en el texto si querés forzar por etiqueta (enrutado por keywords + etiqueta ≥12 caracteres).

**Ejemplos:** `demo +34 (722) 372-858`, `cobrar +34 722 372 111`, `reunion: +34722372858`, `registar: +34 722 372 858`, `mi flujo demo +34600111222`.
MD;

        foreach (Team::query()->orderBy('id')->get() as $team)
        {
            /** @var Team $team */
            foreach ($this->promptDefinitions() as $def)
            {
                Prompt::withoutGlobalScope('team')->updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'module_id' => $chatModule->id,
                        'section_key' => $def['section_key'],
                    ],
                    [
                        'section_label' => $def['section_label'],
                        'prompt_instruction' => $def['instruction'],
                        'helper_text' => $helper,
                        'is_active' => true,
                        'order' => $def['order'],
                    ],
                );
            }

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
        }

        $this->command?->info('ChatAssistantProactiveDemoPromptsSeeder: prompts demo chat + keyword routing ON por equipo.');
    }
}
