<?php

namespace App\Jobs;

use App\Http\Controllers\OvhApiController;
use App\Models\Category;
use App\Models\Domain;
use App\Models\Enterprise;
use App\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OvhServiceSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $verbose;

    // Service category mapping
    protected $categoryMapping = [
        'domain' => 401, // Domain Names - Hardcoded category ID
        'emailpro' => 405, // Email Pro - Hardcoded category ID
        'webHosting' => 402, // Web Hosting - Hardcoded category ID
        'privateDatabase' => 408, // Private Database - Hardcoded category ID
        'vps' => 401, // VPS - Hardcoded category ID
        'cloudProject' => 409, // Cloud Project - Hardcoded category ID
        'cpanel' => 407, // cPanel License - Hardcoded category ID
        'email' => 405, // Email Domain - Hardcoded category ID
        'dns' => 403, // DNS Zones - Hardcoded category ID
        'vRack' => 410, // vRack - Hardcoded category ID
        'default' => 1, // Default "Services" category ID
    ];

    public function __construct(bool $verbose = false)
    {
        $this->verbose = $verbose;
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
            Log::info('Starting OVH domain sync job', ['verbose' => $this->verbose]);

            // Create instance of OvhApiController to use its methods
            $ovhController = app(OvhApiController::class);

            // Get services from OVH API
            $response = $ovhController->getServicesRaw();

            // Log the response for debugging
            if ($this->verbose) {
                Log::debug('OVH API response', [
                    'status' => $response['status'] ?? 'unknown',
                    'count' => count($response['data'] ?? []),
                    'sample' => isset($response['data']) ? json_encode(array_slice($response['data'], 0, 2)) : 'no data',
                ]);
            }

            if (! isset($response['status']) || $response['status'] !== 'success') {
                Log::error('Failed to fetch OVH services', ['response' => $response]);

                return;
            }

            $services = $response['data'];
            Log::info('Retrieved services count: '.count($services));

            $importCount = 0;
            $updateCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
            $skippedReasons = [];
            $errorReasons = [];

            foreach ($services as $index => $service) {
                // Generate a simple service ID for logging
                $serviceId = $service['id'] ?? $service['serviceName'] ?? "index-{$index}";

                // Log service details in verbose mode
                if ($this->verbose) {
                    Log::debug('Processing service', [
                        'index' => $index,
                        'service_id' => $serviceId,
                        'domain' => $service['domain'] ?? 'no domain',
                        'serviceName' => $service['serviceName'] ?? 'no serviceName',
                        'resource_name' => isset($service['resource']) ? ($service['resource']['name'] ?? 'no resource.name') : 'no resource',
                        'category' => $service['category'] ?? 'no category',
                        'status' => $service['state'] ?? $service['status'] ?? 'no status',
                    ]);
                }

                $result = $this->processDomain($service, $index);

                if (is_array($result)) {
                    $status = $result['status'];
                    $reason = $result['reason'] ?? 'No reason provided';

                    if ($status === 'updated') {
                        $updateCount++;
                    } elseif ($status === 'imported') {
                        $importCount++;
                    } elseif ($status === 'skipped') {
                        $skippedCount++;
                        $skippedReasons[$reason] = ($skippedReasons[$reason] ?? 0) + 1;

                        // Always log skipped services with their reason
                        Log::info("Skipped service: {$serviceId}", [
                            'reason' => $reason,
                            'category' => $service['category'] ?? 'unknown',
                        ]);
                    } elseif ($status === 'error') {
                        $errorCount++;
                        $errorReasons[$reason] = ($errorReasons[$reason] ?? 0) + 1;

                        // Always log error services with their reason
                        Log::error("Failed to process service: {$serviceId}", [
                            'reason' => $reason,
                            'category' => $service['category'] ?? 'unknown',
                        ]);
                    }
                } else {
                    // For backward compatibility
                    if ($result === 'updated') {
                        $updateCount++;
                    } elseif ($result === 'imported') {
                        $importCount++;
                    } elseif ($result === 'skipped') {
                        $skippedCount++;
                        $skippedReasons['unknown'] = ($skippedReasons['unknown'] ?? 0) + 1;
                    } elseif ($result === 'error') {
                        $errorCount++;
                        $errorReasons['unknown'] = ($errorReasons['unknown'] ?? 0) + 1;
                    }
                }
            }

            Log::info('OVH domain sync completed', [
                'imported' => $importCount,
                'updated' => $updateCount,
                'skipped' => $skippedCount,
                'error' => $errorCount,
                'skipped_reasons' => $skippedReasons,
                'error_reasons' => $errorReasons,
                'total' => count($services),
            ]);

            // Output for console when in verbose mode
            if ($this->verbose) {
                echo "OVH Domain Sync Completed\n";
                echo "------------------------\n";
                echo "Imported: $importCount\n";
                echo "Updated: $updateCount\n";
                echo "Skipped: $skippedCount\n";
                echo "Errors: $errorCount\n";

                if (! empty($skippedReasons)) {
                    echo "\nSkipped reasons:\n";
                    foreach ($skippedReasons as $reason => $count) {
                        echo "  - $reason: $count\n";
                    }
                }

                if (! empty($errorReasons)) {
                    echo "\nError reasons:\n";
                    foreach ($errorReasons as $reason => $count) {
                        echo "  - $reason: $count\n";
                    }
                }

                echo "\nTotal services: ".count($services)."\n";
            }
        } catch (\Exception $e) {
            Log::error('Error in OVH domain sync: '.$e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($this->verbose) {
                echo "ERROR: {$e->getMessage()}\n";
            }
        }
    }

    /**
     * Process a single domain/service from OVH
     *
     * @return array|string Result status and reason
     */
    protected function processDomain(array $service, int $index)
    {
        // Generate a service ID for logging
        $serviceId = $service['id'] ?? $service['serviceName'] ?? "index-{$index}";

        // 1. Check for required service data
        if (empty($service)) {
            return [
                'status' => 'skipped',
                'reason' => 'Empty service data',
            ];
        }

        // 2. Find a suitable domain name
        $domainName = $this->findDomainName($service);

        if (! $domainName) {
            return [
                'status' => 'skipped',
                'reason' => 'No valid domain identifier found',
            ];
        }

        // 3. Determine appropriate category ID based on service type
        $category = $this->determineCategory($service);
        $categoryId = $this->categoryMapping[$category] ?? $this->categoryMapping['default'];
        Log::debug('Category mapping', [
            'category' => $category,
            'mapped_id' => $categoryId,
            'mapping' => $this->categoryMapping,
        ]);

        // 4. Set enterprise ID
        $enterpriseId = config('services.ovh.enterprise_id', env('OVH_ENTERPRISE_ID', 1));
        Log::debug('Initial enterprise ID set', ['enterprise_id' => $enterpriseId]);

        if ($this->verbose) {
            Log::debug('Using mapped category', [
                'original_category' => $category,
                'category_id' => $categoryId,
                'enterprise_id' => $enterpriseId,
                'domain' => $domainName,
            ]);
        }

        // 5. Determine the server URL
        $serverUrl = $this->determineServerUrl($service, $domainName);

        // 6. Generate a unique key to search for existing services
        $uniqueKey = md5($domainName.($service['id'] ?? '').($service['serviceName'] ?? ''));

        try {
            // 7. Check if service already exists in services table
            $existingService = Service::where('data->unique_key', $uniqueKey)->first();

            // Extract dates from service if available
            $creationDate = null;
            $expirationDate = null;

            if (isset($service['creationDate'])) {
                try {
                    $creationDate = \Carbon\Carbon::parse($service['creationDate']);
                } catch (\Exception $e) {
                    if ($this->verbose) {
                        Log::debug('Could not parse creation date', [
                            'date' => $service['creationDate'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            if (isset($service['expirationDate'])) {
                try {
                    $expirationDate = \Carbon\Carbon::parse($service['expirationDate']);
                } catch (\Exception $e) {
                    if ($this->verbose) {
                        Log::debug('Could not parse expiration date', [
                            'date' => $service['expirationDate'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Calculate frequency in months based on renewal interval
            $frequency = 1; // Default to monthly
            if (isset($service['renew']) && isset($service['renew']['interval'])) {
                $interval = $service['renew']['interval'];
                if (preg_match('/P(\d+)([YMD])/', $interval, $matches)) {
                    $value = (int) $matches[1];
                    $unit = $matches[2];

                    switch ($unit) {
                        case 'Y': // Years
                            $frequency = $value * 12;
                            break;
                        case 'M': // Months
                            $frequency = $value;
                            break;
                        case 'D': // Days (approximate to months)
                            $frequency = max(1, intval($value / 30));
                            break;
                    }
                }

                if ($this->verbose) {
                    Log::debug('Calculated frequency', [
                        'interval' => $interval,
                        'frequency_months' => $frequency,
                    ]);
                }
            }

            // Prepare JSON data with necessary conversions for clean storage
            $fullData = [];
            foreach ($service as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    // Recursively convert objects to arrays for consistent storage
                    $fullData[$key] = json_decode(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), true);
                } else {
                    $fullData[$key] = $value;
                }
            }

            // Prepare data for storage - use JSON_UNESCAPED_SLASHES to keep URLs readable
            $jsonData = [
                'unique_key' => $uniqueKey,
                'domain' => $domainName,
                'server_url' => $serverUrl,
                'username' => $service['contactAdmin'] ?? $service['contactTech'] ?? 'ovh-user',
                'serviceName' => $service['serviceName'] ?? null,
                'resource_name' => isset($service['resource']) ? ($service['resource']['name'] ?? null) : null,
                'state' => $service['state'] ?? $service['status'] ?? null,
                'category_type' => $category,
                'full_data' => $fullData,
            ];

            $serviceData = [
                'enterprise_id' => $enterpriseId,
                'category_id' => $categoryId,
                'operation' => 'buy',
                'description' => isset($service['resource']) && isset($service['resource']['displayName']) ?
                    $service['resource']['displayName'] : $domainName,
                'data' => $jsonData,
                'currency_id' => 1, // Default to EUR
                'price' => $service['amount'] ?? 0,
                'frequency' => $frequency,
                'status' => ($service['state'] ?? $service['status'] ?? '') === 'ok' ? 4 : 1, // 4=Active, 1=Suspended
            ];

            // Set expiration date if available
            if ($expirationDate) {
                $serviceData['expires_at'] = $expirationDate;
            }

            // Set next billing date if available
            if (isset($service['nextBillingDate'])) {
                try {
                    $serviceData['next_billing'] = \Carbon\Carbon::parse($service['nextBillingDate']);
                } catch (\Exception $e) {
                    // Skip if unparseable
                }
            }

            Log::debug('Service data preparation', [
                'enterprise_id' => $enterpriseId,
                'category_id' => $categoryId,
            ]);

            if ($existingService) {
                Log::debug('Updating existing service', [
                    'service_id' => $existingService->id,
                    'enterprise_id' => $serviceData['enterprise_id'],
                    'category_id' => $serviceData['category_id'],
                ]);
                // Update existing service but preserve created_at
                $existingService->update($serviceData);
                Log::info("Updated service: {$serviceId}", [
                    'domain' => $domainName,
                    'category' => $category,
                ]);

                return [
                    'status' => 'updated',
                    'reason' => 'Service already exists',
                ];
            } else {
                // Create new service with original creation date if available
                if ($creationDate) {
                    $serviceData['created_at'] = $creationDate;
                    $serviceData['updated_at'] = $creationDate;
                }

                try {
                    $newService = Service::create($serviceData);
                    Log::debug('Service created successfully', [
                        'id' => $newService->id,
                    ]);
                    Log::info("Imported service: {$serviceId}", [
                        'domain' => $domainName,
                        'category' => $category,
                    ]);

                    return [
                        'status' => 'imported',
                        'reason' => 'New service created',
                    ];
                } catch (\Exception $e) {
                    Log::error('Error creating service', [
                        'error' => $e->getMessage(),
                        'enterprise_id' => $serviceData['enterprise_id'],
                        'category_id' => $serviceData['category_id'],
                        'domain' => $domainName,
                        'trace' => $e->getTraceAsString(),
                    ]);

                    return [
                        'status' => 'error',
                        'reason' => 'Database error: '.$e->getMessage(),
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('Error saving service: '.$e->getMessage(), [
                'service_id' => $serviceId,
                'domain' => $domainName,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'status' => 'error',
                'reason' => 'Database error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Find the best domain name from various service fields
     */
    private function findDomainName(array $service): ?string
    {
        // Priority 1: Domain field
        if (isset($service['domain']) && ! empty($service['domain'])) {
            if ($this->verbose) {
                Log::debug('Using domain field', ['domain' => $service['domain']]);
            }

            return $service['domain'];
        }

        // Priority 2: Resource name if it looks like a domain/hostname
        if (isset($service['resource']) && isset($service['resource']['name']) && ! empty($service['resource']['name'])) {
            if ($this->verbose) {
                Log::debug('Using resource.name as domain', ['resource_name' => $service['resource']['name']]);
            }

            return $service['resource']['name'];
        }

        // Priority 3: ServiceName if it looks like a domain/hostname
        if (isset($service['serviceName']) && ! empty($service['serviceName'])) {
            if ($this->verbose) {
                Log::debug('Using serviceName as domain', ['serviceName' => $service['serviceName']]);
            }

            return $service['serviceName'];
        }

        // Priority 4: Resource displayName if it exists
        if (isset($service['resource']) && isset($service['resource']['displayName']) && ! empty($service['resource']['displayName'])) {
            if ($this->verbose) {
                Log::debug('Using resource.displayName as domain', ['displayName' => $service['resource']['displayName']]);
            }

            return $service['resource']['displayName'];
        }

        return null;
    }

    /**
     * Determine the appropriate server URL for a service
     */
    private function determineServerUrl(array $service, string $domainName): string
    {
        // Default server URL
        $serverUrl = 'ovh.com';

        // Use resource.name if available and valid
        if (isset($service['resource']) && isset($service['resource']['name'])) {
            $resourceName = $service['resource']['name'];
            if (filter_var($resourceName, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                $serverUrl = $resourceName;
                if ($this->verbose) {
                    Log::debug('Using resource.name as server_url', ['server_url' => $serverUrl]);
                }

                return $serverUrl;
            }
        }

        // Use the domain itself if it's different from the resource name
        if (filter_var($domainName, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $serverUrl = $domainName;
            if ($this->verbose) {
                Log::debug('Using domain as server_url', ['server_url' => $serverUrl]);
            }

            return $serverUrl;
        }

        if ($this->verbose) {
            Log::debug('Using default server_url', ['server_url' => $serverUrl]);
        }

        return $serverUrl;
    }

    /**
     * Determinar la categoría correcta para un servicio
     *
     * @return string Clave de categoría
     */
    private function determineCategory(array $service): string
    {
        // Verificar categoría explícita
        if (isset($service['category']) && ! empty($service['category'])) {
            return $service['category'];
        }

        // Detectar por URL o path en la ruta
        if (isset($service['route']) && isset($service['route']['path'])) {
            $path = $service['route']['path'];

            if (strpos($path, '/domain/zone') !== false) {
                return 'domain';
            }
            if (strpos($path, '/email/pro') !== false) {
                return 'emailpro';
            }
            if (strpos($path, '/hosting/web') !== false) {
                return 'webHosting';
            }
            if (strpos($path, '/vps') !== false) {
                return 'vps';
            }
            if (strpos($path, '/cloud/project') !== false) {
                return 'cloudProject';
            }
            if (strpos($path, '/hosting/privateDatabase') !== false ||
                strpos($path, '/privateDatabase') !== false) {
                return 'privateDatabase';
            }
            if (strpos($path, '/email/domain') !== false) {
                return 'email';
            }
            if (strpos($path, '/vrack') !== false) {
                return 'vRack';
            }
        }

        // Detectar por nombre del recurso o serviceName
        $resourceName = $service['resource']['name'] ?? '';
        $serviceName = $service['serviceName'] ?? '';

        if (strpos($resourceName, 'vps') !== false || strpos($serviceName, 'vps') !== false) {
            return 'vps';
        }

        if (strpos($resourceName, 'hosting') !== false ||
            strpos($serviceName, 'cluster') !== false ||
            strpos($resourceName, 'cluster') !== false) {
            return 'webHosting';
        }

        if (strpos($resourceName, 'cloud') !== false || strpos($serviceName, 'cloud') !== false) {
            return 'cloudProject';
        }

        if (strpos($resourceName, 'email') !== false || strpos($serviceName, 'email') !== false) {
            if (strpos($resourceName, 'pro') !== false || strpos($serviceName, 'pro') !== false) {
                return 'emailpro';
            }

            return 'email';
        }

        // Si no se pudo determinar, usar default
        Log::debug('Could not determine category, using default', [
            'resource_name' => $resourceName,
            'service_name' => $serviceName,
            'route' => $service['route'] ?? 'no route',
        ]);

        return 'default';
    }
}
