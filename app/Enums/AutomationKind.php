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
}
