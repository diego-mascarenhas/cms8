<?php

namespace App\Jobs;

use App\Models\ProspectImport;
use App\Models\Team;
use App\Models\User;
use App\Services\ApolloService;
use App\Services\Billing\AssistantSubscriptionService;
use App\Services\Prospecting\ProspectDirectory;
use App\Support\HumanoPricingCatalog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportApolloProspectsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public int $teamId,
        public int $userId,
        public array $filters,
        public int $page = 1,
        public ?int $categoryId = null,
        public ?string $lockKey = null,
        public ?int $importId = null,
    ) {
        $this->onQueue('prospects');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function lockKey(int $teamId, array $filters, ?int $categoryId = null): string
    {
        ksort($filters);

        return 'prospect-import:'.$teamId.':'.($categoryId ?? 0).':'.md5((string) json_encode($filters));
    }

    public function handle(
        ApolloService $apollo,
        ProspectDirectory $directory,
        AssistantSubscriptionService $access,
    ): void {
        $team = Team::query()->find($this->teamId);
        $user = User::query()->find($this->userId);
        if ($team === null || $user === null)
        {
            $this->releaseLock();

            return;
        }

        $catalog = $access->accessForCatalog($team, HumanoPricingCatalog::MAILER);
        if (($catalog['status'] ?? null) !== 'paid')
        {
            $this->releaseLock();

            return;
        }

        if ($this->page > 5000)
        {
            $this->releaseLock();

            return;
        }

        $perPage = 25;
        $apolloFilters = $this->filters;
        unset($apolloFilters['channels']);
        $result = $apollo->searchPeople($apolloFilters, $this->page, $perPage);
        $people = is_array($result['people'] ?? null) ? $result['people'] : [];
        $total = (int) ($result['total_entries'] ?? 0);

        if ($people === [])
        {
            ProspectImport::query()->find($this->importId)?->noteQueued(0, $this->reportedTotal(0, $total), true);
            $this->releaseLock();

            return;
        }

        $search = $directory->recordSearch($team, $this->userId, $apolloFilters, $this->page, $perPage, $people);
        $queued = 0;

        foreach ($people as $person)
        {
            if (! is_array($person))
            {
                continue;
            }

            $apolloId = trim((string) ($person['id'] ?? ''));
            if ($apolloId === '' || ! self::personMatchesChannels($person, $this->filters))
            {
                continue;
            }

            ImportApolloProspectContactJob::dispatch(
                (int) $team->id,
                $this->userId,
                $apolloId,
                $person,
                $this->categoryId,
                $search->id,
                $this->importId,
            );
            $queued++;
        }

        Log::info('Apollo prospect page queued', [
            'team_id' => $team->id,
            'page' => $this->page,
            'total' => $total,
            'queued' => $queued,
            'category_id' => $this->categoryId,
            'import_id' => $this->importId,
        ]);

        ProspectImport::query()->find($this->importId)?->noteQueued(
            $queued,
            $this->reportedTotal($queued, $total),
            $this->page * $perPage >= $total,
        );

        if ($this->page * $perPage < $total)
        {
            self::dispatch(
                $this->teamId,
                $this->userId,
                $this->filters,
                $this->page + 1,
                $this->categoryId,
                $this->lockKey,
                $this->importId,
            );

            return;
        }

        $this->releaseLock();
    }

    /**
     * @param  array<string, mixed>  $person
     * @param  array<string, mixed>  $filters
     */
    public static function personMatchesChannels(array $person, array $filters): bool
    {
        $channels = $filters['channels'] ?? null;
        if (! is_array($channels) || $channels === [])
        {
            return true;
        }

        $hasEmail = (bool) ($person['has_email'] ?? false);
        $hasPhone = (bool) ($person['has_phone'] ?? false);
        $wantsEmail = in_array('email', $channels, true);
        $wantsPhone = in_array('phone', $channels, true);
        $wantsRest = in_array('rest', $channels, true);

        if ($wantsEmail && $wantsPhone && $wantsRest)
        {
            return true;
        }

        return ($wantsEmail && $hasEmail)
            || ($wantsPhone && $hasPhone)
            || ($wantsRest && ! $hasEmail && ! $hasPhone);
    }

    private function reportedTotal(int $queued, int $apolloTotal): int
    {
        if (! $this->limitsChannels())
        {
            return $apolloTotal;
        }

        $already = (int) (ProspectImport::query()->find($this->importId)?->queued ?? 0);

        return $already + $queued;
    }

    private function limitsChannels(): bool
    {
        $channels = $this->filters['channels'] ?? null;
        if (! is_array($channels) || $channels === [])
        {
            return false;
        }

        return count(array_intersect(['email', 'phone', 'rest'], $channels)) < 3;
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Apollo prospect import stopped', [
            'team_id' => $this->teamId,
            'page' => $this->page,
            'error' => $exception->getMessage(),
        ]);
        ProspectImport::query()->find($this->importId)?->markFailed($exception->getMessage());
        $this->releaseLock();
    }

    private function releaseLock(): void
    {
        if ($this->lockKey !== null)
        {
            Cache::forget($this->lockKey);
        }
    }
}
