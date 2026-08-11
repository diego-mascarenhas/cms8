<?php

namespace App\Services;

use App\Helpers\Helpers;
use App\Models\Category;
use App\Models\Module;
use App\Models\Project;
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
    public const DEFAULT_AI_USAGE_PERCENT = 70.0;

    /** Max drop of (labor + tokens) vs original when shifting hours→tokens. */
    public const MAX_BALANCE_DISCOUNT_PERCENT = 30.0;

    /** Blended AI cost €/M tokens (70% input @ 11 + 30% output @ 55). */
    public const TOKEN_BLEND_EUR_PER_MILLION = 24.2;

    /**
     * Generate a full budget specification (includes prices for internal use).
     *
     * @return array{
     *   ai_interpretation: string,
     *   dimension: string,
     *   estimated_times: string,
     *   resources: string,
     *   token_consumption: array<string, mixed>,
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
            $response = $agent->prompt(
                $userMessage,
                [],
                AiTasks::provider('assistant'),
                null,
                $this->budgetSpecTimeout(),
            );
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
        $suggestedTasks = $this->normalizeSuggestedTasks($suggestedTasks);
        $tokenConsumption = $this->normalizeTokenConsumption(
            $decoded['token_consumption'] ?? null,
            $suggestedTasks,
        );

        return [
            'ai_interpretation' => (string) ($decoded['ai_interpretation'] ?? ''),
            'dimension' => (string) ($decoded['dimension'] ?? ''),
            'estimated_times' => (string) ($decoded['estimated_times'] ?? ''),
            'resources' => (string) ($decoded['resources'] ?? ''),
            'token_consumption' => $tokenConsumption,
            'client_items' => is_array($decoded['client_items'] ?? null) ? $decoded['client_items'] : [],
            'resource_breakdown' => is_array($decoded['resource_breakdown'] ?? null) ? $decoded['resource_breakdown'] : [],
            'suggested_tasks' => $suggestedTasks,
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
     *   business_name: string|null,
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
                'business_name' => null,
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
            ."- \"business_name\": nombre del negocio o marca si el cliente lo dijo (solo el nombre, sin inventar), o null.\n"
            ."- \"project_name\": nombre corto del proyecto (usa business_name si existe) o null.\n"
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
            $response = $agent->prompt(
                $userMessage,
                [],
                AiTasks::provider('assistant'),
                null,
                $this->budgetSpecTimeout(),
            );
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

        $businessName = $decoded['business_name'] ?? null;
        $businessName = is_string($businessName) && trim($businessName) !== '' ? trim($businessName) : null;

        $suggestedName = $decoded['project_name'] ?? null;
        $suggestedName = is_string($suggestedName) && trim($suggestedName) !== '' ? trim($suggestedName) : null;
        if ($suggestedName === null && $businessName !== null)
        {
            $suggestedName = $businessName;
        }

        return [
            'assistant_message' => $assistantMessage,
            'requirements' => $evaluated,
            'brief' => $brief,
            'project_name' => $suggestedName,
            'business_name' => $businessName,
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
            $response = $agent->prompt(
                $userMessage,
                [],
                AiTasks::provider('assistant'),
                null,
                $this->budgetSpecTimeout(),
            );
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
     *   token_consumption: array<string, mixed>,
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
            'token_consumption' => $this->normalizeTokenConsumption($spec['token_consumption'] ?? null, $tasks),
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
                'estimated_tokens' => $clientTask['estimated_tokens'] ?? $base['estimated_tokens'] ?? null,
                'unit_price' => $base['unit_price'] ?? null,
                'included' => array_key_exists('included', $clientTask)
                    ? (bool) $clientTask['included']
                    : (array_key_exists('included', $base) ? (bool) $base['included'] : true),
            ];
        }

        $cachedSpec['suggested_tasks'] = $this->normalizeSuggestedTasks($merged);
        $cachedSpec['token_consumption'] = $this->normalizeTokenConsumption(
            $cachedSpec['token_consumption'] ?? null,
            $cachedSpec['suggested_tasks'],
        );

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
            $t['estimated_tokens'] = $this->resolveEstimatedTokens($t);

            return $t;
        }, $tasks));
    }

    /**
     * MCP-style token consumption payload. Notes hold one labor line each.
     *
     * @param  array<int, array<string, mixed>>  $tasks
     * @param  array<string, mixed>|null  $existing
     * @return array{
     *   notes: string,
     *   input_tokens: int,
     *   output_tokens: int,
     *   total_tokens: int,
     *   cost_euros: float,
     *   savings_percent: float,
     *   billable_euros: float,
     *   currency: string
     * }
     */
    public function buildTokenConsumption(array $tasks, ?array $existing = null): array
    {
        $notes = $this->buildTokenConsumptionNotes($tasks);
        $totalTokens = 0;
        foreach ($tasks as $task)
        {
            if (! is_array($task))
            {
                continue;
            }
            if (array_key_exists('included', $task) && ! $task['included'])
            {
                continue;
            }
            $totalTokens += $this->resolveEstimatedTokens($task);
        }

        $inputTokens = (int) round($totalTokens * 0.7);
        $outputTokens = max(0, $totalTokens - $inputTokens);
        $savingsPercent = isset($existing['savings_percent']) && is_numeric($existing['savings_percent'])
            ? (float) $existing['savings_percent']
            : 57.0;
        $cost = $this->estimateTokenCostEuros($inputTokens, $outputTokens);
        $remaining = max(0.01, 1 - ($savingsPercent / 100));
        $billable = round($cost / $remaining, 2);

        return [
            'notes' => $notes,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $totalTokens,
            'cost_euros' => $cost,
            'savings_percent' => $savingsPercent,
            'billable_euros' => $billable,
            'currency' => (string) ($existing['currency'] ?? 'EUR'),
        ];
    }

    /**
     * Normalize legacy string or partial array into the MCP token_consumption shape.
     *
     * @param  array<int, array<string, mixed>>  $tasks
     * @return array{
     *   notes: string,
     *   input_tokens: int,
     *   output_tokens: int,
     *   total_tokens: int,
     *   cost_euros: float,
     *   savings_percent: float,
     *   billable_euros: float,
     *   currency: string
     * }
     */
    public function normalizeTokenConsumption(mixed $value, array $tasks = []): array
    {
        $existing = is_array($value) ? $value : [];
        $built = $this->buildTokenConsumption($tasks, $existing);

        if (is_string($value) && trim($value) !== '')
        {
            $built['notes'] = $this->stripTokenConsumptionPrefix(trim($value));
        } elseif (is_array($value))
        {
            $notes = $value['notes'] ?? null;
            if (is_string($notes) && trim($notes) !== '')
            {
                $built['notes'] = $this->stripTokenConsumptionPrefix(trim($notes));
            } elseif (is_array($notes))
            {
                $built['notes'] = $this->stripTokenConsumptionPrefix(implode("\n", array_map(
                    static fn ($line) => trim((string) $line),
                    $notes,
                )));
            }

            foreach (['input_tokens', 'output_tokens', 'total_tokens'] as $key)
            {
                if (isset($value[$key]) && is_numeric($value[$key]) && (int) $value[$key] > 0)
                {
                    $built[$key] = (int) $value[$key];
                }
            }
            if (isset($value['cost_euros']) && is_numeric($value['cost_euros']))
            {
                $built['cost_euros'] = round((float) $value['cost_euros'], 2);
            }
            if (isset($value['billable_euros']) && is_numeric($value['billable_euros']))
            {
                $built['billable_euros'] = round((float) $value['billable_euros'], 2);
            }
            if (isset($value['savings_percent']) && is_numeric($value['savings_percent']))
            {
                $built['savings_percent'] = (float) $value['savings_percent'];
            }
            if (isset($value['currency']) && is_string($value['currency']) && $value['currency'] !== '')
            {
                $built['currency'] = $value['currency'];
            }
        }

        if ($built['notes'] === '' && $tasks !== [])
        {
            $built['notes'] = $this->buildTokenConsumptionNotes($tasks);
        }

        if (($built['total_tokens'] ?? 0) <= 0 && $tasks !== [])
        {
            $rebuilt = $this->buildTokenConsumption($tasks, $built);
            $built['input_tokens'] = $rebuilt['input_tokens'];
            $built['output_tokens'] = $rebuilt['output_tokens'];
            $built['total_tokens'] = $rebuilt['total_tokens'];
            $built['cost_euros'] = $rebuilt['cost_euros'];
            $built['billable_euros'] = $rebuilt['billable_euros'];
        }

        return $built;
    }

    public function tokenConsumptionNotes(mixed $value): string
    {
        if (is_string($value))
        {
            return $this->stripTokenConsumptionPrefix(trim($value));
        }
        if (! is_array($value))
        {
            return '';
        }
        $notes = $value['notes'] ?? '';
        if (is_array($notes))
        {
            return $this->stripTokenConsumptionPrefix(implode("\n", array_map(static fn ($line) => trim((string) $line), $notes)));
        }

        return $this->stripTokenConsumptionPrefix(trim((string) $notes));
    }

    public function stripTokenConsumptionPrefix(string $notes): string
    {
        if ($notes === '')
        {
            return '';
        }

        $lines = preg_split("/\r\n|\n|\r/", $notes) ?: [];
        $cleaned = [];
        foreach ($lines as $line)
        {
            $line = trim((string) $line);
            if ($line === '')
            {
                continue;
            }
            $line = preg_replace('/^Tokens\s+AI\s*[—\-–:]\s*/iu', '', $line) ?? $line;
            $cleaned[] = trim($line);
        }

        return implode("\n", $cleaned);
    }

    /**
     * One token-consumption line per labor/task (MCP billing style).
     *
     * @param  array<int, array<string, mixed>>  $tasks
     */
    public function buildTokenConsumptionNotes(array $tasks): string
    {
        $lines = [];
        foreach ($tasks as $task)
        {
            if (! is_array($task))
            {
                continue;
            }
            if (array_key_exists('included', $task) && ! $task['included'])
            {
                continue;
            }

            $title = trim((string) ($task['title'] ?? ''));
            if ($title === '')
            {
                continue;
            }

            $tokens = $this->resolveEstimatedTokens($task);
            if ($tokens <= 0)
            {
                continue;
            }

            $lines[] = $title.': '.$this->formatTokenCount($tokens);
        }

        return implode("\n", $lines);
    }

    public function estimateTokenCostEuros(int $inputTokens, int $outputTokens): float
    {
        $inputRate = 11.0;
        $outputRate = 55.0;

        return round(($inputTokens / 1_000_000) * $inputRate + ($outputTokens / 1_000_000) * $outputRate, 2);
    }

    /**
     * Quote totals from suggested tasks (same math as the public budget preview), with project.price fallback.
     *
     * @return array{
     *     grand_total: int,
     *     discount_percent: float,
     *     discounted_total: int,
     *     payable_total: int
     * }
     */
    public function computeQuoteTotals(Project $project): array
    {
        $data = is_array($project->data) ? $project->data : [];
        $suggestedTasks = is_array($data['suggested_tasks'] ?? null) ? $data['suggested_tasks'] : [];
        $savings = (float) data_get($data, 'token_consumption.savings_percent', 57);
        $aiUsage = $this->normalizeAiUsagePercent(
            data_get($data, 'ai_usage_percent', self::DEFAULT_AI_USAGE_PERCENT),
        );

        $totalLabor = 0.0;
        $totalTokenBillable = 0.0;

        foreach ($suggestedTasks as $task)
        {
            if (! is_array($task) || (($task['included'] ?? true) === false))
            {
                continue;
            }

            $hours = isset($task['estimated_hours']) && is_numeric($task['estimated_hours'])
                ? (float) $task['estimated_hours']
                : 0.0;
            $price = isset($task['unit_price']) && $task['unit_price'] !== '' && $task['unit_price'] !== null
                ? (float) $task['unit_price']
                : null;
            $baseTokens = $this->resolveEstimatedTokens($task);
            $balanced = $this->applyHoursTokensBalance($price, $hours, $baseTokens, $savings, $aiUsage);
            $rounded = $this->roundLaborToHalfHourSteps($balanced['labor'], $balanced['hours']);

            if ($rounded['labor'] !== null)
            {
                $totalLabor += $rounded['labor'];
            }
            $totalTokenBillable += $balanced['token_billable'];
        }

        $grandTotal = (int) round($totalLabor + $totalTokenBillable);
        $usedPriceFallback = false;

        if ($grandTotal <= 0 && is_numeric($project->price) && (float) $project->price > 0)
        {
            $grandTotal = (int) round((float) $project->price);
            $usedPriceFallback = true;
        }

        $discountPercent = is_numeric($project->discount)
            ? max(0.0, min(100.0, (float) $project->discount))
            : 0.0;

        if ($usedPriceFallback)
        {
            $discountedTotal = (int) round($grandTotal * (1 - ($discountPercent / 100)));
        } else
        {
            // Commercial discount applies to labor only; tokens stay full price.
            $discountedLabor = round($totalLabor * (1 - ($discountPercent / 100)), 2);
            $discountedTotal = (int) round($discountedLabor + $totalTokenBillable);
        }

        $payableTotal = $discountPercent > 0 ? $discountedTotal : $grandTotal;

        return [
            'grand_total' => $grandTotal,
            'discount_percent' => $discountPercent,
            'discounted_total' => $discountedTotal,
            'payable_total' => $payableTotal,
        ];
    }

    /**
     * Shift billable hours onto tokens by balance %.
     * Hours and labor € drop with the slider (seniority rate stays).
     * Commercial total eases from 100% of original down to (100 − MAX_BALANCE_DISCOUNT)% as balance → 100%,
     * by topping up token volume (advanced-model justification).
     *
     * @return array{
     *     hours: float,
     *     labor: ?float,
     *     tokens: int,
     *     display_tokens: int,
     *     cost: float,
     *     token_billable: float,
     *     transferred_hours: float,
     *     original_total: float,
     *     target_total: float
     * }
     */
    public function applyHoursTokensBalance(
        float|int|string|null $unitPrice,
        float|int|string|null $hours,
        int $baseTokens,
        float|int|string|null $savingsPercent = 57,
        float|int|string|null $balancePercent = 0,
    ): array {
        $balance = is_numeric($balancePercent)
            ? max(0.0, min(100.0, (float) $balancePercent))
            : 0.0;
        $savings = is_numeric($savingsPercent) ? (float) $savingsPercent : 57.0;
        $savings = max(0.0, min(99.0, $savings));
        $remainingFactor = max(0.01, 1 - ($savings / 100));

        $hoursValue = is_numeric($hours) ? max(0.0, (float) $hours) : 0.0;
        $baseTokens = max(0, $baseTokens);

        $baseInput = (int) round($baseTokens * 0.7);
        $baseOutput = max(0, $baseTokens - $baseInput);
        $baseCost = $this->estimateTokenCostEuros($baseInput, $baseOutput);
        $baseBillable = round($baseCost / $remainingFactor, 2);

        $originalLabor = ($unitPrice !== null && $unitPrice !== '' && is_numeric($unitPrice))
            ? (float) $unitPrice
            : 0.0;
        $originalTotal = round($originalLabor + $baseBillable, 2);

        $transferredHours = round($hoursValue * ($balance / 100), 4);
        $hoursCharged = round(max(0.0, $hoursValue - $transferredHours), 4);

        $labor = null;
        if ($unitPrice !== null && $unitPrice !== '' && is_numeric($unitPrice))
        {
            $labor = round($originalLabor * (1 - ($balance / 100)), 2);
        }
        $laborCharged = $labor ?? 0.0;

        $extraTokens = (int) round($transferredHours * 20000);
        $tokens = $baseTokens + $extraTokens;

        // 0% → full original total; 100% → original × (1 − 30%) = 70% of original.
        $discountFactor = 1 - ((self::MAX_BALANCE_DISCOUNT_PERCENT / 100) * ($balance / 100));
        $targetTotal = round($originalTotal * $discountFactor, 2);
        $tokenBillableTarget = max(0.0, round($targetTotal - $laborCharged, 2));

        $tokensForTarget = $this->tokensNeededForBillable($tokenBillableTarget, $remainingFactor);
        $tokens = max($tokens, $tokensForTarget);

        $input = (int) round($tokens * 0.7);
        $output = max(0, $tokens - $input);
        $cost = $this->estimateTokenCostEuros($input, $output);
        $tokenBillable = round($cost / $remainingFactor, 2);
        $displayTokens = (int) round($tokens / $remainingFactor);

        return [
            'hours' => $hoursCharged,
            'labor' => $labor,
            'tokens' => $tokens,
            'display_tokens' => $displayTokens,
            'cost' => $cost,
            'token_billable' => $tokenBillable,
            'transferred_hours' => $transferredHours,
            'original_total' => $originalTotal,
            'target_total' => $targetTotal,
        ];
    }

    /**
     * Round billable hours up to 30-minute steps and scale labor euros proportionally.
     *
     * @return array{hours: float, labor: ?float}
     */
    public function roundLaborToHalfHourSteps(?float $labor, float $hours): array
    {
        $roundedHours = Helpers::ceilHoursToHalfHour($hours) ?? 0.0;

        if ($labor === null)
        {
            return ['hours' => $roundedHours, 'labor' => null];
        }

        if ($hours <= 0)
        {
            return ['hours' => $roundedHours, 'labor' => round($labor, 2)];
        }

        if (abs($roundedHours - $hours) < 0.00001)
        {
            return ['hours' => $roundedHours, 'labor' => round($labor, 2)];
        }

        return [
            'hours' => $roundedHours,
            'labor' => round($labor * ($roundedHours / $hours), 2),
        ];
    }

    /**
     * Invert billable euros → raw tokens (before MCP display inflate).
     */
    public function tokensNeededForBillable(float $billableEuros, float $remainingFactor): int
    {
        if ($billableEuros <= 0)
        {
            return 0;
        }

        $remainingFactor = max(0.01, $remainingFactor);
        $costNeeded = $billableEuros * $remainingFactor;
        $blend = self::TOKEN_BLEND_EUR_PER_MILLION;

        return (int) max(0, (int) ceil(($costNeeded * 1_000_000) / $blend));
    }

    /**
     * @deprecated Prefer applyHoursTokensBalance(); kept for callers that only need labor euros.
     */
    public function laborValueAfterAi(float|int|string|null $unitPrice, float|int|string|null $aiUsagePercent): ?float
    {
        return $this->applyHoursTokensBalance($unitPrice, 1, 0, 57, $aiUsagePercent)['labor'];
    }

    /**
     * Normalize hours↔tokens balance percentage for the budget (0–100).
     * Higher values shift hours onto tokens.
     */
    public function normalizeAiUsagePercent(mixed $value): float
    {
        if (! is_numeric($value))
        {
            return self::DEFAULT_AI_USAGE_PERCENT;
        }

        return max(0.0, min(100.0, (float) $value));
    }

    /**
     * @param  array<string, mixed>  $task
     */
    public function resolveEstimatedTokens(array $task): int
    {
        if (isset($task['estimated_tokens']) && is_numeric($task['estimated_tokens']))
        {
            return max(0, (int) round((float) $task['estimated_tokens']));
        }

        $hours = isset($task['estimated_hours']) && is_numeric($task['estimated_hours'])
            ? (float) $task['estimated_hours']
            : 0.0;

        if ($hours <= 0)
        {
            return 0;
        }

        // Heuristic aligned with MCP AI-billing labors (~20K tokens per assisted hour).
        return (int) round($hours * 20000);
    }

    public function formatTokenCount(int $tokens): string
    {
        if ($tokens <= 0)
        {
            return '—';
        }

        if ($tokens >= 1_000_000)
        {
            $m = $tokens / 1_000_000;

            return rtrim(rtrim(number_format($m, 1, ',', ''), '0'), ',').' M';
        }

        if ($tokens >= 1000)
        {
            return number_format($tokens / 1000, 1, ',', '').' K';
        }

        return (string) $tokens;
    }

    private function budgetSpecTimeout(): int
    {
        return max(60, (int) config('ai.budget_spec_timeout', 180));
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
            ."- Add a key \"suggested_tasks\" to your JSON: an array of objects, each with \"title\", \"description\" (1-2 sentences explaining the section/work), \"category_name\" (one of the names above), \"estimated_hours\" (decimal), \"resource_level\", \"unit_price\", and \"estimated_tokens\".\n"
            ."- **resource_level** (string): You MUST suggest a level for every task. Use typical roles: Senior (architecture, lead, complex work), Junior (routine implementation, support), Consultor (analysis, advice, audits). Infer from the type of work and complexity.\n"
            ."- **unit_price** (number): You MUST suggest a monetary value for every task. (1) If the budget explicitly states a price for that line/module, use it (plain number, e.g. 1500 or 1250.50, no currency or thousands separator). (2) If the budget does NOT give per-line prices, estimate unit_price using: (a) typical market rates for that type of work (e.g. development, consulting) and region (e.g. EU/Spain), (b) the scope/quantity (estimated_hours × reasonable hourly rate, or a realistic fixed price for that module). Always output a number so the user gets a suggested quote; the user can adjust later.\n"
            ."- **estimated_tokens** (integer): Estimated AI token consumption for that labor (prompt + completion). Use roughly 15k–25k tokens per assisted hour depending on complexity; output a plain integer (e.g. 160000).\n"
            ."- Also add \"token_consumption\": an object with \"notes\" (ONE line per labor, format exactly: \"{title}: {N} K\" — no \"Tokens AI\" prefix), optional totals, currency EUR.\n"
            .'- Suggest between 0 and 15 tasks. Leave suggested_tasks as empty array [] only if the budget does not describe concrete tasks. Every suggested task must have description, resource_level, unit_price and estimated_tokens.';
    }

    private function getDefaultBudgetSpecPrompt(): string
    {
        return "You are an expert at interpreting project budgets and technical proposals, especially for software development.\n\nGiven the budget text we received from the client, respond with ONLY a valid JSON object (no markdown, no code block wrapper, no explanation).\nUse exactly these keys:\n- \"ai_interpretation\": Short summary of what you understood from the budget (scope, intent, main deliverables). 1-2 paragraphs.\n- \"dimension\": Scope and size of the project (features, modules, deliverables, complexity).\n- \"estimated_times\": Realistic timeline (phases, milestones, total duration).\n- \"resources\": Human and technical resources (roles, team size, tools, infrastructure).\n- \"token_consumption\": Object with \"notes\" (one line per labor: \"{title}: {N} K\", no Tokens AI prefix), and optional input_tokens/output_tokens/total_tokens/cost_euros/savings_percent/billable_euros/currency.\n- \"suggested_tasks\": (optional) Array: each object with \"title\", \"description\" (short explanation of the section), \"category_name\" (match existing task category), \"estimated_hours\" (decimal), \"resource_level\" (Senior/Junior/Consultor), \"unit_price\" (number), \"estimated_tokens\" (integer). Use empty array if not applicable.\n\nWrite in the same language as the budget text. Be concrete and professional. Keep each field to 2-4 short paragraphs. Every suggested task must include description, resource_level, unit_price and estimated_tokens.";
    }
}
