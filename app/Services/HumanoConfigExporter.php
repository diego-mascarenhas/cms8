<?php

namespace App\Services;

use App\Enums\AutomationKind;
use App\Models\Automation;
use App\Models\Prompt;
use InvalidArgumentException;

class HumanoConfigExporter
{
    public const VERSION = 1;

    public const TYPE_FUNNEL = 'funnel';

    public const TYPE_ACTION = 'action';

    public const TYPE_PROMPT = 'prompt';

    public function __construct(
        protected AutomationFlowGraphSyncer $graphSyncer,
    ) {}

    /**
     * @return array{humano_export: array<string, mixed>, payload: array<string, mixed>}
     */
    public function exportAutomation(Automation $automation): array
    {
        if ($automation->isFunnel())
        {
            return $this->exportFunnel($automation);
        }

        if ($automation->isAction())
        {
            return $this->exportAction($automation);
        }

        throw new InvalidArgumentException('Unsupported automation kind.');
    }

    /**
     * @return array{humano_export: array<string, mixed>, payload: array<string, mixed>}
     */
    public function exportFunnel(Automation $automation): array
    {
        abort_unless($automation->isFunnel(), 404);

        $graph = $this->portableGraph($automation);
        $actions = $this->referencedActions($automation, $graph);
        $prompts = $this->referencedPrompts(
            (int) $automation->team_id,
            array_filter([
                $automation->resolvedEntryPromptKey(),
                ...collect($graph['nodes'] ?? [])->pluck('prompt_key')->all(),
                ...collect($actions)->pluck('entry_prompt_key')->all(),
            ]),
        );

        return $this->envelope(
            self::TYPE_FUNNEL,
            __('Embudo'),
            [
                'name' => $automation->name,
                'slug' => $automation->slug,
            ],
            [
                'automation' => $this->automationPayload($automation),
                'graph' => $graph,
                'actions' => $actions,
                'prompts' => $prompts,
            ],
        );
    }

    /**
     * @return array{humano_export: array<string, mixed>, payload: array<string, mixed>}
     */
    public function exportAction(Automation $automation): array
    {
        abort_unless($automation->isAction(), 404);

        $prompts = $this->referencedPrompts(
            (int) $automation->team_id,
            array_filter([$automation->resolvedEntryPromptKey()]),
        );

        return $this->envelope(
            self::TYPE_ACTION,
            __('Automatización'),
            [
                'name' => $automation->name,
                'slug' => $automation->slug,
            ],
            [
                'automation' => $this->automationPayload($automation),
                'prompts' => $prompts,
            ],
        );
    }

