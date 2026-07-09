<?php

namespace App\Jobs;

use App\Models\PaidAdCampaign;
use App\Services\PaidAdPublishOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishPaidAdCampaignJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $campaignId) {}

    public function handle(PaidAdPublishOrchestrator $orchestrator): void
    {
        $campaign = PaidAdCampaign::withoutGlobalScopes()
            ->with('platforms.connection')
            ->find($this->campaignId);

        if ($campaign === null)
        {
            return;
        }

        $orchestrator->publish($campaign);
    }
}
