<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\TokenUsageLog;
use App\Models\User;
use App\Support\AiTasks;
use Illuminate\Support\Facades\Log;
use RuntimeException;

use function Laravel\Ai\agent;

class ProjectBudgetSpecService
{
    /**
     * Generate a full budget specification (includes prices for internal use).
     *
     * @return array{
     *   ai_interpretation: string,
     *   dimension: string,
     *   estimated_times: string,
     *   resources: string,
     *   client_items: array<int, mixed>,
     *   resource_breakdown: array<int, mixed>,
     *   suggested_tasks: array<int, array<string, mixed>>
     * }
     */
    public function generate(string $budgetGiven, ?Team $team = null, ?User $user = null): array
    {
        $budgetGiven = trim($budgetGiven);
        if ($budgetGiven === '')
        {
            throw new RuntimeException(__('The budget text is required.'));
        }

        $instructions = Prompt::forModule('projects')
            ->where('section_key', 'budget_spec')
            ->active()
            ->first()?->prompt_instruction ?? $this->getDefaultBudgetSpecPrompt();

        $taskCategoriesContext = $this->getTaskCategoriesContextForAi($team);
        if ($taskCategoriesContext !== '')
        {
            $instructions .= "\n\n".$taskCategoriesContext;
        }

        $userMessage = $instructions."\n\n---\n\nEntrada del usuario:\n\n".$budgetGiven;

        try
        {
            $agent = agent(
                instructions: $instructions,
                messages: [],
                tools: [],
            );
            $response = $agent->prompt($userMessage, [], AiTasks::provider('assistant'));
            $text = $response->text ?: '';
        } catch (\Throwable $e)
        {
            Log::error('ProjectBudgetSpecService failed', ['error' => $e->getMessage()]);

            throw new RuntimeException(__('Error al comunicar con la IA: ').$e->getMessage(), 0, $e);
        }

        if (isset($response->usage) && $user?->currentTeam)
        {
            $usage = $response->usage;
            $totalTokens = $usage->promptTokens + $usage->completionTokens;
            try
            {
                TokenUsageLog::create([
                    'team_id' => $user->currentTeam->id,
                    'module_id' => TokenUsageLog::inferModuleId(),
                    'service' => 'ProjectBudgetSpecService::generate',
                    'json_size' => strlen($userMessage),
                    'toon_size' => 0,
                    'json_tokens' => $totalTokens,
                    'toon_tokens' => 0,
                    'savings_percentage' => 0,
                    'used_toon' => false,
                ]);
            } catch (\Exception $logEx)
            {
                Log::warning('TokenUsageLog failed', ['error' => $logEx->getMessage()]);
            }
        }

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $m))
        {
            $text = trim($m[1]);
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded))
        {
            Log::warning('ProjectBudgetSpecService invalid JSON', ['text' => substr($text, 0, 500)]);

            throw new RuntimeException(__('La respuesta no es un JSON válido.'));
        }

        $suggestedTasks = is_array($decoded['suggested_tasks'] ?? null) ? $decoded['suggested_tasks'] : [];

        return [
            'ai_interpretation' => (string) ($decoded['ai_interpretation'] ?? ''),
            'dimension' => (string) ($decoded['dimension'] ?? ''),
            'estimated_times' => (string) ($decoded['estimated_times'] ?? ''),
            'resources' => (string) ($decoded['resources'] ?? ''),
            'client_items' => is_array($decoded['client_items'] ?? null) ? $decoded['client_items'] : [],
            'resource_breakdown' => is_array($decoded['resource_breakdown'] ?? null) ? $decoded['resource_breakdown'] : [],
            'suggested_tasks' => $this->normalizeSuggestedTasks($suggestedTasks),
        ];
    }

    /**
     * Conversational guide: ask the next question and tick fundamentación requirements.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{
     *   assistant_message: string,
     *   requirements: array<int, array{key: string, name: string, hint: string, met: bool, feedback: string}>,
     *   brief: string,
     *   project_name: string|null,
     *   all_met: bool
     * }
     */
    public function chatTurn(array $messages, ?string $projectName = null, ?string $leadName = null): array
    {
        $normalized = [];
        foreach ($messages as $message)
        {
            if (! is_array($message))
            {
                continue;
            }
            $role = (string) ($message['role'] ?? '');
            $content = trim((string) ($message['content'] ?? ''));
            if (! in_array($role, ['user', 'assistant'], true) || $content === '')
            {
                continue;
            }
            $normalized[] = ['role' => $role, 'content' => $content];
        }

        $requirements = $this->techGuideRequirements();

        // Instant welcome without calling the AI — avoids hanging the UI on empty chat boot.
        if ($normalized === [])
        {
            $greetingName = trim((string) $leadName);
            $hello = $greetingName !== ''
                ? "Hola {$greetingName}, soy tu asistente de alcance."
                : 'Hola, soy tu asistente de alcance.';

            return [
                'assistant_message' => $hello.' ¿Qué necesitas construir y qué problema quieres resolver?',
                'requirements' => array_map(
                    fn (array $r): array => [
                        'key' => $r['key'],
                        'name' => $r['name'],
                        'hint' => $r['hint'],
                        'met' => false,
                        'feedback' => '',
                    ],
                    $requirements,
                ),
                'brief' => '',
                'project_name' => $projectName,
                'all_met' => false,
            ];
        }

        $requirementsJson = json_encode(
            array_map(fn (array $r): array => [
                'key' => $r['key'],
                'name' => $r['name'],
                'hint' => $r['hint'],
            ], $requirements),
            JSON_UNESCAPED_UNICODE,
        );

        $transcript = collect($normalized)
            ->map(fn (array $m): string => strtoupper($m['role']).': '.$m['content'])
            ->implode("\n\n");

        $leadLabel = trim((string) $leadName) !== '' ? trim((string) $leadName) : '(sin nombre)';

        $instructions = "Eres un asistente comercial-técnico que ayuda a fundamentar un presupuesto de producto digital (web, app, etc.).\n"
            ."El cliente se llama {$leadLabel}. Puedes usar su nombre de forma natural, sin forzar.\n"
            ."Mantén un tono cercano y profesional en español. Haz UNA sola pregunta clara por turno.\n"
            ."Evalúa los requisitos con TODA la conversación acumulada (no solo el último mensaje).\n"
            ."Si un requisito ya se cubrió en un turno anterior, déjalo met=true. Nunca lo bajes a false.\n"
            ."Orden sugerido de preguntas (salta lo ya cubierto): objetivo → negocio → usuarios → funcionalidades → plataforma → urls → diseno → alcance.\n"
            ."Para \"urls\": pregunta si tiene web/app actual (pide la URL) y 1-3 URLs de referencia o inspiración. Si dice que no tiene URL actual, marca met=true igual y anótalo.\n"
            ."Para \"diseno\": en uno o dos turnos cubre (a) si ya tienen identidad/gráfica hecha, (b) si quieren gráfica a medida o solo un template, (c) nivel de diseño (básico, medio, premium).\n"
            ."Tilda met=true solo cuando haya información suficiente y concreta para ese requisito:\n"
            .$requirementsJson."\n\n"
            ."Responde SOLO con un JSON válido (sin markdown) con estas claves:\n"
            ."- \"assistant_message\": tu siguiente mensaje al cliente (pregunta o confirmación breve).\n"
            ."- \"requirements\": array con un objeto por cada requisito; cada uno con \"key\", \"met\" (boolean), \"feedback\" (frase corta).\n"
            ."- \"brief\": síntesis acumulada del proyecto con TODO lo que el cliente ya dijo (negocio, URLs, diseño, alcance…), sin inventar. Debe tener al menos 2-3 frases útiles para presupuestar.\n"
            ."- \"project_name\": nombre corto sugerido (mejor el del negocio si lo hay) o null.\n"
            .'Si aún faltan requisitos, pregunta por el primero que falte. Si ya están todos, confirma que puedes estimar el alcance y no hagas más preguntas.';

        $userMessage = $instructions
            ."\n\nNOMBRE DE PROYECTO ACTUAL: ".($projectName ?: '(ninguno)')
            ."\n\nCONVERSACIÓN:\n\n".$transcript;

        try
        {
            $agent = agent(
                instructions: $instructions,
                messages: [],
                tools: [],
            );
            $response = $agent->prompt($userMessage, [], AiTasks::provider('assistant'));
            $text = $response->text ?: '';
        } catch (\Throwable $e)
        {
            Log::error('ProjectBudgetSpecService::chatTurn failed', ['error' => $e->getMessage()]);

            throw new RuntimeException(__('Error al comunicar con la IA: ').$e->getMessage(), 0, $e);
        }

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $m))
        {
            $text = trim($m[1]);
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded))
        {
            throw new RuntimeException(__('La respuesta no es un JSON válido.'));
        }

        $evaluated = $this->mapRequirementsFromAi($requirements, is_array($decoded['requirements'] ?? null) ? $decoded['requirements'] : []);
        $allMet = collect($evaluated)->every(fn (array $r): bool => $r['met'] === true);

        $assistantMessage = trim((string) ($decoded['assistant_message'] ?? ''));
        if ($assistantMessage === '')
        {
            $assistantMessage = $allMet
                ? 'Perfecto, ya tengo lo necesario para estimar el alcance. Cuando quieras, pulsa “Estimar alcance”.'
                : 'Cuéntame un poco más sobre lo que necesitas.';
        }

        $brief = trim((string) ($decoded['brief'] ?? ''));
        if ($brief === '')
        {
            $brief = collect($normalized)
                ->where('role', 'user')
                ->pluck('content')
                ->implode("\n\n");
        }

        $suggestedName = $decoded['project_name'] ?? null;
        $suggestedName = is_string($suggestedName) && trim($suggestedName) !== '' ? trim($suggestedName) : null;

        return [
            'assistant_message' => $assistantMessage,
            'requirements' => $evaluated,
            'brief' => $brief,
            'project_name' => $suggestedName,
            'all_met' => $allMet,
        ];
    }

    /**
     * Guided tech-brief evaluation (Fanyion-style fundamentación checklist).
     *
     * @return array{
     *   requirements: array<int, array{key: string, name: string, hint: string, met: bool, feedback: string}>,
     *   summary: string,
     *   suggested_additions: string,
     *   improved_brief: string,
     *   all_met: bool
     * }
     */
    public function guideBrief(string $brief): array
    {
        $brief = trim($brief);
        if (mb_strlen($brief) < 10)
        {
            throw new RuntimeException(__('Write at least 10 characters to evaluate.'));
        }

        $requirements = $this->techGuideRequirements();
        $requirementsJson = json_encode(
            array_map(fn (array $r): array => [
                'key' => $r['key'],
                'name' => $r['name'],
                'hint' => $r['hint'],
            ], $requirements),
            JSON_UNESCAPED_UNICODE,
        );

        $instructions = "Eres un asistente experto en presupuestos de software y productos digitales.\n"
            ."Evalúa si el brief del cliente cubre los requisitos de fundamentación (negocio, URLs, diseño, alcance técnico).\n"
            ."Para urls: met=true si indica URL actual o explícitamente que no tiene, y/o da referencias.\n"
            ."Para diseno: met=true si aclara gráfica previa, template vs a medida, y nivel de diseño.\n"
            ."Responde SOLO con un JSON válido (sin markdown) con estas claves:\n"
            ."- \"requirements\": array con un objeto por cada requisito recibido; cada uno con \"key\", \"met\" (boolean), \"feedback\" (1 frase concreta: qué falta o qué está bien).\n"
            ."- \"summary\": 1-2 frases sobre el estado del brief.\n"
            ."- \"suggested_additions\": texto corto con preguntas o puntos que el cliente debería añadir.\n"
            ."- \"improved_brief\": reescritura del brief en español, en primera persona o neutra, incorporando solo lo que el usuario ya dijo y placeholders claros [entre corchetes] para lo que falte.\n"
            .'Sé concreto y orientado a productos digitales (web, apps, marca, diseño).';

        $userMessage = $instructions."\n\nREQUISITOS:\n".$requirementsJson."\n\n---\n\nBRIEF DEL CLIENTE:\n\n".$brief;

        try
        {
            $agent = agent(
                instructions: $instructions,
                messages: [],
                tools: [],
            );
            $response = $agent->prompt($userMessage, [], AiTasks::provider('assistant'));
            $text = $response->text ?: '';
        } catch (\Throwable $e)
        {
            Log::error('ProjectBudgetSpecService::guideBrief failed', ['error' => $e->getMessage()]);

            throw new RuntimeException(__('Error al comunicar con la IA: ').$e->getMessage(), 0, $e);
        }

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $m))
        {
            $text = trim($m[1]);
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded))
        {
            throw new RuntimeException(__('La respuesta no es un JSON válido.'));
        }

        $evaluated = $this->mapRequirementsFromAi($requirements, is_array($decoded['requirements'] ?? null) ? $decoded['requirements'] : []);
        $allMet = collect($evaluated)->every(fn (array $r): bool => $r['met'] === true);

        return [
            'requirements' => $evaluated,
            'summary' => (string) ($decoded['summary'] ?? ''),
            'suggested_additions' => (string) ($decoded['suggested_additions'] ?? ''),
            'improved_brief' => (string) ($decoded['improved_brief'] ?? ''),
            'all_met' => $allMet,
        ];
    }

    /**
     * @param  array<int, array{key: string, name: string, hint: string}>  $requirements
     * @param  array<int, mixed>  $aiRequirements
     * @return array<int, array{key: string, name: string, hint: string, met: bool, feedback: string}>
     */
    private function mapRequirementsFromAi(array $requirements, array $aiRequirements): array
    {
        $byKey = [];
        foreach ($aiRequirements as $item)
        {
            if (! is_array($item) || empty($item['key']))
            {
                continue;
            }
            $byKey[(string) $item['key']] = $item;
        }

        $evaluated = [];
        foreach ($requirements as $requirement)
        {
            $match = $byKey[$requirement['key']] ?? [];
            $evaluated[] = [
                'key' => $requirement['key'],
                'name' => $requirement['name'],
                'hint' => $requirement['hint'],
                'met' => (bool) ($match['met'] ?? false),
                'feedback' => (string) ($match['feedback'] ?? ''),
            ];
        }

        return $evaluated;
    }

    /**
     * @return array<int, array{key: string, name: string, hint: string}>
     */
    public function techGuideRequirements(): array
    {
        return [
            [
                'key' => 'objetivo',
                'name' => 'Objetivo',
                'hint' => 'Qué problema resuelve o qué meta de negocio persigue el producto.',
            ],
            [
                'key' => 'negocio',
                'name' => 'Negocio',
                'hint' => 'Nombre del negocio, marca o proyecto.',
            ],
            [
                'key' => 'usuarios',
                'name' => 'Usuarios',
                'hint' => 'Para quién es: roles, perfiles o públicos que lo usarán.',
            ],
            [
                'key' => 'funcionalidades',
                'name' => 'Funcionalidades',
                'hint' => 'Qué debe poder hacer el sistema (módulos, flujos clave).',
            ],
            [
                'key' => 'plataforma',
                'name' => 'Plataforma',
                'hint' => 'Medio técnico: web, app móvil, API, panel admin, integraciones.',
            ],
            [
                'key' => 'urls',
                'name' => 'URLs',
                'hint' => 'URL actual (si tiene) y URLs de referencia o inspiración.',
            ],
            [
                'key' => 'diseno',
                'name' => 'Diseño',
                'hint' => 'Si ya tiene gráfica, si será a medida o template, y nivel de diseño.',
            ],
            [
                'key' => 'alcance',
                'name' => 'Alcance y plazos',
                'hint' => 'Fases, MVP vs alcance completo, o plazos deseados.',
            ],
        ];
    }

    /**
     * Client-facing payload: tasks, times and resource type (no prices).
     *
     * @param  array<string, mixed>  $spec
     * @return array{
     *   ai_interpretation: string,
     *   dimension: string,
     *   estimated_times: string,
     *   resources: string,
     *   suggested_tasks: array<int, array{title: string, description: string, category_name: string, estimated_hours: float|int|string|null, resource_level: string, included: bool}>
     * }
     */
    public function toClientSafe(array $spec): array
    {
        $tasks = is_array($spec['suggested_tasks'] ?? null) ? $spec['suggested_tasks'] : [];

        return [
            'ai_interpretation' => (string) ($spec['ai_interpretation'] ?? ''),
            'dimension' => (string) ($spec['dimension'] ?? ''),
            'estimated_times' => (string) ($spec['estimated_times'] ?? ''),
            'resources' => (string) ($spec['resources'] ?? ''),
            'suggested_tasks' => array_values(array_map(function (array $task): array
            {
                return [
                    'title' => (string) ($task['title'] ?? ''),
                    'description' => (string) ($task['description'] ?? ''),
                    'category_name' => (string) ($task['category_name'] ?? ''),
                    'estimated_hours' => $task['estimated_hours'] ?? null,
                    'resource_level' => (string) ($task['resource_level'] ?? ''),
                    'included' => array_key_exists('included', $task) ? (bool) $task['included'] : true,
                ];
            }, $tasks)),
        ];
    }

    /**
     * Merge client task edits (include/hours/title) onto the server-side priced quote.
     *
     * @param  array<string, mixed>  $cachedSpec
     * @param  array<int, array<string, mixed>>  $clientTasks
     * @return array<string, mixed>
     */
    public function mergeClientTaskEdits(array $cachedSpec, array $clientTasks): array
    {
        $original = is_array($cachedSpec['suggested_tasks'] ?? null) ? $cachedSpec['suggested_tasks'] : [];
        $merged = [];

        foreach ($clientTasks as $index => $clientTask)
        {
            if (! is_array($clientTask))
            {
                continue;
            }

            $base = is_array($original[$index] ?? null) ? $original[$index] : [];
            $title = trim((string) ($clientTask['title'] ?? $base['title'] ?? ''));
            if ($title === '')
            {
                continue;
            }

            $merged[] = [
                'title' => $title,
                'description' => (string) ($clientTask['description'] ?? $base['description'] ?? ''),
                'category_name' => (string) ($clientTask['category_name'] ?? $base['category_name'] ?? ''),
                'estimated_hours' => $clientTask['estimated_hours'] ?? $base['estimated_hours'] ?? null,
                'resource_level' => (string) ($clientTask['resource_level'] ?? $base['resource_level'] ?? ''),
                'unit_price' => $base['unit_price'] ?? null,
                'included' => array_key_exists('included', $clientTask)
                    ? (bool) $clientTask['included']
                    : (array_key_exists('included', $base) ? (bool) $base['included'] : true),
            ];
        }

        $cachedSpec['suggested_tasks'] = $this->normalizeSuggestedTasks($merged);

        return $cachedSpec;
    }

    /**
     * @param  array<int, mixed>  $tasks
     * @return array<int, array<string, mixed>>
     */
    public function normalizeSuggestedTasks(array $tasks): array
    {
        return array_values(array_map(function ($t): array
        {
            if (! is_array($t))
            {
                $t = [];
            }

            if (isset($t['unit_price']))
            {
                $v = $t['unit_price'];
                if (is_numeric($v))
                {
                    $t['unit_price'] = (float) $v;
                } elseif (is_string($v))
                {
                    $normalized = preg_replace('/[\s\x{00A0}]/u', '', $v);
                    if (preg_match('/^\d{1,3}(?:\.\d{3})*,\d+$/', $normalized))
                    {
                        $normalized = str_replace('.', '', $normalized);
                        $normalized = str_replace(',', '.', $normalized);
                    } else
                    {
                        $normalized = str_replace(',', '.', $normalized);
                    }
                    if (is_numeric($normalized))
                    {
                        $t['unit_price'] = (float) $normalized;
                    }
                }
            }

            if (! isset($t['resource_level']) || $t['resource_level'] === null)
            {
                $t['resource_level'] = '';
            } else
            {
                $t['resource_level'] = (string) $t['resource_level'];
            }

            if (! array_key_exists('included', $t))
            {
                $t['included'] = true;
            } else
            {
                $t['included'] = (bool) $t['included'];
            }

            $t['description'] = (string) ($t['description'] ?? '');

            return $t;
        }, $tasks));
    }

    private function getTaskCategoriesContextForAi(?Team $team): string
    {
        $tasksModule = Module::where('key', 'tasks')->first();
        if (! $tasksModule)
        {
            return '';
        }

        $teamId = $team?->id;

        $categories = Category::where('module_id', $tasksModule->id)
            ->where('status', 1)
            ->where(function ($q) use ($teamId)
            {
                $q->whereNull('team_id');
                if ($teamId)
                {
                    $q->orWhere('team_id', $teamId);
                }
            })
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        if ($categories->isEmpty())
        {
            return '';
        }

        $names = $categories->pluck('name')->unique()->values()->all();
        $namesList = implode(', ', $names);

        return "TASK SUGGESTIONS (use only when the budget describes concrete tasks or work packages):\n"
            .'- Available task categories in the system (use ONLY these exact names for category_name): '.$namesList."\n"
            ."- Add a key \"suggested_tasks\" to your JSON: an array of objects, each with \"title\", \"description\" (1-2 sentences explaining the section/work), \"category_name\" (one of the names above), \"estimated_hours\" (decimal), \"resource_level\", and \"unit_price\".\n"
            ."- **resource_level** (string): You MUST suggest a level for every task. Use typical roles: Senior (architecture, lead, complex work), Junior (routine implementation, support), Consultor (analysis, advice, audits). Infer from the type of work and complexity.\n"
            ."- **unit_price** (number): You MUST suggest a monetary value for every task. (1) If the budget explicitly states a price for that line/module, use it (plain number, e.g. 1500 or 1250.50, no currency or thousands separator). (2) If the budget does NOT give per-line prices, estimate unit_price using: (a) typical market rates for that type of work (e.g. development, consulting) and region (e.g. EU/Spain), (b) the scope/quantity (estimated_hours × reasonable hourly rate, or a realistic fixed price for that module). Always output a number so the user gets a suggested quote; the user can adjust later.\n"
            .'- Suggest between 0 and 15 tasks. Leave suggested_tasks as empty array [] only if the budget does not describe concrete tasks. Every suggested task must have description, resource_level and unit_price.';
    }

    private function getDefaultBudgetSpecPrompt(): string
    {
        return "You are an expert at interpreting project budgets and technical proposals, especially for software development.\n\nGiven the budget text we received from the client, respond with ONLY a valid JSON object (no markdown, no code block wrapper, no explanation).\nUse exactly these keys:\n- \"ai_interpretation\": Short summary of what you understood from the budget (scope, intent, main deliverables). 1-2 paragraphs.\n- \"dimension\": Scope and size of the project (features, modules, deliverables, complexity).\n- \"estimated_times\": Realistic timeline (phases, milestones, total duration).\n- \"resources\": Human and technical resources (roles, team size, tools, infrastructure).\n- \"suggested_tasks\": (optional) Array: each object with \"title\", \"description\" (short explanation of the section), \"category_name\" (match existing task category), \"estimated_hours\" (decimal), \"resource_level\" (Senior/Junior/Consultor), \"unit_price\" (number). Use empty array if not applicable.\n\nWrite in the same language as the budget text. Be concrete and professional. Keep each field to 2-4 short paragraphs. Every suggested task must include description, resource_level and unit_price.";
    }
}