    /**
     * @return array{humano_export: array<string, mixed>, payload: array<string, mixed>}
     */
    public function exportPrompt(Prompt $prompt): array
    {
        $prompt->loadMissing('module');

        $moduleKey = $prompt->module?->key;
        $routingKey = $moduleKey
            ? $moduleKey.':'.$prompt->section_key
            : $prompt->section_key;

        return $this->envelope(
            self::TYPE_PROMPT,
            __('Prompt'),
            [
                'name' => $prompt->section_label,
                'routing_key' => $routingKey,
            ],
            [
                'prompt' => $this->promptPayload($prompt),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $payload
     * @return array{humano_export: array<string, mixed>, payload: array<string, mixed>}
     */
    protected function envelope(string $type, string $label, array $source, array $payload): array
    {
        return [
            'humano_export' => [
                'type' => $type,
                'label' => $label,
                'belongs_to' => $label,
                'version' => self::VERSION,
                'exported_at' => now()->toIso8601String(),
                'source' => $source,
            ],
            'payload' => $payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function automationPayload(Automation $automation): array
    {
        return [
            'name' => $automation->name,
            'slug' => $automation->slug,
            'kind' => $automation->kind instanceof AutomationKind
                ? $automation->kind->value
                : (string) $automation->kind,
            'is_active' => (bool) $automation->is_active,
            'entry_prompt_key' => $automation->resolvedEntryPromptKey(),
            'channels' => is_array($automation->channels) ? $automation->channels : Automation::defaultChannels(),
            'settings' => is_array($automation->settings) ? $automation->settings : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function promptPayload(Prompt $prompt): array
    {
        $prompt->loadMissing('module');

        return [
            'module_key' => $prompt->module?->key,
            'section_key' => $prompt->section_key,
            'section_label' => $prompt->section_label,
            'prompt_instruction' => $prompt->prompt_instruction,
            'helper_text' => $prompt->helper_text,
            'is_active' => (bool) $prompt->is_active,
            'order' => (int) $prompt->order,
        ];
    }

    /**
     * Graph with exit automations referenced by slug (portable across teams/IDs).
     *
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    protected function portableGraph(Automation $automation): array
    {
        $graph = $this->graphSyncer->export($automation);
        $idToSlug = Automation::query()
            ->withoutGlobalScope('team')
            ->where('team_id', $automation->team_id)
            ->actions()
            ->pluck('slug', 'id')
            ->all();

        $graph['nodes'] = array_map(function (array $node) use ($idToSlug): array
        {
            $outputs = [];
            foreach ($node['outputs'] ?? [] as $output)
            {
                if (! is_array($output))
                {
                    continue;
                }
                $exitId = $output['to_automation_id'] ?? null;
                unset($output['to_automation_id']);
                $output['to_automation_slug'] = ($exitId && isset($idToSlug[(int) $exitId]))
                    ? $idToSlug[(int) $exitId]
                    : null;
                $outputs[] = $output;
            }
            $node['outputs'] = $outputs;

            return $node;
        }, $graph['nodes'] ?? []);

        $graph['edges'] = array_map(function (array $edge) use ($idToSlug): array
        {
            $exitId = $edge['to_automation_id'] ?? null;
            unset($edge['to_automation_id']);
            $edge['to_automation_slug'] = ($exitId && isset($idToSlug[(int) $exitId]))
                ? $idToSlug[(int) $exitId]
                : null;

            return $edge;
        }, $graph['edges'] ?? []);

        return $graph;
    }

    /**
     * @param  array{nodes?: list<array<string, mixed>>, edges?: list<array<string, mixed>>}  $graph
     * @return list<array<string, mixed>>
     */
    protected function referencedActions(Automation $automation, array $graph): array
    {
        $slugs = [];
        foreach ($graph['nodes'] ?? [] as $node)
        {
            foreach ($node['outputs'] ?? [] as $output)
            {
                $slug = trim((string) ($output['to_automation_slug'] ?? ''));
                if ($slug !== '')
                {
                    $slugs[$slug] = true;
                }
            }
        }
        foreach ($graph['edges'] ?? [] as $edge)
        {
            $slug = trim((string) ($edge['to_automation_slug'] ?? ''));
            if ($slug !== '')
            {
                $slugs[$slug] = true;
            }
        }

        if ($slugs === [])
        {
            return [];
        }

        return Automation::query()
            ->withoutGlobalScope('team')
            ->where('team_id', $automation->team_id)
            ->actions()
            ->whereIn('slug', array_keys($slugs))
            ->orderBy('name')
            ->get()
            ->map(fn (Automation $action) => $this->automationPayload($action))
            ->values()
            ->all();
    }

    /**
     * @param  iterable<int, string|null>  $routingKeys
     * @return list<array<string, mixed>>
     */
    protected function referencedPrompts(int $teamId, iterable $routingKeys): array
    {
        $unique = [];
        foreach ($routingKeys as $key)
        {
            $key = is_string($key) ? trim($key) : '';
            if ($key !== '')
            {
                $unique[$key] = true;
            }
        }

        $payloads = [];
        foreach (array_keys($unique) as $routingKey)
        {
            $prompt = Prompt::findByRoutingKey($routingKey, $teamId);
            if ($prompt)
            {
                $payloads[] = $this->promptPayload($prompt);
            }
        }

        return $payloads;
    }
}
