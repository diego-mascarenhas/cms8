<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Team;

class TeamModulesByPricingPlanSyncer
{
    /**
     * Enable/disable team modules from Humano pricing plan slug (assistant, business, foundation).
     */
    public function syncForHumanoPricingPlan(Team $team, string $planSlug): void
    {
        $planSlug = strtolower(trim($planSlug));
        if ($planSlug === '')
        {
            return;
        }

        if (! in_array($planSlug, ['assistant', 'business', 'foundation'], true))
        {
            return;
        }

        $enabledKeys = config("humano_pricing.plan_team_modules.{$planSlug}", []);

        if ($enabledKeys === [])
        {
            return;
        }

        $enabledLookup = array_fill_keys($enabledKeys, true);

        foreach (Module::query()->cursor() as $module)
        {
            $key = $module->key;
            if ($key === null || $key === '')
            {
                continue;
            }

            if (isset($enabledLookup[$key]))
            {
                $team->enableModule($key);

                continue;
            }

            if ($team->modules()->where('modules.id', $module->id)->exists())
            {
                $team->disableModule($key);
            }
        }
    }
}
