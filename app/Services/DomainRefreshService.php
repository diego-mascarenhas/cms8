<?php

namespace App\Services;

use App\Helpers\DnsHelper;
use App\Models\Domain;
use App\Services\ControlPanel\ControlPanelManager;
use App\Services\ControlPanel\CpanelConnector;
use Illuminate\Support\Facades\Log;

class DomainRefreshService
{
    public function __construct(
        private ControlPanelManager $controlPanelManager,
        private WHMService $whmService,
        private DomainInfoService $domainInfoService,
    ) {}

    /**
     * @return array{success: bool, error?: string}
     */
    public function refresh(Domain $domain): array
    {
        $domain->load('server');

        if (! $domain->server)
        {
            return [
                'success' => false,
                'error' => 'El dominio no tiene un servidor asociado.',
            ];
        }

        try
        {
            $data = $domain->data ?? [];
            $controlPanelError = null;
            $accountIsSuspended = $domain->suspended;
            $emailAccounts = $data['email_accounts'] ?? [];
            $mxRecords = $data['mx_records'] ?? [];
            $availablePlans = $data['available_plans'] ?? [];
            $accountDisk = $data['account_disk'] ?? null;

            if ($domain->server->control_panel === 'cpanel' && $domain->server->hasToken())
            {
                $whmResult = $this->whmService->getDomainsFromServer($domain->server);

                if ($whmResult['success'])
                {
                    $account = collect($whmResult['domains'] ?? [])->firstWhere('domain', $domain->domain);

                    if ($account)
                    {
                        $domain->username = $account['user'] ?? $domain->username;
                        $domain->plan = $account['plan'] ?? $domain->plan;
                        $domain->suspended = (bool) ($account['suspended'] ?? $domain->suspended);
                        $data = array_merge($data, $account);
                        $accountIsSuspended = $domain->suspended;
                    }
                }

                if (! $accountIsSuspended)
                {
                    $connector = $this->controlPanelManager->forServer($domain->server);

                    $plansResult = $connector->listPlans($domain->server);
                    $emailsResult = $connector->listEmailAccounts($domain->server, $domain);
                    $mxResult = $connector->getMxRecords($domain->server, $domain);
                    $diskResult = $connector->getAccountDiskUsage($domain->server, $domain);

                    $availablePlans = $plansResult['plans'] ?? $availablePlans;
                    $emailAccounts = $emailsResult['emails'] ?? $emailAccounts;
                    $mxRecords = $mxResult['records'] ?? $mxRecords;
                    $accountDisk = ($diskResult['success'] ?? false) ? $diskResult : $accountDisk;

                    $apiErrors = array_filter([
                        $plansResult['error'] ?? null,
                        $emailsResult['error'] ?? null,
                        $mxResult['error'] ?? null,
                        $diskResult['error'] ?? null,
                    ]);

                    foreach ($apiErrors as $apiError)
                    {
                        if (CpanelConnector::isSuspendedAccountError($apiError))
                        {
                            $accountIsSuspended = true;
                            $domain->suspended = true;
                            break;
                        }
                    }

                    if ($accountIsSuspended)
                    {
                        $emailAccounts = [];
                        $mxRecords = [];
                        $accountDisk = null;
                        $controlPanelError = null;
                    } elseif (! ($plansResult['success'] ?? true))
                    {
                        $controlPanelError = $plansResult['error'] ?? null;
                    } elseif (! ($emailsResult['success'] ?? true))
                    {
                        $controlPanelError = $emailsResult['error'] ?? null;
                    } elseif (! ($mxResult['success'] ?? true))
                    {
                        $controlPanelError = $mxResult['error'] ?? null;
                    }
                }
            }

            $domainInfo = $this->domainInfoService->getDomainInfo($domain->domain);
            $nameservers = [];

            foreach ($domainInfo['dns_records']['ns'] ?? [] as $record)
            {
                if (! empty($record['target']))
                {
                    $nameservers[] = rtrim((string) $record['target'], '.');
                }
            }

            if ($nameservers === [])
            {
                $nameservers = $domain->server->getProvisioningNameservers();
            }

            $domain->update([
                'username' => $domain->username,
                'plan' => $domain->plan,
                'suspended' => $domain->suspended,
                'data' => array_merge($data, $domainInfo, [
                    'email_accounts' => $emailAccounts,
                    'mx_records' => $mxRecords,
                    'available_plans' => $availablePlans,
                    'account_disk' => is_array($accountDisk) ? [
                        'used_mb' => $accountDisk['used_mb'] ?? 0,
                        'limit_mb' => $accountDisk['limit_mb'] ?? null,
                        'unlimited' => $accountDisk['unlimited'] ?? false,
                        'usage_percent' => $accountDisk['usage_percent'] ?? 0,
                    ] : null,
                    'public_spf_check' => DnsHelper::checkSpfRecord($domain->domain),
                    'nameservers' => $nameservers,
                    'control_panel_error' => $controlPanelError,
                    'last_refreshed' => now()->toIso8601String(),
                ]),
            ]);

            $domain->updatePhpVersion();

            return ['success' => true];
        } catch (\Throwable $e)
        {
            Log::error('Error refreshing domain data: '.$e->getMessage(), [
                'domain_id' => $domain->id,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
