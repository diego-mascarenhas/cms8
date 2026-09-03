<?php

namespace App\Support;

use App\Enums\TeamBillingProduct;
use App\Models\Team;
use App\Models\TeamBillingRate;
use DateTimeInterface;

class MailerPaygPricing
{
    public static function pricePerEmail(Team|int|null $team = null, DateTimeInterface|string|null $on = null): string
    {
        $teamId = $team instanceof Team ? (int) $team->id : $team;
        if ($teamId === null && $on === null)
        {
            return TeamBillingRate::formatAmount((float) (config('emailer.payg.price_per_email', 0.002) ?: 0));
        }

        return TeamBillingRate::formattedAmountOn($teamId, TeamBillingProduct::MailerSend, $on);
    }

    public static function currency(): string
    {
        return strtoupper(trim((string) config('emailer.payg.currency', 'EUR')) ?: 'EUR');
    }

    public static function amountToCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function overageDueCents(int $overageEmails, Team|int|null $team = null, DateTimeInterface|string|null $on = null): int
    {
        if ($overageEmails < 1)
        {
            return 0;
        }

        return (int) round($overageEmails * (float) self::pricePerEmail($team, $on) * 100);
    }
}
