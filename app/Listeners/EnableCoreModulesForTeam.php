<?php

namespace App\Listeners;

use App\Models\Module;
use App\Models\Team;
use App\Services\DefaultAssistantFlowPromptsService;
use Database\Seeders\PostTypeSeeder;
use Laravel\Jetstream\Events\TeamCreated;

class EnableCoreModulesForTeam
{
    /**
     * Handle the event.
     */
    public function handle(TeamCreated $event): void
    {
        $team = $event->team instanceof Team ? $event->team : Team::find($event->team->id);

        if (! $team)
        {
            return;
        }

        $defaults = config('team-modules.defaults', []);
        $coreModuleKeys = Module::where('is_core', true)->pluck('key');

        foreach ($coreModuleKeys as $moduleKey)
        {
            if (! ($defaults[$moduleKey] ?? true))
            {
                continue;
            }
            $team->enableModule($moduleKey);
        }

        $addonModuleKeys = Module::where('is_core', false)->pluck('key');

        foreach ($addonModuleKeys as $moduleKey)
        {
            if (! ($defaults[$moduleKey] ?? false))
            {
                continue;
            }
            $team->enableModule($moduleKey);
        }

        DefaultAssistantFlowPromptsService::syncForTeam((int) $team->id);

        PostTypeSeeder::seedForTeam($team);
    }
}
