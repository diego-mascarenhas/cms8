<?php

namespace App\Listeners;

use App\Models\Module;
use App\Models\Team;
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

        $coreModuleKeys = Module::where('is_core', true)->pluck('key');

        foreach ($coreModuleKeys as $moduleKey)
        {
            $team->enableModule($moduleKey);
        }
    }
}
