<?php

namespace App\Jobs;

use App\Enums\SyncResource;
use App\Models\ExternalAccount;
use App\Models\SyncRun;
use App\Sync\Providers\GoogleCalendarSyncProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncGoogleCalendarEventsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(private readonly int $externalAccountId)
    {
    }

    public function handle(GoogleCalendarSyncProvider $provider): void
    {
        $account = ExternalAccount::query()->find($this->externalAccountId);

        if ($account === null)
        {
            return;
        }

        $run = SyncRun::query()->create([
            'external_account_id' => $account->id,
            'resource' => SyncResource::CalendarEvents,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try
        {
            $stats = $provider->sync($account);

            $run->forceFill([
                'status' => 'success',
                'pulled_count' => $stats['pulled_count'] ?? 0,
                'upserted_count' => $stats['upserted_count'] ?? 0,
                'deleted_count' => $stats['deleted_count'] ?? 0,
                'finished_at' => now(),
            ])->save();

            $account->forceFill(['last_synced_at' => now()])->save();
        } catch (\Throwable $exception)
        {
            Log::error('Google calendar sync failed.', [
                'external_account_id' => $account->id,
                'error' => $exception->getMessage(),
            ]);

            $run->forceFill([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();

            throw $exception;
        }
    }
}
