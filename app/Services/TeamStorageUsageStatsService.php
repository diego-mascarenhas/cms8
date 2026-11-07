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
        $ourRate = TeamBillingRate::amountOn((int) $team->id, TeamBillingProduct::StorageMegabyte, $at);
        $currency = strtoupper((string) config('humano_pricing.storage_billing.currency', 'USD'));
        $megabytes = $bytes / (1024 ** 2);

        return [
            'bytes' => $bytes,
            'our_amount_cents' => (int) round($megabytes * $ourRate * 100),
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
     * @return list<array{label: string, bytes: int, formatted_size: string, amount_cents: int}>
     */
    public static function sourceLines(Team $team, ?CarbonInterface $asOf = null): array
    {
        $rows = [];

        foreach (self::labeledSources((int) $team->id) as $row)
        {
            if ($row['bytes'] < 1)
            {
                continue;
            }

            $rows[] = $row;
        }

        usort($rows, fn (array $left, array $right): int => $right['bytes'] <=> $left['bytes']);
        $totalBytes = array_sum(array_column($rows, 'bytes'));
        $billed = self::forTeam($team, $asOf);
        $cents = self::allocateCents(array_column($rows, 'bytes'), (int) $billed['our_amount_cents']);

        return array_map(function (array $row, int $amount) use ($totalBytes): array
        {
            return [
                'label' => $row['label'],
                'bytes' => $row['bytes'],
                'formatted_size' => self::formatBytes($row['bytes']),
                'amount_cents' => $totalBytes > 0 ? $amount : 0,
            ];
        }, $rows, $cents);
    }

    /**
     * @return list<array{label: string, bytes: int}>
     */
    private static function labeledSources(int $teamId): array
    {
        $rows = [];

        foreach (self::directSources() as $modelClass => [$table, $softDeletes, $label])
        {
            $rows[] = [
                'label' => $label,
                'bytes' => self::sumMedia($modelClass, function ($query) use ($table, $teamId, $softDeletes): void
                {
                    $query->select('id')->from($table)->where('team_id', $teamId);
                    if ($softDeletes)
                    {
                        $query->whereNull('deleted_at');
                    }
                }),
            ];
        }

        $rows[] = [
            'label' => 'Respuestas',
            'bytes' => self::sumMedia(TicketResponse::class, function ($query) use ($teamId): void
            {
                $query->select('ticket_responses.id')
                    ->from('ticket_responses')
                    ->join('tickets', 'tickets.id', '=', 'ticket_responses.ticket_id')
                    ->where('tickets.team_id', $teamId);
            }),
        ];

        return $rows;
    }

    /**
     * @param  list<int|float>  $weights
     * @return list<int>
     */
    private static function allocateCents(array $weights, int $totalCents): array
    {
        $sum = array_sum($weights);
        $cents = array_fill(0, count($weights), 0);
        if ($sum <= 0 || $totalCents <= 0)
        {
            return $cents;
        }

        $fractions = [];
        $assigned = 0;
        foreach (array_values($weights) as $index => $weight)
        {
            $exact = $totalCents * ($weight / $sum);
            $cents[$index] = (int) floor($exact);
            $fractions[$index] = $exact - $cents[$index];
            $assigned += $cents[$index];
        }

        arsort($fractions);
        foreach (array_keys($fractions) as $index)
        {
            if ($assigned >= $totalCents)
            {
                break;
            }

            $cents[$index]++;
            $assigned++;
        }

        return $cents;
    }

    /**
     * @return array<class-string, array{0: string, 1: bool, 2: string}>
     */
    private static function directSources(): array
    {
        return [
            Contact::class => ['contacts', true, 'Contactos'],
            Task::class => ['tasks', true, 'Tareas'],
            Communication::class => ['communications', false, 'Comunicaciones'],
            Ticket::class => ['tickets', false, 'Tickets'],
            TeamFile::class => ['team_files', true, 'Archivos'],
            Multimedia::class => ['multimedia', true, 'Multimedia'],
            Product::class => ['products', false, 'Productos'],
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
