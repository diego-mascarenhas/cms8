<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Server;
use App\Services\ControlPanel\CpanelConnector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WHMService
{
    public function __construct(
        private CpanelConnector $cpanelConnector,
    ) {}

    public function syncDomainsFromAllServers(): array
    {
        $servers = Server::query()
            ->where('control_panel', 'cpanel')
            ->whereNotNull('encrypted_token')
            ->get();

        if ($servers->isEmpty())
        {
            return $this->syncDomainsFromEnvServers();
        }

        $successCount = 0;
        $errors = [];

        foreach ($servers as $server)
        {
            $result = $this->syncDomainsFromServer($server);

            if ($result['success'])
            {
                $successCount++;
            } elseif (isset($result['error']))
            {
                $errors[] = "Error en servidor {$server->server_url}: {$result['error']}";
            }
        }

        return [
            'success' => $successCount > 0,
            'total_servers' => $servers->count(),
            'successful_servers' => $successCount,
            'errors' => $errors,
        ];
    }

    public function testConnections(): array
    {
        $servers = Server::query()
            ->where('control_panel', 'cpanel')
            ->whereNotNull('encrypted_token')
            ->get();

        if ($servers->isEmpty())
        {
            return $this->testEnvConnections();
        }

        $results = [];

        foreach ($servers as $index => $server)
        {
            $results[] = [
                'index' => $index,
                'server_id' => $server->id,
                'server_url' => $server->server_url,
                'test_result' => $this->cpanelConnector->testConnection($server),
            ];
        }

        return $results;
    }

    public function getDomainsFromServer(Server $server): array
    {
        return $this->cpanelConnector->listAccounts($server);
    }

    public function syncDomainsFromServer(Server $server): array
    {
        return $this->cpanelConnector->syncAccounts($server);
    }

    /**
     * @deprecated Use servers table instead of WHM_SERVERS env.
     */
    private function syncDomainsFromEnvServers(): array
    {
        $serversString = env('WHM_SERVERS');

        if (empty($serversString))
        {
            return [
                'success' => false,
                'errors' => ['No hay servidores cPanel configurados en la base de datos ni en WHM_SERVERS'],
            ];
        }

        $serversList = explode(',', $serversString);
        $successCount = 0;
        $errors = [];

        foreach ($serversList as $serverString)
        {
            $server = explode(':', trim($serverString));

            if (count($server) < 3)
            {
                $errors[] = 'Configuración de servidor incorrecta. Formato requerido: hostname:usuario:token';

                continue;
            }

            try
            {
                $url = "https://{$server[0]}:2087";
                $response = Http::withHeaders([
                    'Authorization' => 'whm '.$server[1].':'.$server[2],
                ])->get($url.'/json-api/listaccts');

                if ($response->successful())
                {
                    $data = $response->json();

                    if (isset($data['acct']))
                    {
                        $dbServer = Server::query()->where('server_url', $server[0])->first();

                        foreach ($data['acct'] as $account)
                        {
                            $plan = $account['plan'] ?? $account['owner'] ?? null;

                            if (! $dbServer)
                            {
                                continue;
                            }

                            $domain = Domain::withTrashed()
                                ->where('domain', $account['domain'])
                                ->where('server_id', $dbServer->id)
                                ->first();

                            if ($domain && $domain->trashed())
                            {
                                $domain->restore();
                            }

                            Domain::updateOrCreate(
                                [
                                    'domain' => $account['domain'],
                                    'server_id' => $dbServer->id,
                                ],
                                [
                                    'username' => $account['user'],
                                    'plan' => $plan,
                                    'suspended' => (bool) ($account['suspended'] ?? false),
                                    'data' => $account,
                                ],
                            );
                        }
                        $successCount++;
                    }
                } else
                {
                    $error = "Error en servidor {$server[0]}: ".$response->body();
                    $errors[] = $error;
                    Log::error($error);
                }
            } catch (\Exception $e)
            {
                $error = "Error en servidor {$server[0]}: ".$e->getMessage();
                $errors[] = $error;
                Log::error($error, [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
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

    /**
     * @deprecated Use servers table instead of WHM_SERVERS env.
     */
    private function testEnvConnections(): array
    {
        $serversString = env('WHM_SERVERS');
        if (empty($serversString))
        {
            return ['error' => 'No hay servidores configurados'];
        }

        $results = [];
        $serversList = explode(',', $serversString);

        foreach ($serversList as $index => $serverString)
        {
            $server = explode(':', trim($serverString));

            $results[] = [
                'index' => $index,
                'raw_string' => $serverString,
                'parsed_components' => count($server),
                'components' => $server,
                'test_result' => $this->testSingleEnvServer($server),
            ];
        }

        return $results;
    }

    /**
     * @param  array<int, string>  $server
     * @return array<string, mixed>
     */
    private function testSingleEnvServer(array $server): array
    {
        try
        {
            if (count($server) < 3)
            {
                return [
                    'success' => false,
                    'error' => 'Faltan componentes. Se necesitan 3 (servidor:usuario:token)',
                    'components_found' => count($server),
                ];
            }

            $ip = gethostbyname($server[0]);
            if ($ip === $server[0])
            {
                return [
                    'success' => false,
                    'error' => 'No se pudo resolver el hostname',
                    'hostname' => $server[0],
                ];
            }

            $url = "https://{$server[0]}:2087";
            $response = Http::withHeaders([
                'Authorization' => 'whm '.$server[1].':'.$server[2],
            ])
                ->timeout(10)
                ->get($url.'/json-api/version');

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'response' => $response->json(),
                'ip' => $ip,
                'url' => $url,
            ];
        } catch (\Exception $e)
        {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }
    }
}
