<?php

namespace App\Services\ControlPanel;

use App\Contracts\ControlPanelConnector;
use App\Models\Domain;
use App\Models\Server;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            return $this->listPlansForCpanelAccount($server);
        }

        try
        {
            $response = $this->whmRequest($server, '/json-api/listpkgs', [
                'want' => 'creatable',
            ]);

            if (! $response->successful())
            {
                return [
                    'success' => false,
                    'error' => 'API request failed: '.$response->body(),
                ];
            }

            return [
                'success' => true,
                'plans' => $this->parseListPkgsResponse($response->json()),
                'reseller_limited' => false,
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
     * @return array{success: bool, error?: string, plans?: array<int, string>, reseller_limited?: bool}
     */
    private function listPlansForCpanelAccount(Server $server): array
    {
        try
        {
            $response = $this->whmBasicAuthRequest($server, '/json-api/listpkgs', [
                'want' => 'creatable',
            ]);

            if ($response->successful())
            {
                $plans = $this->parseListPkgsResponse($response->json());

                if ($plans !== [])
                {
                    return [
                        'success' => true,
                        'plans' => $plans,
                        'reseller_limited' => false,
                    ];
                }
            }
        } catch (\Exception $e)
        {
            Log::warning('Reseller WHM listpkgs failed, falling back to account plan: '.$e->getMessage(), [
                'server_id' => $server->id,
            ]);
        }

        $result = $this->accountUapiRequest($server, 'Variables', 'get_user_information');

        if (! $result['success'])
        {
            return $result;
        }

        $plan = $result['data']['plan'] ?? null;

        return [
            'success' => true,
            'plans' => $plan ? [$plan] : [],
            'reseller_limited' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function parseListPkgsResponse(array $payload): array
    {
        if (! empty($payload['package']))
        {
            return collect($payload['package'])
                ->pluck('name')
                ->filter()
                ->values()
                ->all();
        }

        if (! empty($payload['data']['pkg']))
        {
            return collect($payload['data']['pkg'])
                ->pluck('name')
                ->filter()
                ->values()
                ->all();
        }

        return [];
    }

    public function createAccount(
        Server $server,
        string $username,
        string $domain,
        string $plan,
        string $password,
        ?string $contactEmail = null,
    ): array {
        if (! $this->supports($server) || ! $server->hasToken())
        {
            return [
                'success' => false,
                'error' => 'Server is not configured for cPanel or missing token',
            ];
        }

        $params = [
            'username' => $username,
            'domain' => $domain,
            'plan' => $plan,
            'password' => $password,
        ];

        if ($contactEmail)
        {
            $params['contactemail'] = $contactEmail;
        }

        try
        {
            $response = $server->usesCpanelAccountAuth()
                ? $this->whmBasicAuthRequest($server, '/json-api/createacct', $params)
                : $this->whmRequest($server, '/json-api/createacct', $params);

            if (! $response->successful())
            {
                Log::warning('cPanel createacct HTTP error', [
                    'server_id' => $server->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $payload = $response->json();

                return [
                    'success' => false,
                    'error' => $this->extractWhmErrorMessage(
                        is_array($payload) ? $payload : [],
                        $response->body(),
                    ),
                ];
            }

            $payload = $response->json();

            if (! is_array($payload))
            {
                return [
                    'success' => false,
                    'error' => 'WHM returned an invalid response while creating the hosting account.',
                ];
            }

            if (! $this->isWhmJsonApiSuccessful($payload))
            {
                Log::warning('cPanel createacct rejected', [
                    'server_id' => $server->id,
                    'domain' => $domain,
                    'username' => $username,
                    'response' => $payload,
                ]);

                return [
                    'success' => false,
                    'error' => $this->extractWhmErrorMessage($payload),
                ];
            }

            return ['success' => true];
        } catch (\Exception $e)
        {
            Log::error('cPanel createacct failed: '.$e->getMessage(), [
                'server_id' => $server->id,
                'domain' => $domain,
                'username' => $username,
                'plan' => $plan,
            ]);

            return [
                'success' => false,
                'error' => 'Connection error: '.$e->getMessage(),
            ];
        }
    }

    public function ensureSpfRecord(Server $server, Domain $domain, string $spfRecord): array
    {
        if ($spfRecord === '')
        {
            return [
                'success' => false,
                'error' => 'SPF record is not configured for this server',
            ];
        }

        $existing = $this->getSpfTxtRecords($server, $domain);

        if (! $existing['success'])
        {
            return $existing;
        }

        foreach ($existing['records'] as $record)
        {
            $text = (string) ($record['text'] ?? '');

            if ($text !== '' && \App\Helpers\DnsHelper::spfIncludesRevisionAlpha($text))
            {
                return ['success' => true];
            }
        }

        $targetSpf = $spfRecord;

        if (($existing['records'][0]['text'] ?? '') !== '')
        {
            $targetSpf = $this->mergeSpfInclude((string) $existing['records'][0]['text'], $spfRecord);
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

        $edits[] = [
            'action' => 'add',
            'record_type' => 'TXT',
            'dname' => $domain->domain.'.',
            'ttl' => 14400,
            'txtdata' => $targetSpf,
        ];

        return $this->uapiRequest($server, $domain, 'DNS', 'mass_edit_zone', [
            'zone' => $domain->domain,
            'serial' => time(),
            'edit' => $edits,
        ], expectData: false);
    }

    /**
     * @return array{success: bool, error?: string, records?: array<int, array{line: int|null, text: string}>}
     */
    private function getSpfTxtRecords(Server $server, Domain $domain): array
    {
        $result = $this->uapiRequest($server, $domain, 'DNS', 'parse_zone', [
            'zone' => $domain->domain,
        ]);

        if (! $result['success'])
        {
            return $result;
        }

        $apex = strtolower(rtrim($domain->domain, '.')).'.';

        $records = collect($result['data'] ?? [])
            ->filter(function (array $record) use ($apex)
            {
                $type = strtoupper((string) ($record['record_type'] ?? $record['type'] ?? ''));
                $name = strtolower(rtrim((string) ($record['dname'] ?? $record['name'] ?? ''), '.').'.');

                if ($type !== 'TXT')
                {
                    return false;
                }

                return $name === $apex || $name === '@';
            })
            ->map(function (array $record)
            {
                $text = (string) ($record['txtdata'] ?? $record['record'] ?? $record['text'] ?? '');

                return [
                    'line' => $record['line'] ?? null,
                    'text' => trim($text, '"'),
                ];
            })
            ->filter(fn (array $record) => stripos($record['text'], 'v=spf1') === 0)
            ->values()
            ->all();

        return [
            'success' => true,
            'records' => $records,
        ];
    }

    private function mergeSpfInclude(string $existing, string $fallbackRecord): string
    {
        if (\App\Helpers\DnsHelper::spfIncludesRevisionAlpha($existing))
        {
            return $existing;
        }

        $include = \App\Helpers\DnsHelper::REVISION_ALPHA_SPF_INCLUDE;

        if (preg_match('/\s(~all|-all|\?all)\s*$/i', $existing, $matches, PREG_OFFSET_CAPTURE))
        {
            return substr($existing, 0, $matches[0][1]).' '.$include.' '.$matches[0][0];
        }

        return trim($existing).' '.$include.' -all';
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
    private function whmBasicAuthRequest(Server $server, string $path, array $query = []): \Illuminate\Http\Client\Response
    {
        $url = $this->whmBaseUrl($server).$path;

        return Http::withBasicAuth($server->username, (string) $server->getDecryptedToken())
            ->timeout(30)
            ->get($url, $query);
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
    private function whmCpanelUapiRequest(
        Server $server,
        string $cpanelUsername,
        string $module,
        string $function,
        array $params = [],
        bool $expectData = true,
    ): array {
        try
        {
            $query = array_merge([
                'cpanel_jsonapi_user' => $cpanelUsername,
                'cpanel_jsonapi_apiversion' => 3,
                'cpanel_jsonapi_module' => $module,
                'cpanel_jsonapi_func' => $function,
            ], $params);

            $response = $this->whmBasicAuthRequest($server, '/json-api/cpanel', $query);

            if (! $response->successful())
            {
                return [
                    'success' => false,
                    'error' => 'API request failed: '.$response->body(),
                ];
            }

            return $this->parseWhmCpanelUapiPayload($response->json(), $expectData);
        } catch (\Exception $e)
        {
            return [
                'success' => false,
                'error' => 'Connection error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, error?: string, data?: array<int, mixed>}
     */
    private function parseWhmCpanelUapiPayload(array $payload, bool $expectData): array
    {
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
            if ($domain->username === $server->username)
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

            return $this->whmCpanelUapiRequest(
                $server,
                $domain->username,
                $module,
                $function,
                $params,
                $expectData,
            );
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

            return $this->parseWhmCpanelUapiPayload($response->json(), $expectData);
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isWhmJsonApiSuccessful(array $payload): bool
    {
        if (isset($payload['metadata']['result']))
        {
            return (int) $payload['metadata']['result'] === 1;
        }

        $result = $payload['result'] ?? null;

        if (! is_array($result))
        {
            return false;
        }

        if (array_is_list($result))
        {
            if ($result === [])
            {
                return false;
            }

            foreach ($result as $item)
            {
                if (! is_array($item) || (int) ($item['status'] ?? 0) !== 1)
                {
                    return false;
                }
            }

            return true;
        }

        if (isset($result['status']))
        {
            return (int) $result['status'] === 1;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractWhmErrorMessage(array $payload, ?string $fallbackBody = null): string
    {
        $messages = [];

        $metadata = $payload['metadata'] ?? [];

        if (is_array($metadata))
        {
            if (! empty($metadata['reason']))
            {
                $messages[] = (string) $metadata['reason'];
            }

            $messages = array_merge($messages, $this->normalizeWhmOutputLines($metadata['output'] ?? null));
        }

        $result = $payload['result'] ?? null;

        if (is_array($result) && array_is_list($result))
        {
            foreach ($result as $item)
            {
                if (! is_array($item))
                {
                    continue;
                }

                if ((int) ($item['status'] ?? 0) !== 1 && ! empty($item['statusmsg']))
                {
                    $messages[] = (string) $item['statusmsg'];
                }

                $messages = array_merge($messages, $this->normalizeWhmOutputLines($item['rawout'] ?? null));
            }
        }

        $messages = array_merge($messages, $this->normalizeWhmOutputLines($payload['data']['output'] ?? null));

        foreach (['statusmsg', 'statusMsg', 'error'] as $key)
        {
            if (! empty($payload[$key]) && is_scalar($payload[$key]))
            {
                $messages[] = (string) $payload[$key];
            }
        }

        $cpanelResult = $payload['cpanelresult'] ?? null;

        if ($cpanelResult === null && is_array($result) && ! array_is_list($result))
        {
            $cpanelResult = $result;
        }

        if (is_array($cpanelResult))
        {
            if (! empty($cpanelResult['statusmsg']) && is_scalar($cpanelResult['statusmsg']))
            {
                $messages[] = (string) $cpanelResult['statusmsg'];
            }

            if (! empty($cpanelResult['error']) && is_scalar($cpanelResult['error']))
            {
                $messages[] = (string) $cpanelResult['error'];
            }

            if (! empty($cpanelResult['errors']))
            {
                $messages = array_merge(
                    $messages,
                    $this->normalizeWhmOutputLines($cpanelResult['errors']),
                );
            }

            $messages = array_merge($messages, $this->normalizeWhmOutputLines($cpanelResult['data'] ?? null));
        }

        $message = trim(implode("\n", array_unique(array_filter(array_map(
            fn (string $line) => trim(strip_tags($line)),
            $messages,
        )))));

        if ($message !== '')
        {
            return $message;
        }

        if ($fallbackBody !== null && $fallbackBody !== '')
        {
            $decoded = json_decode($fallbackBody, true);

            if (is_array($decoded))
            {
                return $this->extractWhmErrorMessage($decoded);
            }

            return Str::limit(trim(strip_tags($fallbackBody)), 500);
        }

        return 'WHM could not create the hosting account.';
    }

    /**
     * @return array<int, string>
     */
    private function normalizeWhmOutputLines(mixed $output): array
    {
        if (is_string($output))
        {
            return $output !== '' ? [trim($output)] : [];
        }

        if (! is_array($output))
        {
            return [];
        }

        if (isset($output['raw']))
        {
            return $this->normalizeWhmOutputLines($output['raw']);
        }

        $lines = [];

        foreach ($output as $line)
        {
            if (is_scalar($line))
            {
                $line = trim((string) $line);

                if ($line !== '')
                {
                    $lines[] = $line;
                }
            }
        }

        return $lines;
    }

    private function cpanelBaseUrl(Server $server): string
    {
        return 'https://'.$server->server_url.':'.self::CPANEL_PORT;
    }
}
