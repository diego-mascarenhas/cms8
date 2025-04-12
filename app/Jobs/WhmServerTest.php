<?php

namespace App\Jobs;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Services\WhmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WhmServerTest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('whm-tests');
    }

    public function handle(WhmService $whmService)
    {
        $results = $whmService->testConnections();

        foreach ($results as $result)
        {
            if (isset($result['components']) && count($result['components']) >= 3)
            {
                $testResult = $result['test_result'];
                $status = $testResult['success'] ?? false
                    ? ServerStatus::ACTIVE
                    : ServerStatus::INACTIVE;

                Server::updateOrCreate(
                    ['server_url' => $result['components'][0]],
                    [
                        'username' => $result['components'][1],
                        'success' => $testResult['success'] ?? false,
                        'status_id' => $status->value,
                        'details' => $testResult
                    ]
                );
            }
        }
    }
}