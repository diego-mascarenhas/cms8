<?php

namespace App\Jobs;

use App\Enums\AdConnectionStatus;
use App\Models\AdPlatformConnection;
use App\Services\Ads\AdPlatformGatewayFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RefreshAdPlatformTokenJob implements ShouldQueue
{
    use Queueable;

    public function handle(AdPlatformGatewayFactory $gateways): void
    {
        AdPlatformConnection::withoutGlobalScopes()
            ->where('status', AdConnectionStatus::Active->value)
            ->whereNotNull('refresh_token')
            ->where('access_token_expires_at', '<=', now()->addHour())
            ->each(function (AdPlatformConnection $connection) use ($gateways): void
            {
                try
                {
                    $gateways->make($connection->platform)->refreshToken($connection);
                } catch (Throwable)
                {
                    // Expiry is flagged inside the gateway; skip and retry next run.
                }
            });
    }
}
