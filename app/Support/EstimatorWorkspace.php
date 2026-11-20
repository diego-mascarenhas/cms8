<?php

namespace App\Support;

use App\Models\Team;

class EstimatorWorkspace
{
    public const SETTING = 'estimator_workspace_mode';

    public const BUDGET = 'budget';

    public const ORDER = 'order';

    public static function mode(Team $team): string
    {
        $value = (string) $team->getSetting(self::SETTING, self::BUDGET);

        return $value === self::ORDER ? self::ORDER : self::BUDGET;
    }
}
