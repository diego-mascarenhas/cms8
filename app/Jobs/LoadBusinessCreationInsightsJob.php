<?php

namespace App\Jobs;

use App\Models\BusinessCreationSession;
use App\Services\BusinessCreationInsightsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class LoadBusinessCreationInsightsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

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
}
