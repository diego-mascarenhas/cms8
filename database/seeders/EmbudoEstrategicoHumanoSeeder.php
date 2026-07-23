<?php

namespace Database\Seeders;

use App\Models\Automation;
use App\Models\Module;
use App\Models\Team;
use App\Services\AssistantAutomationRunner;
use App\Services\AutomationFlowGraphSyncer;
use Illuminate\Database\Seeder;

/**
 * Seeds the "Embudo estratégico Humano" funnel + email action + flow graph.
 *
 * Usage:
 *   EMBUDO_ESTRATEGICO_TEAM_ID=2 php artisan db:seed --class=EmbudoEstrategicoHumanoSeeder
 *
 * Without the env var, seeds the first team by id.
 */
class EmbudoEstrategicoHumanoSeeder extends Seeder
{
    public const SLUG = 'embudo-estrategico-humano';

    public const EMAIL_ACTION_SLUG = 'enviar-resumen-por-email';

    public function run(): void
    {
        $teamId = $this->resolveTeamId();
        $team = Team::query()->find($teamId);

        if (! $team)
        {
            $this->command?->error("Team {$teamId} not found.");

            return;
        }

        Module::firstOrCreate(
            ['key' => 'automations'],
            [
                'name' => 'Automations',
                'icon' => 'robot',
                'description' => 'Omnichannel assistant flows',
                'is_core' => false,
                'group' => 'automation',
                'order' => 4,
                'status' => 1,
            ],
        );
        $team->enableModule('automations');

        $emailAction = Automation::query()->updateOrCreate(
            [
                'team_id' => $team->id,
                'slug' => self::EMAIL_ACTION_SLUG,
            ],
            [
                'name' => 'Enviar resumen por email',
                'kind' => 'action',
                'is_active' => true,
                'entry_prompt_key' => null,
                'channels' => Automation::normalizeChannels([
                    'humano' => true,
                    'whatsapp' => true,
                    'chat' => true,
                    'email' => true,
                    'api' => true,
                ]),
                'settings' => [
                    'action_type' => AssistantAutomationRunner::ACTION_TYPE_SEND_FUNNEL_SUMMARY_EMAIL,
                    'description' => 'Envía el resumen del embudo estratégico al usuario que lo completó.',
                ],
            ],
        );

        $funnel = Automation::query()->updateOrCreate(
            [
                'team_id' => $team->id,
                'slug' => self::SLUG,
            ],
            [
                'name' => 'Embudo estratégico Humano',
                'kind' => 'funnel',
                'is_active' => true,
                'entry_prompt_key' => null,
                'channels' => Automation::normalizeChannels([
                    'humano' => true,
                    'whatsapp' => true,
                    'chat' => true,
                    'email' => false,
                    'api' => true,
                ]),
                'settings' => [
                    'welcome_message' => 'Estrategia de Humano en 4 pasos: valor, mercado, posicionamiento y estructura. Luego el embudo comercial.',
                    'description' => 'Propuesta de valor → mercado objetivo → posicionamiento → roles → embudo comercial (atraer a retener).',
                    'entry_aliases' => ['embudo', 'estrategia', 'embudo de operaciones'],
                ],
            ],
        );

        app(AutomationFlowGraphSyncer::class)->sync($funnel, $this->graph($emailAction->id));

        $this->command?->info("✅ Embudo estratégico Humano seeded for team {$team->id} ({$team->name}) — funnel #{$funnel->id}, email action #{$emailAction->id}");
    }

    private function resolveTeamId(): int
    {
        if ($env = env('EMBUDO_ESTRATEGICO_TEAM_ID'))
        {
            return (int) $env;
        }

        $first = Team::query()->orderBy('id')->value('id');

        return (int) ($first ?: 1);
    }

