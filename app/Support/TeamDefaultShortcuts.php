<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;

class TeamDefaultShortcuts
{
    /**
     * @return array<string, array{title: string, subtitle: string, icon: string, route: string, module: string}>
     */
    public static function definitions(): array
    {
        return [
            'calendar' => [
                'title' => 'Calendario',
                'subtitle' => __('app.shortcuts.appointments'),
                'icon' => 'ti ti-calendar',
                'route' => 'app-calendar',
                'module' => 'calendar',
            ],
            'prospecting' => [
                'title' => 'Buscar clientes',
                'subtitle' => __('Prospección'),
                'icon' => 'ti ti-target',
                'route' => 'prospect.search',
                'module' => 'prospecting',
            ],
            'team_files' => [
                'title' => 'Team Files',
                'subtitle' => __('app.shortcuts.team_files'),
                'icon' => 'ti ti-folders',
                'route' => 'team-file.index',
                'module' => 'team_files',
            ],
            'times' => [
                'title' => 'Times',
                'subtitle' => __('app.shortcuts.times'),
                'icon' => 'ti ti-hourglass',
                'route' => 'time.index',
                'module' => 'times',
            ],
            'passwords' => [
                'title' => 'Contraseñas',
                'subtitle' => 'Cofre',
                'icon' => 'ti ti-lock',
                'route' => 'passwords.index',
                'module' => 'passwords',
            ],
            'performance_insights' => [
                'title' => __('app.performance_insights_menu'),
                'subtitle' => __('app.shortcuts.performance_insights'),
                'icon' => 'ti ti-chart-bar',
                'route' => 'performance-insights.index',
                'module' => 'performance_insights',
            ],
        ];
    }

    /**
     * Default shortcuts enabled when first injected for a team (module must be active).
     *
     * @return list<string>
     */
    public static function enabledByDefaultKeys(): array
    {
        return ['performance_insights'];
    }

    public static function isEnabledByDefault(string $key, Team $team): bool
    {
        if (! in_array($key, self::enabledByDefaultKeys(), true))
        {
            return false;
        }

        $definition = self::definitions()[$key] ?? null;
        if (! $definition)
        {
            return false;
        }

        return $team->hasModule($definition['module']);
    }

    public static function userCanSeeShortcut(string $key, ?User $user): bool
    {
        if ($key !== 'performance_insights')
        {
            return true;
        }

        return $user?->hasAnyRole(['admin', 'root']) ?? false;
    }

    /**
     * Navbar / notification defaults when the performance insights module is enabled.
     */
    public static function applyPerformanceInsightsTeamDefaults(Team $team): void
    {
        if (! $team->hasModule('performance_insights'))
        {
            return;
        }

        if (! filter_var($team->getSetting('shortcuts_icon_visible', false), FILTER_VALIDATE_BOOLEAN))
        {
            $team->setSetting('shortcuts_icon_visible', true, [
                'group' => 'shortcuts',
                'type' => 'boolean',
                'is_encrypted' => false,
            ]);
        }

        if ($team->settings()->where('key', 'performance_insights_in_app_notification')->doesntExist())
        {
            $team->setSetting('performance_insights_in_app_notification', true, [
                'group' => 'notifications',
                'type' => 'boolean',
                'is_encrypted' => false,
            ]);
        }

        $shortcuts = $team->getSetting('team_shortcuts', []) ?? [];
        $hasPerformanceShortcut = false;

        foreach ($shortcuts as $shortcut)
        {
            if (($shortcut['type'] ?? '') === 'default' && ($shortcut['key'] ?? '') === 'performance_insights')
            {
                $hasPerformanceShortcut = true;

                break;
            }
        }

        if (! $hasPerformanceShortcut)
        {
            $shortcuts[] = [
                'type' => 'default',
                'key' => 'performance_insights',
                'enabled' => true,
            ];

            $team->setSetting('team_shortcuts', array_values($shortcuts), [
                'type' => 'json',
                'group' => 'shortcuts',
                'is_encrypted' => false,
            ]);
        }
    }
}
