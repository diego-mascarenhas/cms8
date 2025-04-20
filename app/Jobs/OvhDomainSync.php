<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Http\Controllers\OvhApiController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OvhDomainSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('ovh-sync');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            Log::info('Starting OVH domain sync job');
            
            // Create instance of OvhApiController to use its methods
            $ovhController = app(OvhApiController::class);
            
            // Get services from OVH API
            $response = $ovhController->getServicesRaw();
            
            if (!isset($response['status']) || $response['status'] !== 'success') {
                Log::error('Failed to fetch OVH services', ['response' => $response]);
                return;
            }
            
            $services = $response['data'];
            $importCount = 0;
            $updateCount = 0;
            
            foreach ($services as $service) {
                // For web hosting services
                if (isset($service['domain'])) {
                    // Check if domain already exists
                    $domain = Domain::where('domain', $service['domain'])->first();
                    
                    $serviceData = [
                        'domain' => $service['domain'],
                        'server_url' => 'ovh.com', // Default server URL for OVH services
                        'username' => $service['contactAdmin'] ?? 'ovh-user',
                        'plan' => $service['serviceName'] ?? null,
                        'suspended' => ($service['status'] ?? '') !== 'ok',
                        'is_working' => ($service['status'] ?? '') === 'ok',
                        'data' => json_encode($service) // Store all OVH data
                    ];
                    
                    if ($domain) {
                        // Update existing domain
                        $domain->update($serviceData);
                        $updateCount++;
                    } else {
                        // Create new domain
                        Domain::create($serviceData);
                        $importCount++;
                    }
                }
            }
            
            Log::info('OVH domain sync completed', [
                'imported' => $importCount,
                'updated' => $updateCount,
                'total' => count($services)
            ]);
        } catch (\Exception $e) {
            Log::error('Error in OVH domain sync: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }
} 