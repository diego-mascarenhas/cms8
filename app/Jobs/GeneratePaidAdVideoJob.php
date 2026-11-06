<?php

namespace App\Jobs;

use App\Services\PaidAds\PaidAdVideoGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeneratePaidAdVideoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 360;

    /**
     * @param  array{headline?: string|null, body?: string|null, voice?: string|null, voice_id?: string|null, scenes: array<int, array{caption: string, spoken: string, visual: string}>}  $payload
     */
    public function __construct(
        public int $teamId,
        public string $videoId,
        public array $payload,
    ) {}

    public function handle(PaidAdVideoGenerationService $videos): void
    {
        $videos->render($this->teamId, $this->videoId, $this->payload);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('GeneratePaidAdVideoJob failed', [
            'video_id' => $this->videoId,
            'error' => $exception->getMessage(),
        ]);

        app(PaidAdVideoGenerationService::class)->markFailed(
            $this->teamId,
            $this->videoId,
            __('No se pudo generar el vídeo. Probá de nuevo.'),
        );
    }
}
