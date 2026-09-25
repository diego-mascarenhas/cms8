<?php

namespace App\Jobs;

use App\Models\GoLink;
use App\Models\GoLinkHit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RecordGoLinkHitJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array{ip_hash?: string|null, user_agent?: string|null, referer?: string|null}  $meta
     */
    public function __construct(
        public int $goLinkId,
        public array $meta = [],
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $link = GoLink::query()->find($this->goLinkId);
        if (! $link)
        {
            return;
        }

        try
        {
            GoLinkHit::query()->create([
                'go_link_id' => $link->id,
                'ip_hash' => $this->meta['ip_hash'] ?? null,
                'user_agent' => $this->meta['user_agent'] ?? null,
                'referer' => $this->meta['referer'] ?? null,
                'created_at' => now(),
            ]);

            $link->forceFill([
                'hits_count' => $link->hits_count + 1,
                'last_hit_at' => now(),
            ])->save();
        } catch (\Throwable $exception)
        {
            Log::warning('RecordGoLinkHitJob failed', [
                'go_link_id' => $this->goLinkId,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
