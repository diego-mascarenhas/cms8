<?php

namespace App\Services;

use App\Enums\AutomationReplyType;
use App\Models\Automation;
use App\Models\AutomationStep;
use App\Models\AutomationTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AutomationFlowGraphSyncer
{
    /**
     * Persist a Drawflow-like graph payload into steps + transitions.
     *
     * Expected payload:
     * {
     *   "nodes": [{"client_id":"1","key":"start","label":"...","prompt_key":null,"instruction":"...","is_entry":true,"position_x":0,"position_y":0,"outputs":[{"id":"output_1","reply_type":"yes_no","match_value":"yes","label":"Sí"}]}],
     *   "edges": [{"from_client_id":"1","from_output":"output_1","to_client_id":"2"}]
     * }
     *
     * @param  array{nodes?: list<array<string, mixed>>, edges?: list<array<string, mixed>>}  $graph
     */
    public function sync(Automation $automation, array $graph): Automation
    {
        $nodes = $graph['nodes'] ?? [];
        $edges = $graph['edges'] ?? [];

        if (! is_array($nodes) || ! is_array($edges))
        {
            throw new InvalidArgumentException('Invalid graph payload.');
        }

        DB::transaction(function () use ($automation, $nodes, $edges)
        {
            $automation->transitions()->delete();
            $automation->steps()->delete();

            /** @var array<string, AutomationStep> $byClientId */
            $byClientId = [];
            $entryCount = 0;

            foreach ($nodes as $index => $node)
            {
                if (! is_array($node))
                {
                    continue;
                }

                $clientId = (string) ($node['client_id'] ?? $index + 1);
                $label = trim((string) ($node['label'] ?? 'Paso '.($index + 1)));
                $key = trim((string) ($node['key'] ?? ''));
                if ($key === '')
                {
                    $key = Str::slug($label, '_') ?: 'step_'.($index + 1);
                }
                $key = Str::slug($key, '_');

                $isEntry = filter_var($node['is_entry'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if ($isEntry)
                {
                    $entryCount++;
                }

                $step = AutomationStep::query()->create([
                    'automation_id' => $automation->id,
                    'key' => $key.'_'.$clientId,
                    'label' => $label !== '' ? $label : 'Paso',
                    'prompt_key' => isset($node['prompt_key']) && trim((string) $node['prompt_key']) !== ''
                        ? trim((string) $node['prompt_key'])
                        : null,
                    'instruction' => isset($node['instruction']) ? (string) $node['instruction'] : null,
                    'is_entry' => $isEntry,
                    'position_x' => (int) ($node['position_x'] ?? 0),
                    'position_y' => (int) ($node['position_y'] ?? 0),
                    'settings' => [
                        'client_id' => $clientId,
                        'outputs' => $node['outputs'] ?? [],
                    ],
                ]);

                $byClientId[$clientId] = $step;
            }

            if ($entryCount === 0 && $byClientId !== [])
            {
                $first = reset($byClientId);
                $first->is_entry = true;
                $first->save();
            }

            if ($entryCount > 1)
            {
                $seen = false;
                foreach ($byClientId as $step)
                {
                    if ($step->is_entry)
                    {
                        if ($seen)
                        {
                            $step->is_entry = false;
                            $step->save();
                        }
                        $seen = true;
                    }
                }
            }

            $sort = 0;
            foreach ($edges as $edge)
            {
                if (! is_array($edge))
                {
                    continue;
                }

                $fromClient = (string) ($edge['from_client_id'] ?? '');
                $toClient = (string) ($edge['to_client_id'] ?? '');
                $fromOutput = (string) ($edge['from_output'] ?? 'output_1');

                if (! isset($byClientId[$fromClient]))
                {
                    continue;
                }

                $fromStep = $byClientId[$fromClient];
                $toStep = $toClient !== '' && isset($byClientId[$toClient]) ? $byClientId[$toClient] : null;

                $outputMeta = $this->findOutputMeta($fromStep, $fromOutput, $edge);
                $replyType = AutomationReplyType::tryFrom((string) ($outputMeta['reply_type'] ?? $edge['reply_type'] ?? 'fallback'))
                    ?? AutomationReplyType::Fallback;

                $toAutomationId = $this->resolveExitAutomationId(
                    $automation,
                    $outputMeta['to_automation_id'] ?? ($edge['to_automation_id'] ?? null),
                );

                // Exit to an action automation replaces the next conversational step.
                if ($toAutomationId !== null)
                {
                    $toStep = null;
                }

                AutomationTransition::query()->create([
                    'automation_id' => $automation->id,
                    'from_step_id' => $fromStep->id,
                    'to_step_id' => $toStep?->id,
                    'to_automation_id' => $toAutomationId,
                    'reply_type' => $replyType,
                    'match_value' => $outputMeta['match_value'] ?? ($edge['match_value'] ?? null),
                    'label' => $outputMeta['label'] ?? ($edge['label'] ?? null),
                    'sort_order' => $sort++,
                    'drawflow_output' => $fromOutput,
                ]);
            }

            // Outputs with an exit automation but no Drawflow edge still become transitions.
            foreach ($byClientId as $fromStep)
            {
                $outputs = data_get($fromStep->settings, 'outputs', []);
                if (! is_array($outputs))
                {
                    continue;
                }

                $existingOutputs = AutomationTransition::query()
                    ->where('from_step_id', $fromStep->id)
                    ->pluck('drawflow_output')
                    ->filter()
                    ->all();

                foreach ($outputs as $output)
                {
                    if (! is_array($output))
                    {
                        continue;
                    }

                    $outId = (string) ($output['id'] ?? '');
                    if ($outId === '' || in_array($outId, $existingOutputs, true))
                    {
                        continue;
                    }

                    $toAutomationId = $this->resolveExitAutomationId(
                        $automation,
                        $output['to_automation_id'] ?? null,
                    );
                    if ($toAutomationId === null)
                    {
                        continue;
                    }

                    $replyType = AutomationReplyType::tryFrom((string) ($output['reply_type'] ?? 'fallback'))
                        ?? AutomationReplyType::Fallback;

                    AutomationTransition::query()->create([
                        'automation_id' => $automation->id,
                        'from_step_id' => $fromStep->id,
                        'to_step_id' => null,
                        'to_automation_id' => $toAutomationId,
                        'reply_type' => $replyType,
                        'match_value' => $output['match_value'] ?? null,
                        'label' => $output['label'] ?? null,
                        'sort_order' => $sort++,
                        'drawflow_output' => $outId,
                    ]);
                }
            }
        });

        return $automation->fresh(['steps.transitions', 'transitions']);
    }

    /**
     * Export graph for the canvas.
     *
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public function export(Automation $automation): array
    {
        $automation->load(['steps.transitions']);
        $nodes = [];
        $edges = [];
        $clientByStepId = [];

        foreach ($automation->steps as $step)
        {
            $clientId = (string) (data_get($step->settings, 'client_id') ?: $step->id);
            $clientByStepId[$step->id] = $clientId;

            $outputs = data_get($step->settings, 'outputs');
            if (! is_array($outputs) || $outputs === [])
            {
                $outputs = [];
                foreach ($step->transitions as $i => $transition)
                {
                    $outputs[] = [
                        'id' => $transition->drawflow_output ?: ('output_'.($i + 1)),
                        'reply_type' => $transition->reply_type->value,
                        'match_value' => $transition->match_value,
                        'label' => $transition->label ?: $transition->reply_type->label(),
                        'to_automation_id' => $transition->to_automation_id,
                    ];
                }
                if ($outputs === [])
                {
                    $outputs[] = [
                        'id' => 'output_1',
                        'reply_type' => AutomationReplyType::Fallback->value,
                        'match_value' => null,
                        'label' => AutomationReplyType::Fallback->label(),
                        'to_automation_id' => null,
                    ];
                }
            }

            $nodes[] = [
                'client_id' => $clientId,
                'key' => $step->key,
                'label' => $step->label,
                'prompt_key' => $step->prompt_key,
                'instruction' => $step->instruction,
                'is_entry' => $step->is_entry,
                'position_x' => $step->position_x,
                'position_y' => $step->position_y,
                'outputs' => $outputs,
            ];
        }

        foreach ($automation->steps as $step)
        {
            $fromClient = $clientByStepId[$step->id] ?? (string) $step->id;
            foreach ($step->transitions as $transition)
            {
                $toClient = $transition->to_step_id
                    ? ($clientByStepId[$transition->to_step_id] ?? null)
                    : null;

                $edges[] = [
                    'from_client_id' => $fromClient,
                    'from_output' => $transition->drawflow_output ?: 'output_1',
                    'to_client_id' => $toClient,
                    'to_automation_id' => $transition->to_automation_id,
                    'reply_type' => $transition->reply_type->value,
                    'match_value' => $transition->match_value,
                    'label' => $transition->label,
                ];
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * @param  array<string, mixed>  $edge
     * @return array{reply_type?: string, match_value?: string|null, label?: string|null, to_automation_id?: int|null}
     */
    protected function findOutputMeta(AutomationStep $fromStep, string $fromOutput, array $edge): array
    {
        $outputs = data_get($fromStep->settings, 'outputs', []);
        if (is_array($outputs))
        {
            foreach ($outputs as $output)
            {
                if (is_array($output) && (string) ($output['id'] ?? '') === $fromOutput)
                {
                    return $output;
                }
            }
        }

        return [
            'reply_type' => $edge['reply_type'] ?? AutomationReplyType::Fallback->value,
            'match_value' => $edge['match_value'] ?? null,
            'label' => $edge['label'] ?? null,
            'to_automation_id' => $edge['to_automation_id'] ?? null,
        ];
    }

    protected function resolveExitAutomationId(Automation $funnel, mixed $rawId): ?int
    {
        if ($rawId === null || $rawId === '' || $rawId === false)
        {
            return null;
        }

        $id = (int) $rawId;
        if ($id <= 0 || $id === (int) $funnel->id)
        {
            return null;
        }

        $exists = Automation::query()
            ->withoutGlobalScope('team')
            ->where('team_id', $funnel->team_id)
            ->actions()
            ->whereKey($id)
            ->exists();

        return $exists ? $id : null;
    }
}
