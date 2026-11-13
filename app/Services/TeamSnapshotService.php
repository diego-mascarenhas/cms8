<?php

namespace App\Services;

use App\Models\TeamSnapshot;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class TeamSnapshotService
{
    /**
     * Persist a reusable team snapshot (finance, mailer, etc.).
     *
     * @param  array<string, mixed>  $payload
     */
    public function store(
        int $teamId,
        string $type,
        array $payload,
        ?string $key = null,
        ?CarbonInterface $capturedAt = null,
    ): TeamSnapshot {
        return TeamSnapshot::query()->create([
            'team_id' => $teamId,
            'type' => $type,
            'key' => $key !== null && $key !== '' ? $key : null,
            'payload' => $payload,
            'captured_at' => $capturedAt ?? now(),
        ]);
    }

    public function latest(int $teamId, string $type, ?string $key = null): ?TeamSnapshot
    {
        return TeamSnapshot::query()
            ->forTeam($teamId)
            ->ofType($type)
            ->withKey($key)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return Collection<int, TeamSnapshot>
     */
    public function history(int $teamId, string $type, ?string $key = null, int $limit = 24): Collection
    {
        return TeamSnapshot::query()
            ->forTeam($teamId)
            ->ofType($type)
            ->withKey($key)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * Drop older rows beyond the retention window (keeps the newest `$keep` per team/type/key).
     */
    public function prune(int $teamId, string $type, ?string $key = null, int $keep = 48): int
    {
        $ids = TeamSnapshot::query()
            ->forTeam($teamId)
            ->ofType($type)
            ->withKey($key)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->skip(max(0, $keep))
            ->take(500)
            ->pluck('id');

        if ($ids->isEmpty())
        {
            return 0;
        }

        return (int) TeamSnapshot::query()->whereIn('id', $ids)->delete();
    }
}
