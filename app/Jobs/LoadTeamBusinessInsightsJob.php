<?php

namespace App\Jobs;

use App\Models\Team;
use App\Services\BusinessCreationInsightsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class LoadTeamBusinessInsightsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public int $teamId,
    ) {}

    public function handle(BusinessCreationInsightsService $service): void
    {
        $team = Team::find($this->teamId);
        if (! $team)
        {
            return;
        }

        $service->runForTeam($team);
    }

    public function failed(Throwable $exception): void
    {
        $team = Team::find($this->teamId);
        if (! $team)
        {
            return;
        }

        $existing = $team->getSetting('business_config', []);
        if (is_string($existing))
        {
            $existing = json_decode($existing, true) ?: [];
        }
        if (! is_array($existing))
        {
            $existing = [];
        }
        $existing['_insights'] = [
            'potential_clients_summary' => 'No se pudo generar el informe ahora. Intenta de nuevo en unos minutos.',
        ];
        unset($existing['_insights_phase'], $existing['_insights_requested_at']);
        $team->setSetting('business_config', $existing, [
            'type' => 'json',
            'group' => 'business-config',
        ]);
    }
}
