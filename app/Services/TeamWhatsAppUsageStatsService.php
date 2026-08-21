<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class TeamWhatsAppUsageStatsService
{
    /**
     * @return array{
     *     messages_sent: int,
     *     our_amount_cents: int,
     *     reference_amount_cents: int,
     *     saved_amount_cents: int,
     *     average_savings: float,
     *     our_rate: float,
     *     reference_rate: float,
     *     currency: string
     * }
     */
    public static function forTeam(Team $team, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $messages = self::outboundCount($team, $from, $to);
        $ourRate = max(0, (float) config('humano_pricing.whatsapp_message_billing.our_amount', 0.003));
        $referenceRate = max(0, (float) config('humano_pricing.whatsapp_message_billing.reference_amount', 0.005));
        $currency = strtoupper((string) config('humano_pricing.whatsapp_message_billing.currency', 'EUR'));

        $ourCents = (int) round($messages * $ourRate * 100);
        $referenceCents = (int) round($messages * $referenceRate * 100);
        $savedCents = max(0, $referenceCents - $ourCents);
        $averageSavings = $referenceCents > 0
            ? round(min(100, ($savedCents / $referenceCents) * 100), 2)
            : 0.0;

        return [
            'messages_sent' => $messages,
            'our_amount_cents' => $ourCents,
            'reference_amount_cents' => $referenceCents,
            'saved_amount_cents' => $savedCents,
            'average_savings' => $averageSavings,
            'our_rate' => $ourRate,
            'reference_rate' => $referenceRate,
            'currency' => $currency,
        ];
    }

    private static function outboundCount(Team $team, ?CarbonInterface $from, ?CarbonInterface $to): int
    {
        $teamNumber = preg_replace('/[^0-9]/', '', (string) $team->getWhatsAppFrom());
        if ($teamNumber === '')
        {
            return 0;
        }

        $query = Conversation::query()
            ->where('channel', 'whatsapp')
            ->where('direction', 'outbound')
            ->whereNotIn('status', ['failed', 'undelivered'])
            ->where(function (Builder $q) use ($teamNumber): void
            {
                $q->where('from', $teamNumber)
                    ->orWhere('from', 'like', $teamNumber.':%');
            });

        if ($from)
        {
            $query->where('created_at', '>=', $from);
        }

        if ($to)
        {
            $query->where('created_at', '<=', $to);
        }

        return $query->count();
    }
}
