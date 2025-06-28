<?php

namespace App\Jobs;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Services\WHMService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WhmServerTest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('whm-tests');
    }

    public function handle(WHMService $whmService)
    {
        try {
            $results = $whmService->testConnections();

            foreach ($results as $result) {
                if (isset($result['components']) && count($result['components']) >= 3) {
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
                            'details' => $testResult,
                        ],
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error('Critical error in WHM server test: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }
}
