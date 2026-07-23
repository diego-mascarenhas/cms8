<?php

namespace App\Support;

use App\Models\Module;
use App\Models\Team;

class TeamModuleAccess
{
    /**
     * Abort with a clear message when the current team does not have the module active.
     */
    public static function abortUnless(string $moduleKey, ?Team $team = null): void
    {
        $team ??= auth()->user()?->currentTeam;

        if ($team !== null && $team->hasModule($moduleKey))
        {
            return;
        }

        abort(403, self::messageForMissingModules([$moduleKey], $team));
    }

    /**
     * Abort unless the team has at least one of the given modules.
     *
     * @param  list<string>  $moduleKeys
     */
    public static function abortUnlessAny(array $moduleKeys, ?Team $team = null): void
    {
        $team ??= auth()->user()?->currentTeam;

        if ($team !== null)
        {
            foreach ($moduleKeys as $moduleKey)
            {
                if ($team->hasModule($moduleKey))
                {
                    return;
                }
            }
        }

        abort(403, self::messageForMissingModules($moduleKeys, $team));
    }

    /**
     * @param  list<string>  $moduleKeys
     */
    public static function messageForMissingModules(array $moduleKeys, ?Team $team = null): string
    {
        $labels = collect($moduleKeys)
            ->map(fn (string $key) => self::moduleLabel($key))
            ->unique()
            ->values();

        $moduleLabel = $labels->count() === 1
            ? $labels->first()
            : $labels->join(', ', ' '.__('o').' ');

        $teamName = $team?->name;

        if ($teamName)
        {
            return __('Tu equipo actual (:team) no tiene el módulo «:module» activo. Pedile al administrador que lo habilite o cambiá de equipo.', [
                'team' => $teamName,
                'module' => $moduleLabel,
            ]);
        }

        return __('No tenés un equipo con el módulo «:module» activo. Pedile al administrador que lo habilite o cambiá de equipo.', [
            'module' => $moduleLabel,
        ]);
    }

    public static function moduleLabel(string $moduleKey): string
    {
        $module = Module::query()->where('key', $moduleKey)->first();

        return $module?->name ?: $moduleKey;
    }
}
