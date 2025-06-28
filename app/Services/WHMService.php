<?php

namespace App\Services;

use App\Models\Domain;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Server;

class WHMService
{
    public function syncDomainsFromAllServers()
    {
        $serversString = env('WHM_SERVERS');

        if (empty($serversString)) {
            return [
                'success' => false,
                'errors' => ['No hay servidores configurados en WHM_SERVERS'],
            ];
        }

        $serversList = explode(',', $serversString);
        $successCount = 0;
        $errors = [];

        foreach ($serversList as $index => $serverString) {
            $server = explode(':', trim($serverString));

            if (count($server) < 3) {
                $errors[] = 'Configuración de servidor incorrecta. Formato requerido: hostname:usuario:token';
                continue;
            }

            try {
                $url = "https://{$server[0]}:2087";
                $response = Http::withHeaders([
                    'Authorization' => 'whm ' . $server[1] . ':' . $server[2],
                ])->get($url . '/json-api/listaccts');

                if ($response->successful()) {
                    $data = $response->json();

                    if (isset($data['acct'])) {
                        foreach ($data['acct'] as $account) {
                            $plan = $account['plan'] ?? $account['owner'] ?? null;

                            $domain = Domain::withTrashed()
                                ->where('domain', $account['domain'])
                                ->where('server_url', $server[0])
                                ->first();

                            if ($domain && $domain->trashed()) {
                                $domain->restore();
                            }

                            Domain::updateOrCreate(
                                [
                                    'domain' => $account['domain'],
                                    'server_url' => $server[0],
                                ],
                                [
                                    'username' => $account['user'],
                                    'plan' => $plan,
                                    'status_id' => $account['suspended'],
                                    'data' => $account,
                                ],
                            );
                        }
                        $successCount++;
                    }
                } else {
                    $error = "Error en servidor {$server[0]}: " . $response->body();
                    $errors[] = $error;
                    Log::error($error);
                }
            } catch (\Exception $e) {
                $error = "Error en servidor {$server[0]}: " . $e->getMessage();
                $errors[] = $error;
                Log::error($error, [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return [
            'success' => $successCount > 0,
            'total_servers' => count($serversList),
            'successful_servers' => $successCount,
            'errors' => $errors,
        ];
    }

    public function testConnections()
    {
        $serversString = env('WHM_SERVERS');
        if (empty($serversString)) {
            return ['error' => 'No hay servidores configurados en WHM_SERVERS'];
        }

        $results = [];
        $serversList = explode(',', $serversString);

        foreach ($serversList as $index => $serverString) {
            $server = explode(':', trim($serverString));

            $results[] = [
                'index' => $index,
                'raw_string' => $serverString,
                'parsed_components' => count($server),
                'components' => $server,
                'test_result' => $this->testSingleServer($server),
            ];
        }

        return $results;
    }

    private function testSingleServer($server)
    {
        try {
            if (count($server) < 3) {
                return [
                    'success' => false,
                    'error' => 'Faltan componentes. Se necesitan 3 (servidor:usuario:token)',
                    'components_found' => count($server),
                ];
            }

            // Intentar resolver el hostname
            $ip = gethostbyname($server[0]);
            if ($ip === $server[0]) {
                return [
                    'success' => false,
                    'error' => 'No se pudo resolver el hostname',
                    'hostname' => $server[0],
                ];
            }

            // Probar la conexión
            $url = "https://{$server[0]}:2087";
            $response = Http::withHeaders([
                'Authorization' => 'whm ' . $server[1] . ':' . $server[2],
            ])
                ->timeout(10)
                ->get($url . '/json-api/version');

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'response' => $response->json(),
                'ip' => $ip,
                'url' => $url,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }
    }

    public function getDomainsFromServer(Server $server)
    {
        if ($server->control_panel !== 'cpanel' || !$server->hasToken()) {
            return [
                'success' => false,
                'error' => 'Server is not configured for cPanel or missing token'
            ];
        }

        try {
            $url = "https://{$server->server_url}:2087";
            $response = Http::withHeaders([
                'Authorization' => $server->getWhmAuthHeader(),
            ])
                ->timeout(30)
                ->get($url . '/json-api/listaccts');

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['acct'])) {
                    return [
                        'success' => true,
                        'domains' => collect($data['acct'])->map(function($account) {
                            return [
                                'domain' => $account['domain'],
                                'user' => $account['user'],
                                'plan' => $account['plan'] ?? $account['owner'] ?? null,
                                'suspended' => $account['suspended'] ?? 0,
                                'disk_used' => $account['diskused'] ?? 0,
                                'disk_limit' => $account['disklimit'] ?? 0,
                                'email' => $account['email'] ?? null,
                                'ip' => $account['ip'] ?? null,
                                'theme' => $account['theme'] ?? null,
                                'shell' => $account['shell'] ?? null,
                                'partition' => $account['partition'] ?? null,
                                'max_ftp' => $account['maxftp'] ?? null,
                                'max_sql' => $account['maxsql'] ?? null,
                                'max_pop' => $account['maxpop'] ?? null,
                                'max_lists' => $account['maxlst'] ?? null,
                                'max_sub' => $account['maxsub'] ?? null,
                                'max_park' => $account['maxpark'] ?? null,
                                'max_addon' => $account['maxaddon'] ?? null,
                                'startdate' => $account['startdate'] ?? null,
                            ];
                        })->sortBy('domain')->values()
                    ];
                }
                
                return [
                    'success' => true,
                    'domains' => collect([])
                ];
            }
            
            return [
                'success' => false,
                'error' => 'API request failed: ' . $response->body(),
                'status_code' => $response->status()
            ];
            
        } catch (\Exception $e) {
            Log::error('Error getting domains from server: ' . $e->getMessage(), [
                'server_id' => $server->id,
                'server_url' => $server->server_url,
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return [
                'success' => false,
                'error' => 'Connection error: ' . $e->getMessage()
            ];
        }
    }

    public function syncDomainsFromServer(Server $server)
    {
        $result = $this->getDomainsFromServer($server);
        
        if (!$result['success']) {
            return $result;
        }

        $synced = 0;
        
        foreach ($result['domains'] as $accountData) {
            $domain = Domain::withTrashed()
                ->where('domain', $accountData['domain'])
                ->where('server_id', $server->id)
                ->first();

            if ($domain && $domain->trashed()) {
                $domain->restore();
            }

            Domain::updateOrCreate(
                [
                    'domain' => $accountData['domain'],
                    'server_id' => $server->id
                ],
                [
                    'username' => $accountData['user'],
                    'plan' => $accountData['plan'],
                    'suspended' => $accountData['suspended'],
                    'data' => $accountData
                ]
            );
            
            $synced++;
        }

        return [
            'success' => true,
            'domains_synced' => $synced,
            'total_domains' => $result['domains']->count()
        ];
    }

    public function syncDomainsFromAllDatabaseServers()
    {
        // Get all cPanel servers with tokens from database
        $servers = Server::where('control_panel', 'cpanel')
                         ->whereNotNull('encrypted_token')
                         ->get();

        if ($servers->isEmpty()) {
            return [
                'success' => false,
                'errors' => ['No cPanel servers with tokens found in database']
            ];
        }

        $successCount = 0;
        $errors = [];

        foreach ($servers as $server) {
            try {
                $result = $this->syncDomainsFromServer($server);
                
                if ($result['success']) {
                    $successCount++;
                    Log::info("Successfully synced {$result['domains_synced']} domains from server {$server->name} ({$server->server_url})");
                } else {
                    $error = "Error syncing from server {$server->name} ({$server->server_url}): " . $result['error'];
                    $errors[] = $error;
                    Log::error($error);
                }
            } catch (\Exception $e) {
                $error = "Error syncing from server {$server->name} ({$server->server_url}): " . $e->getMessage();
                $errors[] = $error;
                Log::error($error, [
                    'server_id' => $server->id,
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return [
            'success' => $successCount > 0,
            'total_servers' => $servers->count(),
            'successful_servers' => $successCount,
            'errors' => $errors
        ];
    }
}
