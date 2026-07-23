<?php

namespace App\Services;

use App\Enums\AutomationKind;
use App\Models\Automation;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class HumanoConfigImporter
{
    public function __construct(
        protected AutomationFlowGraphSyncer $graphSyncer,
    ) {}

    /**
     * @param  array<string, mixed>  $document
     * @return array{type: string, model: Automation|Prompt, redirect_route: string, redirect_params: mixed}
     */
    public function import(array $document, Team $team): array
    {
        $header = $document['humano_export'] ?? null;
        if (! is_array($header))
        {
            throw new InvalidArgumentException(__('JSON inválido: falta la cabecera humano_export.'));
        }

        $type = (string) ($header['type'] ?? '');
        $payload = $document['payload'] ?? null;
        if (! is_array($payload))
        {
            throw new InvalidArgumentException(__('JSON inválido: falta payload.'));
        }

        return match ($type)
        {
            HumanoConfigExporter::TYPE_PROMPT => $this->importPrompt($payload, $team),
            HumanoConfigExporter::TYPE_ACTION => $this->importAction($payload, $team),
            HumanoConfigExporter::TYPE_FUNNEL => $this->importFunnel($payload, $team),
            default => throw new InvalidArgumentException(__('Tipo de export desconocido: :type', ['type' => $type ?: '—'])),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{type: string, model: Prompt, redirect_route: string, redirect_params: Prompt}
     */
    protected function importPrompt(array $payload, Team $team): array
    {
        $data = $payload['prompt'] ?? $payload;
        if (! is_array($data))
        {
            throw new InvalidArgumentException(__('Payload de prompt inválido.'));
        }

        $prompt = $this->upsertPrompt($data, $team);

        return [
            'type' => HumanoConfigExporter::TYPE_PROMPT,
            'model' => $prompt,
            'redirect_route' => 'prompt.show',
            'redirect_params' => $prompt,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{type: string, model: Automation, redirect_route: string, redirect_params: Automation}
     */
    protected function importAction(array $payload, Team $team): array
    {
        foreach ($payload['prompts'] ?? [] as $promptData)
        {
            if (is_array($promptData))
            {
                $this->upsertPrompt($promptData, $team);
            }
        }

        $automationData = $payload['automation'] ?? $payload;
        if (! is_array($automationData))
        {
            throw new InvalidArgumentException(__('Payload de automatización inválido.'));
        }

        $automationData['kind'] = AutomationKind::Action->value;
        $automation = $this->createAutomation($automationData, $team);

        return [
            'type' => HumanoConfigExporter::TYPE_ACTION,
            'model' => $automation,
            'redirect_route' => 'automation.show',
            'redirect_params' => $automation,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{type: string, model: Automation, redirect_route: string, redirect_params: Automation}
     */
    protected function importFunnel(array $payload, Team $team): array
    {
        return DB::transaction(function () use ($payload, $team)
        {
            foreach ($payload['prompts'] ?? [] as $promptData)
            {
                if (is_array($promptData))
                {
                    $this->upsertPrompt($promptData, $team);
                }
            }

            /** @var array<string, int> $slugToId */
            $slugToId = [];
            foreach ($payload['actions'] ?? [] as $actionData)
            {
                if (! is_array($actionData))
                {
                    continue;
                }
                $actionData['kind'] = AutomationKind::Action->value;
                $originalSlug = trim((string) ($actionData['slug'] ?? ''));
                $existing = $originalSlug !== ''
                    ? Automation::query()
                        ->withoutGlobalScope('team')
                        ->where('team_id', $team->id)
                        ->actions()
                        ->where('slug', $originalSlug)
                        ->first()
                    : null;

                $action = $existing ?? $this->createAutomation($actionData, $team);
                if ($originalSlug !== '')
                {
                    $slugToId[$originalSlug] = (int) $action->id;
                }
                $slugToId[$action->slug] = (int) $action->id;
            }

            $automationData = $payload['automation'] ?? null;
            if (! is_array($automationData))
            {
                throw new InvalidArgumentException(__('Payload de embudo inválido.'));
            }
            $automationData['kind'] = AutomationKind::Funnel->value;
            $funnel = $this->createAutomation($automationData, $team);

            $graph = $payload['graph'] ?? ['nodes' => [], 'edges' => []];
            if (! is_array($graph))
            {
                $graph = ['nodes' => [], 'edges' => []];
            }

            $this->graphSyncer->sync($funnel, $this->hydrateGraphExitIds($graph, $slugToId));

            return [
                'type' => HumanoConfigExporter::TYPE_FUNNEL,
                'model' => $funnel->fresh(),
                'redirect_route' => 'funnel.show',
                'redirect_params' => $funnel,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function upsertPrompt(array $data, Team $team): Prompt
    {
        $moduleKey = trim((string) ($data['module_key'] ?? ''));
        $sectionKey = trim((string) ($data['section_key'] ?? ''));
        if ($moduleKey === '' || $sectionKey === '')
        {
            throw new InvalidArgumentException(__('El prompt requiere module_key y section_key.'));
        }

        $module = Module::query()->where('key', $moduleKey)->first();
        if (! $module)
        {
            throw new InvalidArgumentException(__('Módulo desconocido: :key', ['key' => $moduleKey]));
        }

        $attributes = [
            'section_label' => trim((string) ($data['section_label'] ?? $sectionKey)) ?: $sectionKey,
            'prompt_instruction' => (string) ($data['prompt_instruction'] ?? ''),
            'helper_text' => isset($data['helper_text']) ? (string) $data['helper_text'] : null,
            'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'order' => (int) ($data['order'] ?? 0),
        ];

        if (trim($attributes['prompt_instruction']) === '')
        {
            throw new InvalidArgumentException(__('La instrucción del prompt no puede estar vacía.'));
        }

        $existing = Prompt::query()
            ->withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('module_id', $module->id)
            ->where('section_key', $sectionKey)
            ->first();

        if ($existing)
        {
            $existing->fill($attributes);
            $existing->save();

            return $existing->fresh(['module']);
        }

        return Prompt::query()->create(array_merge($attributes, [
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => $sectionKey,
        ]))->load('module');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createAutomation(array $data, Team $team): Automation
    {
        $kind = AutomationKind::tryFrom((string) ($data['kind'] ?? '')) ?? AutomationKind::Action;
        $baseSlug = Str::slug((string) ($data['slug'] ?? $data['name'] ?? 'import'));
        if ($baseSlug === '')
        {
            $baseSlug = 'import';
        }

        $name = trim((string) ($data['name'] ?? '')) ?: ucfirst($baseSlug);

        return Automation::query()->create([
            'team_id' => $team->id,
            'name' => $name,
            'slug' => $this->uniqueAutomationSlug($team->id, $baseSlug),
            'kind' => $kind,
            'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'entry_prompt_key' => isset($data['entry_prompt_key']) && trim((string) $data['entry_prompt_key']) !== ''
                ? trim((string) $data['entry_prompt_key'])
                : null,
            'channels' => Automation::normalizeChannels(
                is_array($data['channels'] ?? null) ? $data['channels'] : Automation::defaultChannels(),
                false,
            ),
            'settings' => is_array($data['settings'] ?? null) ? $data['settings'] : [],
            'public_token' => bin2hex(random_bytes(32)),
        ]);
    }

    protected function uniqueAutomationSlug(int $teamId, string $baseSlug): string
    {
        $slug = $baseSlug;
        $i = 2;
        while (
            Automation::query()
                ->withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /**
     * @param  array{nodes?: list<array<string, mixed>>, edges?: list<array<string, mixed>>}  $graph
     * @param  array<string, int>  $slugToId
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    protected function hydrateGraphExitIds(array $graph, array $slugToId): array
    {
        $nodes = [];
        foreach ($graph['nodes'] ?? [] as $node)
        {
            if (! is_array($node))
            {
                continue;
            }
            $outputs = [];
            foreach ($node['outputs'] ?? [] as $output)
            {
                if (! is_array($output))
                {
                    continue;
                }
                $slug = trim((string) ($output['to_automation_slug'] ?? ''));
                unset($output['to_automation_slug']);
                $output['to_automation_id'] = ($slug !== '' && isset($slugToId[$slug]))
                    ? $slugToId[$slug]
                    : null;
                $outputs[] = $output;
            }
            $node['outputs'] = $outputs;
            $nodes[] = $node;
        }

        $edges = [];
        foreach ($graph['edges'] ?? [] as $edge)
        {
            if (! is_array($edge))
            {
                continue;
            }
            $slug = trim((string) ($edge['to_automation_slug'] ?? ''));
            unset($edge['to_automation_slug']);
            $edge['to_automation_id'] = ($slug !== '' && isset($slugToId[$slug]))
                ? $slugToId[$slug]
                : null;
            $edges[] = $edge;
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }
}
