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
     * Client-facing payload: tasks and times only (no prices).
     *
     * @param  array<string, mixed>  $spec
     * @return array{
     *   ai_interpretation: string,
     *   dimension: string,
     *   estimated_times: string,
     *   resources: string,
     *   suggested_tasks: array<int, array{title: string, category_name: string, estimated_hours: float|int|string|null, included: bool}>
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
                    'category_name' => (string) ($task['category_name'] ?? ''),
                    'estimated_hours' => $task['estimated_hours'] ?? null,
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
                'category_name' => (string) ($clientTask['category_name'] ?? $base['category_name'] ?? ''),
                'estimated_hours' => $clientTask['estimated_hours'] ?? $base['estimated_hours'] ?? null,
                'resource_level' => (string) ($base['resource_level'] ?? ''),
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
            ."- Add a key \"suggested_tasks\" to your JSON: an array of objects, each with \"title\", \"category_name\" (one of the names above), \"estimated_hours\" (decimal), \"resource_level\", and \"unit_price\".\n"
            ."- **resource_level** (string): You MUST suggest a level for every task. Use typical roles: Senior (architecture, lead, complex work), Junior (routine implementation, support), Consultor (analysis, advice, audits). Infer from the type of work and complexity.\n"
            ."- **unit_price** (number): You MUST suggest a monetary value for every task. (1) If the budget explicitly states a price for that line/module, use it (plain number, e.g. 1500 or 1250.50, no currency or thousands separator). (2) If the budget does NOT give per-line prices, estimate unit_price using: (a) typical market rates for that type of work (e.g. development, consulting) and region (e.g. EU/Spain), (b) the scope/quantity (estimated_hours × reasonable hourly rate, or a realistic fixed price for that module). Always output a number so the user gets a suggested quote; the user can adjust later.\n"
            .'- Suggest between 0 and 15 tasks. Leave suggested_tasks as empty array [] only if the budget does not describe concrete tasks. Every suggested task must have resource_level and unit_price.';
    }

    private function getDefaultBudgetSpecPrompt(): string
    {
        return "You are an expert at interpreting project budgets and technical proposals, especially for software development.\n\nGiven the budget text we received from the client, respond with ONLY a valid JSON object (no markdown, no code block wrapper, no explanation).\nUse exactly these keys:\n- \"ai_interpretation\": Short summary of what you understood from the budget (scope, intent, main deliverables). 1-2 paragraphs.\n- \"dimension\": Scope and size of the project (features, modules, deliverables, complexity).\n- \"estimated_times\": Realistic timeline (phases, milestones, total duration).\n- \"resources\": Human and technical resources (roles, team size, tools, infrastructure).\n- \"suggested_tasks\": (optional) Array: each object with \"title\", \"category_name\" (match existing task category), \"estimated_hours\" (decimal), \"resource_level\" (always suggest: Senior/Junior/Consultor based on work type and complexity), \"unit_price\" (always suggest: use client price if given, otherwise estimate from market rates and scope/quantity so every line has a value). Use empty array if not applicable.\n\nWrite in the same language as the budget text. Be concrete and professional. Keep each field to 2-4 short paragraphs. Every suggested task must include resource_level and unit_price based on market prices and quantity/scope when the budget does not specify them.";
    }
}
