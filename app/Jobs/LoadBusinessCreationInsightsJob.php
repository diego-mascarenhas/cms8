<?php

namespace App\Jobs;

use App\Models\BusinessCreationSession;
use App\Services\BusinessCreationInsightsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class LoadBusinessCreationInsightsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public int $businessCreationSessionId,
    ) {}

    public function handle(BusinessCreationInsightsService $service): void
    {
        $session = BusinessCreationSession::find($this->businessCreationSessionId);

        if (! $session)
        {
            return;
        }

        $service->run($session);
    }

    public function failed(Throwable $exception): void
    {
        $session = BusinessCreationSession::find($this->businessCreationSessionId);
        if (! $session)
        {
            return;
        }

        $config = $session->config ?? [];
        $config['_insights'] = [
            'potential_clients_summary' => 'No se pudo generar el informe ahora. Intenta de nuevo en unos minutos.',
        ];
        unset($config['_insights_phase'], $config['_insights_requested_at']);

        $session->update(['config' => $config]);
    }
}
