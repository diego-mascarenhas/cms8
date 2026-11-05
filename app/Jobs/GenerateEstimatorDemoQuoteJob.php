<?php

namespace App\Jobs;

use App\Models\Team;
use App\Services\EstimatorProductQuoteService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GenerateEstimatorDemoQuoteJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public int $tries = 1;

    public function __construct(
        public string $pollToken,
        public int $teamId,
        public string $brief,
    ) {}

    public static function cacheKey(string $pollToken): string
    {
        return 'estimator-demo:'.$pollToken;
    }

    public function handle(EstimatorProductQuoteService $quotes): void
    {
        $key = self::cacheKey($this->pollToken);
        ignore_user_abort(true);
        set_time_limit($this->timeout);
        $finished = false;
        register_shutdown_function(function () use ($key, &$finished): void
        {
            if ($finished || app()->runningUnitTests())
            {
                return;
            }

            $state = Cache::get($key);
            if (! is_array($state) || ($state['status'] ?? '') !== 'processing')
            {
                return;
            }

            Cache::put($key, [
                'status' => 'failed',
                'message' => __('Could not generate the estimate. Please try again.'),
            ], now()->addHour());
        });
        $team = Team::query()->find($this->teamId);
        if (! $team)
        {
            Cache::put($key, [
                'status' => 'failed',
                'message' => __('Could not generate the estimate. Please try again.'),
            ], now()->addHour());

            return;
        }

        try
        {
            $preview = $quotes->quote($this->brief, $team);
        } catch (RuntimeException $e)
        {
            Log::error('GenerateEstimatorDemoQuoteJob AI failed', [
                'team_id' => $this->teamId,
                'error' => $e->getMessage(),
            ]);
            Cache::put($key, [
                'status' => 'failed',
                'message' => __('Could not generate the estimate. Please try again.'),
            ], now()->addHour());

            return;
        } catch (\Throwable $e)
        {
            Log::error('GenerateEstimatorDemoQuoteJob failed', [
                'team_id' => $this->teamId,
                'error' => $e->getMessage(),
            ]);
            Cache::put($key, [
                'status' => 'failed',
                'message' => __('Could not generate the estimate. Please try again.'),
            ], now()->addHour());

            return;
        }

        Cache::put($key, [
            'status' => 'ready',
            'data' => $preview,
        ], now()->addHour());
        $finished = true;
    }
}
