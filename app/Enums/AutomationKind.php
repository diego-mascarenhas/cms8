<?php

namespace App\Enums;

enum AutomationKind: string
{
    case Funnel = 'funnel';
    case Action = 'action';

    public function label(): string
    {
        return match ($this)
        {
            self::Funnel => __('Embudo'),
            self::Action => __('Automatización'),
        };
    }

    public function listRouteName(): string
    {
        return match ($this)
        {
            self::Funnel => 'funnel-list',
            self::Action => 'automation-list',
        };
    }

    public function createRouteName(): string
    {
        return match ($this)
        {
            self::Funnel => 'funnel.create',
            self::Action => 'automation.create',
        };
    }

    public function showRouteName(): string
    {
        return match ($this)
        {
            self::Funnel => 'funnel.show',
            self::Action => 'automation.show',
        };
    }

    public function editRouteName(): string
    {
        return match ($this)
        {
            self::Funnel => 'funnel.edit',
            self::Action => 'automation.edit',
        };
    }

    public function updateRouteName(): string
    {
        return match ($this)
        {
            self::Funnel => 'funnel.update',
            self::Action => 'automation.update',
        };
    }

    public function destroyRouteName(): string
    {
        return match ($this)
        {
            self::Funnel => 'funnel.destroy',
            self::Action => 'automation.destroy',
        };
    }
}
