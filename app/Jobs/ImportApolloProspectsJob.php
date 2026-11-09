<?php

namespace App\Jobs;

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
        $result = $apollo->searchPeople($this->filters, $this->page, $perPage);
        $people = is_array($result['people'] ?? null) ? $result['people'] : [];
        $total = (int) ($result['total_entries'] ?? 0);

        if ($people === [])
        {
            $this->releaseLock();

            return;
        }

        $search = $directory->recordSearch($team, $this->userId, $this->filters, $this->page, $perPage, $people);
        $queued = 0;

        foreach ($people as $person)
        {
            if (! is_array($person))
            {
                continue;
            }

            $apolloId = trim((string) ($person['id'] ?? ''));
            if ($apolloId === '')
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
            );
            $queued++;
        }

        Log::info('Apollo prospect page queued', [
            'team_id' => $team->id,
            'page' => $this->page,
            'total' => $total,
            'queued' => $queued,
            'category_id' => $this->categoryId,
        ]);

        if ($this->page * $perPage < $total)
        {
            self::dispatch(
                $this->teamId,
                $this->userId,
                $this->filters,
                $this->page + 1,
                $this->categoryId,
                $this->lockKey,
            );

            return;
        }

        $this->releaseLock();
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Apollo prospect import stopped', [
            'team_id' => $this->teamId,
            'page' => $this->page,
            'error' => $exception->getMessage(),
        ]);
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
