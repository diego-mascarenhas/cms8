<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Support\CollectionMessagingGuide;
use App\Support\DatabaseSequence;
use Database\Seeders\PromptSeeder;
use InvalidArgumentException;

/**
 * System-wide assistant defaults. Teams keep their own copy in module_prompts
 * only after they pick one (or restore that default).
 */
class AssistantPromptCatalog
{
    /**
     * @return list<array{key: string, group: string, group_label: string, own_brand: bool, module_key: string, section_key: string, section_label: string, helper_text: string, prompt_instruction: string}>
     */
    public function items(): array
    {
        $items = [];

        foreach (DefaultAssistantFlowPromptsService::definitions() as $definition)
        {
            $group = $this->groupForSection($definition['section_key']);
            if ($group === null)
            {
                continue;
            }

            $items[] = $this->item(
                $definition['module_key'].':'.$definition['section_key'],
                $group,
                $definition['module_key'],
                $definition['section_key'],
                $definition['section_label'],
                (string) ($definition['helper_text'] ?? ''),
                $definition['prompt_instruction'],
                false,
            );
        }

        $items[] = $this->item(
            'invoices:collections',
            'cobranzas',
            'invoices',
            'collections',
            'Cobranzas',
            'Cobranzas: buscar el contacto y usar las facturas (invoices) reales.',
            CollectionMessagingGuide::collectionsAssistantInstruction(),
            false,
        );

        foreach ((new PromptSeeder)->getPromptDefinitions() as $definition)
        {
            if (! ($definition['own_brand'] ?? false))
            {
                continue;
            }

            $module = Module::query()->find($definition['module_id']);
            if (! $module)
            {
                continue;
            }

            $section = (string) $definition['section_key'];
            $items[] = $this->item(
                $module->key.':'.$section,
                'marca',
                $module->key,
                $section,
                (string) $definition['section_label'],
                (string) ($definition['helper_text'] ?? ''),
                (string) $definition['prompt_instruction'],
                true,
            );
        }

        return $items;
    }

    /**
     * @return list<array{group: string, group_label: string, items: list<array{key: string, section_key: string, label: string, helper: string, section_label: string, prompt_instruction: string, own_brand: bool, owned: bool, drifted: bool}>}>
     */
    public function groupsFor(Team $team): array
    {
        $grouped = [];

        foreach ($this->items() as $item)
        {
            if ($item['own_brand'] && ! $this->teamCanSeeOwnBrand($team, $item))
            {
                continue;
            }

            $owned = $this->teamPromptBySection($team, $item['section_key'])
                ?? $this->teamPrompt($team, $item['module_key'], $item['section_key']);
            $grouped[$item['group']]['group'] = $item['group'];
            $grouped[$item['group']]['group_label'] = $item['group_label'];
            $grouped[$item['group']]['items'][] = [
                'key' => $item['key'],
                'section_key' => $item['section_key'],
                'label' => $item['section_label'],
                'helper' => $item['helper_text'],
                'section_label' => $item['section_label'],
                'prompt_instruction' => $item['prompt_instruction'],
                'own_brand' => $item['own_brand'],
                'owned' => $owned !== null,
                'drifted' => $owned !== null && trim((string) $owned->prompt_instruction) !== trim($item['prompt_instruction']),
            ];
        }

        $order = ['agenda', 'ventas', 'cobranzas', 'equipo', 'marca'];
        uksort($grouped, function (string $left, string $right) use ($order): int
        {
            $leftRank = array_search($left, $order, true);
            $rightRank = array_search($right, $order, true);

            return ($leftRank === false ? 99 : $leftRank) <=> ($rightRank === false ? 99 : $rightRank);
        });

        return array_values($grouped);
    }

    /**
     * Copy one system default into the team and return the routing key.
     */
    public function apply(Team $team, string $routingKey): string
    {
        $item = $this->catalogItemOrFail($team, $routingKey);
        $existing = $this->teamPromptBySection($team, $item['section_key']);
        if ($existing)
        {
            $existing->section_label = $item['section_label'];
            $existing->prompt_instruction = $item['prompt_instruction'];
            $existing->helper_text = $item['helper_text'];
            $existing->is_active = true;
            $existing->save();

            return $this->routingKeyForPrompt($existing, $item['module_key']);
        }

        return $this->copyItemToTeam($team, $item);
    }

    /**
     * Resolve a catalog or team prompt for pinning. Copies the default when the team has no row.
     * Does not overwrite a team customization.
     */
    public function ensureOnTeam(Team $team, string $routingKey): string
    {
        $direct = Prompt::findByRoutingKey(trim($routingKey), (int) $team->id);
        if ($direct && $direct->is_active)
        {
            return $this->routingKeyForPrompt($direct, $direct->module?->key ?? '');
        }

        $item = $this->catalogItemOrFail($team, $routingKey);
        $existing = $this->teamPromptBySection($team, $item['section_key'])
            ?? $this->teamPrompt($team, $item['module_key'], $item['section_key']);
        if ($existing)
        {
            if (! $existing->is_active)
            {
                $existing->is_active = true;
                $existing->save();
            }

            return $this->routingKeyForPrompt($existing, $item['module_key']);
        }

        return $this->copyItemToTeam($team, $item);
    }

