<?php

namespace App\Services;

use App\Models\Domain;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhmService
{
    public function syncDomainsFromAllServers()
    {
        $serversString = env('WHM_SERVERS');
        Log::info('WHM_SERVERS value:', ['servers' => $serversString]);

        if (empty($serversString)) {
            return [
                'success' => false,
                'errors' => ['No hay servidores configurados en WHM_SERVERS']
            ];
        }

        $serversList = explode(',', $serversString);
        Log::info('Servers list after explode:', ['list' => $serversList]);

        $successCount = 0;
        $errors = [];

        foreach ($serversList as $index => $serverString)
        {
            $server = explode(':', trim($serverString));
            Log::info('Processing server:', [
                'index' => $index,
                'raw_string' => $serverString,
                'parsed_components' => count($server),
                'components' => $server
            ]);

            if (count($server) < 3)
            {
                $errors[] = "Configuración de servidor incorrecta. Formato requerido: hostname:usuario:token";
                continue;
            }

            try
            {
                $url = "https://{$server[0]}:2087";
                $response = Http::withHeaders([
                    'Authorization' => 'whm ' . $server[1] . ':' . $server[2],
                ])->get($url . '/json-api/listaccts');

                if ($response->successful())
                {
                    $data = $response->json();

                    // Guardar la respuesta completa
                    // Log::info('WHM Response for server ' . $server[0], ['response' => $data]);

                    if (isset($data['acct']))
                    {
                        foreach ($data['acct'] as $account)
                        {
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
                                    'server_url' => $server[0]
                                ],
                                [
                                    'username' => $account['user'],
                                    'plan' => $plan,
                                    'status' => $account['suspended'] == 0,
                                ]
                            );
                        }
                        $successCount++;
                    }
                }
                else
                {
                    $error = "Error en servidor {$server[0]}: " . $response->body();
                    $errors[] = $error;
                    Log::error($error);
                }
            }
            catch (\Exception $e)
            {
                $error = "Error en servidor {$server[0]}: " . $e->getMessage();
                $errors[] = $error;
                Log::error($error, [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return [
            'success' => $successCount > 0,
            'total_servers' => count($serversList),
            'successful_servers' => $successCount,
            'errors' => $errors
        ];
    }

    public function testConnections()
    {
        $serversString = env('WHM_SERVERS');
        if (empty($serversString))
        {
            return ['error' => 'No hay servidores configurados en WHM_SERVERS'];
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
                'test_result' => $this->testSingleServer($server)
            ];
        }

        return $results;
    }

    private function testSingleServer($server)
    {
        try
        {
            if (count($server) < 3)
            {
                return [
                    'success' => false,
                    'error' => 'Faltan componentes. Se necesitan 3 (servidor:usuario:token)',
                    'components_found' => count($server)
                ];
            }

            // Intentar resolver el hostname
            $ip = gethostbyname($server[0]);
            if ($ip === $server[0])
            {
                return [
                    'success' => false,
                    'error' => 'No se pudo resolver el hostname',
                    'hostname' => $server[0]
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
                'url' => $url
            ];

        }
        catch (\Exception $e)
        {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
        }
    }
}
