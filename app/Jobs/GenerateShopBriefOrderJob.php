<?php

namespace App\Jobs;

use App\Models\Team;
use App\Services\EstimatorProductQuoteService;
use App\Services\ShopBriefOrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GenerateShopBriefOrderJob implements ShouldQueue
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
        return 'shop-brief-order:'.$pollToken;
    }

    public function handle(EstimatorProductQuoteService $quotes, ShopBriefOrderService $orders): void
    {
        $key = self::cacheKey($this->pollToken);
        ignore_user_abort(true);
        set_time_limit($this->timeout);
        $finished = false;
        register_shutdown_function(function () use ($key, &$finished): void
        {
            if ($finished)
            {
                return;
            }

            try
            {
                if (app()->runningUnitTests() || ! app()->bound('cache'))
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
                    'team_id' => $this->teamId,
                    'message' => __('Could not generate the estimate. Please try again.'),
                ], now()->addHour());
            } catch (\Throwable)
            {
                return;
            }
        });

        $team = Team::withoutGlobalScopes()->find($this->teamId);
        if (! $team)
        {
            $finished = true;
            $this->failCache($key);

            return;
        }

        try
        {
            $order = $orders->create($team, $quotes->quote($this->brief, $team), 'shop');
        } catch (\Throwable $exception)
        {
            Log::error('GenerateShopBriefOrderJob failed', [
                'team_id' => $this->teamId,
                'error' => $exception->getMessage(),
            ]);
            $finished = true;
            $this->failCache($key);

            return;
        }

        $finished = true;
        Cache::put($key, [
            'status' => 'ready',
            'team_id' => $this->teamId,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ], now()->addHour());
    }

    private function failCache(string $key): void
    {
        Cache::put($key, [
            'status' => 'failed',
            'team_id' => $this->teamId,
            'message' => __('Could not generate the estimate. Please try again.'),
        ], now()->addHour());
    }
}