    /**
     * @return array{key: string, group: string, group_label: string, own_brand: bool, module_key: string, section_key: string, section_label: string, helper_text: string, prompt_instruction: string}
     */
    private function catalogItemOrFail(Team $team, string $routingKey): array
    {
        $item = $this->find($routingKey) ?? $this->findBySectionKey($this->sectionKeyFrom($routingKey));
        if ($item === null)
        {
            throw new InvalidArgumentException(__('team_settings.site_assistant.invalid_prompt'));
        }

        if ($item['own_brand'] && ! $this->teamCanSeeOwnBrand($team, $item))
        {
            throw new InvalidArgumentException(__('team_settings.site_assistant.invalid_prompt'));
        }

        return $item;
    }

    /**
     * @param  array{key: string, group: string, group_label: string, own_brand: bool, module_key: string, section_key: string, section_label: string, helper_text: string, prompt_instruction: string}  $item
     */
    private function copyItemToTeam(Team $team, array $item): string
    {
        $module = Module::query()->where('key', $item['module_key'])->first();
        if (! $module)
        {
            throw new InvalidArgumentException(__('team_settings.site_assistant.missing_module'));
        }

        DatabaseSequence::sync('module_prompts');

        $prompt = null;
        DatabaseSequence::retryOnDuplicateId('module_prompts', function () use ($team, $module, $item, &$prompt): void
        {
            $prompt = Prompt::withoutGlobalScope('team')->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'module_id' => $module->id,
                    'section_key' => $item['section_key'],
                ],
                [
                    'section_label' => $item['section_label'],
                    'prompt_instruction' => $item['prompt_instruction'],
                    'helper_text' => $item['helper_text'],
                    'order' => 0,
                    'is_active' => true,
                ],
            );
        });

        return $item['key'];
    }

    private function routingKeyForPrompt(Prompt $prompt, string $fallbackModuleKey = ''): string
    {
        $prompt->loadMissing('module');

        return ($prompt->module?->key ?: $fallbackModuleKey).':'.$prompt->section_key;
    }

    /**
     * @return array{key: string, group: string, group_label: string, own_brand: bool, module_key: string, section_key: string, section_label: string, helper_text: string, prompt_instruction: string}|null
     */
    public function find(string $routingKey): ?array
    {
        $key = trim($routingKey);
        foreach ($this->items() as $item)
        {
            if ($item['key'] === $key)
            {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  array{key: string, group: string, group_label: string, own_brand: bool, module_key: string, section_key: string, section_label: string, helper_text: string, prompt_instruction: string}  $item
     * @return array{key: string, group: string, group_label: string, own_brand: bool, module_key: string, section_key: string, section_label: string, helper_text: string, prompt_instruction: string}
     */
    private function item(
        string $key,
        string $group,
        string $moduleKey,
        string $sectionKey,
        string $label,
        string $helper,
        string $instruction,
        bool $ownBrand,
    ): array {
        return [
            'key' => $key,
            'group' => $group,
            'group_label' => $this->groupLabel($group),
            'own_brand' => $ownBrand,
            'module_key' => $moduleKey,
            'section_key' => $sectionKey,
            'section_label' => $label,
            'helper_text' => $helper,
            'prompt_instruction' => $instruction,
        ];
    }

    private function groupForSection(string $sectionKey): ?string
    {
        return match ($sectionKey)
        {
            'assistant_citas' => 'agenda',
            'assistant_embudo', 'assistant_catalogo' => 'ventas',
            'assistant_contactos', 'assistant_tareas', 'assistant_campanas' => 'equipo',
            default => null,
        };
    }

    private function groupLabel(string $group): string
    {
        return match ($group)
        {
            'agenda' => 'Agenda',
            'ventas' => 'Ventas',
            'cobranzas' => 'Cobranzas',
            'equipo' => 'Equipo',
            'marca' => 'Nuestros productos',
            default => $group,
        };
    }

    /**
     * @param  array{section_key: string}  $item
     */
    private function teamCanSeeOwnBrand(Team $team, array $item): bool
    {
        if (in_array((int) $team->id, config('humano_pricing.plan_access_team_ids', []), true))
        {
            return true;
        }

        return $this->teamPrompt($team, $item['module_key'] ?? '', $item['section_key']) !== null;
    }

    private function findBySectionKey(string $sectionKey): ?array
    {
        $key = trim($sectionKey);
        if ($key === '')
        {
            return null;
        }

        foreach ($this->items() as $item)
        {
            if ($item['section_key'] === $key)
            {
                return $item;
            }
        }

        return null;
    }

    private function sectionKeyFrom(string $routingKey): string
    {
        $parts = explode(':', trim($routingKey), 2);

        return $parts[1] ?? $parts[0];
    }

    private function teamPrompt(Team $team, string $moduleKey, string $sectionKey): ?Prompt
    {
        $module = Module::query()->where('key', $moduleKey)->first();
        if (! $module)
        {
            return null;
        }

        return Prompt::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('module_id', $module->id)
            ->where('section_key', $sectionKey)
            ->first();
    }

    private function teamPromptBySection(Team $team, string $sectionKey): ?Prompt
    {
        return Prompt::withoutGlobalScope('team')
            ->with('module')
            ->where('team_id', $team->id)
            ->where('section_key', $sectionKey)
            ->first();
    }
}
