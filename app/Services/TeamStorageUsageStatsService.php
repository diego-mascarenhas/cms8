<?php

namespace App\Services;

use App\Enums\TeamBillingProduct;
use App\Helpers\Helpers;
use App\Models\Communication;
use App\Models\Contact;
use App\Models\Multimedia;
use App\Models\Product;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamBillingRate;
use App\Models\TeamFile;
use App\Models\Ticket;
use App\Models\TicketResponse;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class TeamStorageUsageStatsService
{
    /**
     * Occupied space is a snapshot (files still stored), billed per GiB at the rate in effect.
     *
     * @return array{
     *     bytes: int,
     *     our_amount_cents: int,
     *     our_rate: float,
     *     currency: string,
     *     formatted_size: string
     * }
     */
    public static function forTeam(Team $team, ?CarbonInterface $asOf = null, bool $bill = true): array
    {
        $bytes = $bill ? self::bytesForTeam((int) $team->id) : 0;
        $at = $asOf ?? now();
        $ourRate = TeamBillingRate::amountOn((int) $team->id, TeamBillingProduct::StorageGigabyte, $at);
        $currency = strtoupper((string) config('humano_pricing.storage_billing.currency', 'EUR'));
        $gigabytes = $bytes / (1024 ** 3);

        return [
            'bytes' => $bytes,
            'our_amount_cents' => (int) round($gigabytes * $ourRate * 100),
            'our_rate' => $ourRate,
            'currency' => $currency,
            'formatted_size' => self::formatBytes($bytes),
        ];
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024)
        {
            return $bytes.' B';
        }

        if ($bytes < 1024 ** 2)
        {
            return Helpers::formatDecimal($bytes / 1024, 1).' KB';
        }

        if ($bytes < 1024 ** 3)
        {
            return Helpers::formatDecimal($bytes / (1024 ** 2), 1).' MB';
        }

        return Helpers::formatDecimal($bytes / (1024 ** 3), 2).' GB';
    }

    public static function bytesForTeam(int $teamId): int
    {
        $bytes = 0;

        foreach (self::directSources() as $modelClass => [$table, $softDeletes])
        {
            $bytes += self::sumMedia($modelClass, function ($query) use ($table, $teamId, $softDeletes): void
            {
                $query->select('id')->from($table)->where('team_id', $teamId);
                if ($softDeletes)
                {
                    $query->whereNull('deleted_at');
                }
            });
        }

        $bytes += self::sumMedia(TicketResponse::class, function ($query) use ($teamId): void
        {
            $query->select('ticket_responses.id')
                ->from('ticket_responses')
                ->join('tickets', 'tickets.id', '=', 'ticket_responses.ticket_id')
                ->where('tickets.team_id', $teamId);
        });

        return $bytes;
    }

    /**
     * @return array<class-string, array{0: string, 1: bool}>
     */
    private static function directSources(): array
    {
        return [
            Contact::class => ['contacts', true],
            Task::class => ['tasks', true],
            Communication::class => ['communications', false],
            Ticket::class => ['tickets', false],
            TeamFile::class => ['team_files', true],
            Multimedia::class => ['multimedia', true],
            Product::class => ['products', false],
        ];
    }

    /**
     * @param  class-string  $modelClass
     */
    private static function sumMedia(string $modelClass, callable $modelIds): int
    {
        return (int) DB::table('media')
            ->where('model_type', $modelClass)
            ->whereIn('model_id', $modelIds)
            ->sum('size');
    }
}
