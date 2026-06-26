<?php

namespace App\Services\ControlPanel;

use App\Contracts\ControlPanelConnector;
use App\Models\Domain;
use App\Models\Server;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CpanelConnector implements ControlPanelConnector
{
    private const WHM_PORT = 2087;

    private const CPANEL_PORT = 2083;

    public function supports(Server $server): bool
    {
        return $server->control_panel === 'cpanel';
    }

    public function testConnection(Server $server): array
    {
        if (! $this->supports($server) || ! $server->hasToken())
        {
            return [
                'success' => false,
                'error' => 'Server is not configured for cPanel or missing token',
            ];
        }

        try
        {
            if ($server->usesCpanelAccountAuth())
            {
                $result = $this->accountUapiRequest($server, 'Variables', 'get_user_information');

                if (! $result['success'])
                {
                    return $result;
                }

                $info = $result['data'] ?? [];

                return [
                    'success' => true,
                    'version' => null,
                    'hostname' => $server->server_url,
                    'domain' => $info['domain'] ?? null,
                    'plan' => $info['plan'] ?? null,
                ];
            }

            $response = $this->whmRequest($server, '/json-api/version');

            if (! $response->successful())
            {
                return [
                    'success' => false,
                    'error' => 'API request failed: '.$response->body(),
                ];
            }

            $data = $response->json();

            return [
                'success' => true,
                'version' => $data['version'] ?? null,
                'hostname' => $data['hostname'] ?? null,
            ];
        } catch (\Exception $e)
        {
            Log::error('cPanel connection test failed: '.$e->getMessage(), [
                'server_id' => $server->id,
            ]);

            return [
                'success' => false,
                'error' => 'Connection error: '.$e->getMessage(),
            ];
        }
    }

    public function listAccounts(Server $server): array
    {
        if (! $this->supports($server) || ! $server->hasToken())
        {
            return [
                'success' => false,
                'error' => 'Server is not configured for cPanel or missing token',
            ];
        }

        if ($server->usesCpanelAccountAuth())
        {
            return $this->listAccountFromCpanelUser($server);
        }

        try
        {
            $response = $this->whmRequest($server, '/json-api/listaccts');

            if (! $response->successful())
            {
                return [
                    'success' => false,
                    'error' => 'API request failed: '.$response->body(),
                ];
            }

            $data = $response->json();
            $accounts = collect($data['acct'] ?? [])->map(fn (array $account) => $this->mapAccount($account));

            return [
                'success' => true,
                'domains' => $accounts,
            ];
        } catch (\Exception $e)
        {
            Log::error('Error listing cPanel accounts: '.$e->getMessage(), [
                'server_id' => $server->id,
            ]);

            return [
                'success' => false,
                'error' => 'Connection error: '.$e->getMessage(),
            ];
        }
    }

    public function syncAccounts(Server $server): array
    {
        $result = $this->listAccounts($server);

        if (! $result['success'])
        {
            return $result;
        }

        $synced = 0;

        /** @var Collection<int, array<string, mixed>> $domains */
        $domains = $result['domains'];

        foreach ($domains as $accountData)
        {
            $domain = Domain::withTrashed()
                ->where('domain', $accountData['domain'])
                ->where('server_id', $server->id)
                ->first();

            if ($domain && $domain->trashed())
            {
                $domain->restore();
            }

            Domain::updateOrCreate(
                [
                    'domain' => $accountData['domain'],
                    'server_id' => $server->id,
                ],
                [
                    'username' => $accountData['user'],
                    'plan' => $accountData['plan'],
                    'suspended' => (bool) ($accountData['suspended'] ?? false),
                    'data' => $accountData,
                ],
            );

            $synced++;
        }

        return [
            'success' => true,
            'domains_synced' => $synced,
            'total_domains' => $domains->count(),
        ];
    }

    public function listPlans(Server $server): array
    {
        if (! $this->supports($server) || ! $server->hasToken())
        {
            return [
                'success' => false,
                'error' => 'Server is not configured for cPanel or missing token',
            ];
        }

        if ($server->usesCpanelAccountAuth())
        {
            $result = $this->accountUapiRequest($server, 'Variables', 'get_user_information');

            if (! $result['success'])
            {
                return $result;
            }

            $plan = $result['data']['plan'] ?? null;

            return [
                'success' => true,
                'plans' => $plan ? [$plan] : [],
            ];
        }

        try
        {
            $response = $this->whmRequest($server, '/json-api/listpkgs');

            if (! $response->successful())
            {
                return [
                    'success' => false,
                    'error' => 'API request failed: '.$response->body(),
                ];
            }

            $packages = collect($response->json()['package'] ?? [])
                ->pluck('name')
                ->filter()
                ->values()
                ->all();

            return [
                'success' => true,
                'plans' => $packages,
            ];
        } catch (\Exception $e)
        {
            return [
                'success' => false,
                'error' => 'Connection error: '.$e->getMessage(),
            ];
        }
    }

    public function changePlan(Server $server, Domain $domain, string $plan): array
    {
        if ($server->usesCpanelAccountAuth())
        {
            return [
                'success' => false,
                'error' => 'Changing hosting plans requires WHM API access (root/reseller token).',
            ];
        }

        if (! $this->supports($server) || ! $server->hasToken())
        {
            return [
                'success' => false,
                'error' => 'Server is not configured for cPanel or missing token',
            ];
        }

        try
        {
            $response = $this->whmRequest($server, '/json-api/modifyacct', [
                'user' => $domain->username,
                'pkg' => $plan,
            ]);

            if (! $response->successful())
            {
                return [
                    'success' => false,
                    'error' => 'API request failed: '.$response->body(),
                ];
            }

            $domain->update(['plan' => $plan]);

            return ['success' => true];
        } catch (\Exception $e)
        {
            return [
                'success' => false,
                'error' => 'Connection error: '.$e->getMessage(),
            ];
        }
    }

    public function listEmailAccounts(Server $server, Domain $domain): array
    {
        $result = $this->uapiRequest($server, $domain, 'Email', 'list_pops_with_disk');

        if (! $result['success'])
        {
            return $result;
        }

        $emails = collect($result['data'] ?? [])
            ->map(fn (array $row) => [
                'email' => $row['email'] ?? '',
                'diskused' => $row['diskused'] ?? null,
                'diskquota' => $row['diskquota'] ?? null,
            ])
            ->values()
            ->all();

        return [
            'success' => true,
            'emails' => $emails,
        ];
    }

    public function changeEmailPassword(Server $server, Domain $domain, string $email, string $password): array
    {
        $localPart = str_contains($email, '@') ? explode('@', $email, 2)[0] : $email;

        return $this->uapiRequest($server, $domain, 'Email', 'passwdpop', [
            'email' => $localPart,
            'domain' => $domain->domain,
            'password' => $password,
        ], expectData: false);
    }

    public function getMxRecords(Server $server, Domain $domain): array
    {
        $result = $this->uapiRequest($server, $domain, 'DNS', 'parse_zone', [
            'zone' => $domain->domain,
        ]);

        if (! $result['success'])
        {
            return $result;
        }

        $records = collect($result['data'] ?? [])
            ->filter(fn (array $record) => strtoupper((string) ($record['record_type'] ?? $record['type'] ?? '')) === 'MX')
            ->map(fn (array $record) => [
                'line' => $record['line'] ?? null,
                'priority' => (int) ($record['preference'] ?? $record['priority'] ?? 0),
                'target' => rtrim((string) ($record['destination'] ?? $record['target'] ?? ''), '.'),
            ])
            ->values()
            ->all();

        return [
            'success' => true,
            'records' => $records,
        ];
    }

    public function updateMxRecords(Server $server, Domain $domain, array $records): array
    {
        $existing = $this->getMxRecords($server, $domain);

        if (! $existing['success'])
        {
            return $existing;
        }

        $edits = [];

        foreach ($existing['records'] as $record)
        {
            if ($record['line'] === null)
            {
                continue;
            }

            $edits[] = [
                'action' => 'remove',
                'line' => $record['line'],
            ];
        }

        foreach ($records as $record)
        {
            $edits[] = [
                'action' => 'add',
                'record_type' => 'MX',
                'dname' => $domain->domain.'.',
                'ttl' => 14400,
                'preference' => (int) $record['priority'],
                'exchange' => rtrim((string) $record['target'], '.').'.',
            ];
        }

        return $this->uapiRequest($server, $domain, 'DNS', 'mass_edit_zone', [
            'zone' => $domain->domain,
            'serial' => time(),
            'edit' => $edits,
        ], expectData: false);
    }

    /**
     * @return array{success: bool, error?: string, domains?: Collection<int, array<string, mixed>>}
     */
    private function listAccountFromCpanelUser(Server $server): array
    {
        $result = $this->accountUapiRequest($server, 'Variables', 'get_user_information');

        if (! $result['success'])
        {
            return $result;
        }

        $info = $result['data'] ?? [];

        return [
            'success' => true,
            'domains' => collect([
                [
                    'domain' => $info['domain'] ?? $server->server_url,
                    'user' => $info['user'] ?? $server->username,
                    'plan' => $info['plan'] ?? null,
                    'suspended' => false,
                    'disk_used' => 0,
                    'disk_limit' => 0,
                    'email' => $info['contact_email'] ?? null,
                    'ip' => $info['ip'] ?? null,
                ],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $account
     * @return array<string, mixed>
     */
    private function mapAccount(array $account): array
    {
        return [
            'domain' => $account['domain'],
            'user' => $account['user'],
            'plan' => $account['plan'] ?? $account['owner'] ?? null,
            'suspended' => (bool) ($account['suspended'] ?? false),
            'disk_used' => $account['diskused'] ?? 0,
            'disk_limit' => $account['disklimit'] ?? 0,
            'email' => $account['email'] ?? null,
            'ip' => $account['ip'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function whmRequest(Server $server, string $path, array $query = []): \Illuminate\Http\Client\Response
    {
        $url = $this->whmBaseUrl($server).$path;

        return Http::withHeaders([
            'Authorization' => $server->getWhmAuthHeader(),
        ])
            ->timeout(30)
            ->get($url, $query);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{success: bool, error?: string, data?: array<string, mixed>|array<int, mixed>}
     */
    private function accountUapiRequest(
        Server $server,
        string $module,
        string $function,
        array $params = [],
        bool $expectData = true,
    ): array {
        if (! $server->hasToken())
        {
            return [
                'success' => false,
                'error' => 'Server password is not configured',
            ];
        }

        try
        {
            $response = Http::withBasicAuth($server->username, $server->getDecryptedToken())
                ->timeout(30)
                ->get($this->cpanelBaseUrl($server).'/execute/'.$module.'/'.$function, $params);

            if (! $response->successful())
            {
                return [
                    'success' => false,
                    'error' => 'API request failed: '.$response->body(),
                ];
            }

            $payload = $response->json();

            if ((int) ($payload['status'] ?? 0) !== 1)
            {
                $errors = $payload['errors'] ?? $payload['messages'] ?? 'Unknown cPanel API error';
                $message = is_array($errors) ? implode(', ', $errors) : (string) $errors;

                return [
                    'success' => false,
                    'error' => $message,
                ];
            }

            if (! $expectData)
            {
                return ['success' => true];
            }

            $data = $payload['data'] ?? [];

            return [
                'success' => true,
                'data' => is_array($data) ? $data : [],
            ];
        } catch (\Exception $e)
        {
            return [
                'success' => false,
                'error' => 'Connection error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{success: bool, error?: string, data?: array<int, mixed>}
     */
    private function uapiRequest(
        Server $server,
        Domain $domain,
        string $module,
        string $function,
        array $params = [],
        bool $expectData = true,
    ): array {
        if ($server->usesCpanelAccountAuth())
        {
            $result = $this->accountUapiRequest($server, $module, $function, $params, $expectData);

            if (! $expectData || ! $result['success'])
            {
                return $result;
            }

            return [
                'success' => true,
                'data' => isset($result['data']) && array_is_list($result['data']) ? $result['data'] : [],
            ];
        }

        if (! $this->supports($server) || ! $server->hasToken())
        {
            return [
                'success' => false,
                'error' => 'Server is not configured for cPanel or missing token',
            ];
        }

        try
        {
            $query = array_merge([
                'cpanel_jsonapi_user' => $domain->username,
                'cpanel_jsonapi_apiversion' => 3,
                'cpanel_jsonapi_module' => $module,
                'cpanel_jsonapi_func' => $function,
            ], $params);

            $response = $this->whmRequest($server, '/json-api/cpanel', $query);

            if (! $response->successful())
            {
                return [
                    'success' => false,
                    'error' => 'API request failed: '.$response->body(),
                ];
            }

            $payload = $response->json();
            $result = $payload['result'] ?? $payload['cpanelresult'] ?? $payload;
            $errors = $result['errors'] ?? null;

            if (! empty($errors))
            {
                $message = is_array($errors) ? implode(', ', $errors) : (string) $errors;

                return [
                    'success' => false,
                    'error' => $message,
                ];
            }

            $status = $result['status'] ?? $result['event']['result'] ?? 1;

            if ((int) $status !== 1 && isset($result['error']))
            {
                return [
                    'success' => false,
                    'error' => (string) $result['error'],
                ];
            }

            if (! $expectData)
            {
                return ['success' => true];
            }

            return [
                'success' => true,
                'data' => $result['data'] ?? [],
            ];
        } catch (\Exception $e)
        {
            Log::error('cPanel UAPI request failed: '.$e->getMessage(), [
                'server_id' => $server->id,
                'domain' => $domain->domain,
                'module' => $module,
                'function' => $function,
            ]);

            return [
                'success' => false,
                'error' => 'Connection error: '.$e->getMessage(),
            ];
        }
    }

    private function whmBaseUrl(Server $server): string
    {
        return 'https://'.$server->server_url.':'.self::WHM_PORT;
    }

    private function cpanelBaseUrl(Server $server): string
    {
        return 'https://'.$server->server_url.':'.self::CPANEL_PORT;
    }
}
