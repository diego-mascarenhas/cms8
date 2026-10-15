<?php

namespace App\Jobs;

use App\Services\Track\TrackEventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordTrackEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $type,
        public array $payload = [],
    ) {
        $this->onConnection((string) config('services.go.track_connection', 'redis'));
        $this->onQueue((string) config('services.go.track_queue', 'track'));
    }

    public function handle(TrackEventRecorder $recorder): void
    {
        $recorder->record($this->type, $this->payload);
    }
}
