<?php

namespace App\Jobs;

use App\Models\BusinessCreationSession;
use App\Services\BusinessCreationSummaryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateBusinessSummaryJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 90;

    public function __construct(
        public int $businessCreationSessionId,
    ) {}

    public function handle(BusinessCreationSummaryService $service): void
    {
        $session = BusinessCreationSession::find($this->businessCreationSessionId);

        if (! $session)
        {
            return;
        }

        $service->run($session);
    }
}
