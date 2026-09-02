<?php

namespace App\Support;

use App\Enums\TeamBillingFrequency;
use App\Models\Team;

class TeamUsageInvoiceFrequency
{
    public const SETTING_KEY = 'usage_invoice_frequency';

    public static function for(Team $team): TeamBillingFrequency
    {
        return TeamBillingFrequency::tryFrom((string) $team->getSetting(self::SETTING_KEY, TeamBillingFrequency::Monthly->value))
            ?? TeamBillingFrequency::Monthly;
    }

    public static function set(Team $team, TeamBillingFrequency $frequency): void
    {
        $team->setSetting(self::SETTING_KEY, $frequency->value, [
            'type' => 'string',
            'group' => 'billing',
        ]);
        $team->unsetRelation('settings');
    }
}