    /**
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    private function graph(int $emailActionId): array
    {
        return [
            'nodes' => [
                [
                    'client_id' => '1',
                    'key' => 'inicio',
                    'label' => 'Inicio',
                    'prompt_key' => null,
                    'instruction' => 'Temas: propuesta de valor de Humano (qué problema resuelve), mercado objetivo, posicionamiento y estructura de roles. Empezar por el problema dominante.',
                    'is_entry' => true,
                    'position_x' => 40,
                    'position_y' => 80,
                    'outputs' => [
                        ['id' => 'output_1', 'reply_type' => 'choice', 'match_value' => 'empezar', 'label' => 'Empezar'],
                        ['id' => 'output_2', 'reply_type' => 'fallback', 'match_value' => null, 'label' => 'Otra respuesta'],
                    ],
                ],
                [
                    'client_id' => '2',
                    'key' => 'valor',
                    'label' => '1 · Propuesta de valor',
                    'prompt_key' => null,
                    'instruction' => 'Definir: (1) problema dominante que resuelve Humano, (2) resultado prometido (no lista de features), (3) por qué un asistente y no solo software, (4) qué NO es Humano. Dejar una frase clara del valor.',
                    'is_entry' => false,
                    'position_x' => 360,
                    'position_y' => 80,
                    'outputs' => [
                        ['id' => 'output_1', 'reply_type' => 'free_text', 'match_value' => null, 'label' => 'Definición de valor'],
                        ['id' => 'output_2', 'reply_type' => 'fallback', 'match_value' => null, 'label' => 'Otra'],
                    ],
                ],
                [
                    'client_id' => '3',
                    'key' => 'mercado',
                    'label' => '2 · Mercado objetivo',
                    'prompt_key' => null,
                    'instruction' => 'Definir: (1) segmento principal para 90 días, (2) persona compradora, (3) trigger de compra, (4) a quién NO se vende ahora, (5) geografía/idioma. Resumir el ICP en una frase.',
                    'is_entry' => false,
                    'position_x' => 680,
                    'position_y' => 80,
                    'outputs' => [
                        ['id' => 'output_1', 'reply_type' => 'free_text', 'match_value' => null, 'label' => 'ICP definido'],
                        ['id' => 'output_2', 'reply_type' => 'fallback', 'match_value' => null, 'label' => 'Otra'],
                    ],
                ],
                [
                    'client_id' => '4',
                    'key' => 'posicionamiento',
                    'label' => '3 · Posicionamiento',
                    'prompt_key' => null,
                    'instruction' => 'Frase: Para [quién] que sufren [problema], Humano es [categoría] que logra [resultado], a diferencia de [alternativa]. Incluir diferencia creíble y la demo/prueba que lo demuestra.',
                    'is_entry' => false,
                    'position_x' => 1000,
                    'position_y' => 80,
                    'outputs' => [
                        ['id' => 'output_1', 'reply_type' => 'free_text', 'match_value' => null, 'label' => 'Frase lista'],
                        ['id' => 'output_2', 'reply_type' => 'fallback', 'match_value' => null, 'label' => 'Otra'],
                    ],
                ],
                [
                    'client_id' => '5',
                    'key' => 'estructura',
                    'label' => '4 · Estructura y roles',
                    'prompt_key' => null,
                    'instruction' => 'Definir: qué hay hoy, qué falta, roles (producto/tech vs mercado/ventas) y huecos sin dueño (onboarding/CS, contenido/ads, soporte). Cada etapa del embudo comercial necesita un dueño.',
                    'is_entry' => false,
                    'position_x' => 1320,
                    'position_y' => 80,
                    'outputs' => [
                        ['id' => 'output_1', 'reply_type' => 'free_text', 'match_value' => null, 'label' => 'Roles definidos'],
                        ['id' => 'output_2', 'reply_type' => 'fallback', 'match_value' => null, 'label' => 'Otra'],
                    ],
                ],
                [
                    'client_id' => '6',
                    'key' => 'comercial',
                    'label' => 'Embudo comercial',
                    'prompt_key' => null,
                    'instruction' => 'Embudo operable: 1) Atraer 2) Cualificar 3) Convertir 4) Activar 5) Retener/expandir. Para cada etapa: dueño y una acción concreta a 90 días. Resumen: valor + ICP + posicionamiento + dueños.',
                    'is_entry' => false,
                    'position_x' => 1640,
                    'position_y' => 80,
                    'outputs' => [
                        ['id' => 'output_1', 'reply_type' => 'free_text', 'match_value' => null, 'label' => 'Plan 90 días'],
                        ['id' => 'output_2', 'reply_type' => 'fallback', 'match_value' => null, 'label' => 'Cierre'],
                    ],
                ],
                [
                    'client_id' => '7',
                    'key' => 'cierre',
                    'label' => 'Cierre',
                    'prompt_key' => null,
                    'instruction' => 'Entregar el resumen final en bullets: propuesta de valor, mercado, posicionamiento, roles y embudo comercial con dueños. Luego confirmar el envío del resumen por email.',
                    'is_entry' => false,
                    'position_x' => 1960,
                    'position_y' => 80,
                    'outputs' => [
                        [
                            'id' => 'output_1',
                            'reply_type' => 'choice',
                            'match_value' => 'email',
                            'label' => 'Enviar resumen por email',
                            'to_automation_id' => $emailActionId,
                        ],
                        [
                            'id' => 'output_2',
                            'reply_type' => 'fallback',
                            'match_value' => null,
                            'label' => 'Enviar y cerrar',
                            'to_automation_id' => $emailActionId,
                        ],
                    ],
                ],
            ],
            'edges' => [
                ['from_client_id' => '1', 'from_output' => 'output_1', 'to_client_id' => '2'],
                ['from_client_id' => '1', 'from_output' => 'output_2', 'to_client_id' => '2'],
                ['from_client_id' => '2', 'from_output' => 'output_1', 'to_client_id' => '3'],
                ['from_client_id' => '2', 'from_output' => 'output_2', 'to_client_id' => '3'],
                ['from_client_id' => '3', 'from_output' => 'output_1', 'to_client_id' => '4'],
                ['from_client_id' => '3', 'from_output' => 'output_2', 'to_client_id' => '4'],
                ['from_client_id' => '4', 'from_output' => 'output_1', 'to_client_id' => '5'],
                ['from_client_id' => '4', 'from_output' => 'output_2', 'to_client_id' => '5'],
                ['from_client_id' => '5', 'from_output' => 'output_1', 'to_client_id' => '6'],
                ['from_client_id' => '5', 'from_output' => 'output_2', 'to_client_id' => '6'],
                ['from_client_id' => '6', 'from_output' => 'output_1', 'to_client_id' => '7'],
                ['from_client_id' => '6', 'from_output' => 'output_2', 'to_client_id' => '7'],
                [
                    'from_client_id' => '7',
                    'from_output' => 'output_1',
                    'to_client_id' => null,
                    'to_automation_id' => $emailActionId,
                ],
                [
                    'from_client_id' => '7',
                    'from_output' => 'output_2',
                    'to_client_id' => null,
                    'to_automation_id' => $emailActionId,
                ],
            ],
        ];
    }
}
