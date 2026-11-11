<?php

namespace App\Services\Hosting;

use App\Models\Domain;
use App\Services\ControlPanel\ControlPanelManager;

class DomainMailboxQuotaService
{
    public function __construct(private readonly ControlPanelManager $controlPanelManager) {}

    /**
     * @return array{success: bool, message: string}
     */
    public function increase(Domain $domain, string $email, ?int $quotaMb, bool $unlimited): array
    {
        $domain->loadMissing('server');
        $server = $domain->server;

        if ($server === null || $server->control_panel !== 'cpanel' || ! $server->hasToken())
        {
            return [
                'success' => false,
                'message' => 'El servidor no tiene credenciales configuradas para cambiar el espacio del correo.',
            ];
        }

        if ($domain->suspended)
        {
            return [
                'success' => false,
                'message' => 'No se puede cambiar el espacio de una cuenta suspendida.',
            ];
        }

        $current = $this->currentAccount($domain, $email);

        if ($current === null)
        {
            return [
                'success' => false,
                'message' => 'Esa cuenta no está en este dominio.',
            ];
        }

        if ($current['unlimited'] && $unlimited)
        {
            return [
                'success' => false,
                'message' => 'Esa cuenta ya tiene espacio ilimitado.',
            ];
        }

        $used = $current['used_mb'];

        if (! $unlimited)
        {
            if ($quotaMb === null)
            {
                return [
                    'success' => false,
                    'message' => 'Indicá la nueva cuota.',
                ];
            }

            if ($current['quota_mb'] !== null && $quotaMb <= $current['quota_mb'])
            {
                return [
                    'success' => false,
                    'message' => 'La nueva cuota tiene que ser mayor que la actual.',
                ];
            }

            if ($used !== null && $quotaMb <= $used)
            {
                return [
                    'success' => false,
                    'message' => 'La nueva cuota tiene que superar el espacio usado.',
                ];
            }
        }

        $result = $this->controlPanelManager
            ->forServer($server)
            ->changeEmailQuota($server, $domain, $email, $unlimited ? 0 : (int) $quotaMb);

        if (! ($result['success'] ?? false))
        {
            return [
                'success' => false,
                'message' => (string) ($result['error'] ?? 'No se pudo actualizar el espacio.'),
            ];
        }

        $this->rememberQuota($domain, $email, $unlimited ? null : $quotaMb, $unlimited);

        return [
            'success' => true,
            'message' => 'El espacio del correo quedó actualizado.',
        ];
    }

    /**
     * @return array{unlimited: bool, quota_mb: int|null, used_mb: float|null}|null
     */
    private function currentAccount(Domain $domain, string $email): ?array
    {
        foreach ($domain->getCachedEmailAccounts() as $account)
        {
            if (strtolower((string) ($account['email'] ?? '')) !== $email)
            {
                continue;
            }

            $quota = $account['diskquota_mb'] ?? null;

            return [
                'unlimited' => (bool) ($account['unlimited'] ?? false),
                'quota_mb' => is_numeric($quota) ? (int) $quota : null,
                'used_mb' => is_numeric($account['diskused_mb'] ?? null) ? (float) $account['diskused_mb'] : null,
            ];
        }

        return null;
    }

    private function rememberQuota(Domain $domain, string $email, ?int $quotaMb, bool $unlimited): void
    {
        $data = is_array($domain->data) ? $domain->data : [];
        $accounts = is_array($data['email_accounts'] ?? null) ? $data['email_accounts'] : [];

        foreach ($accounts as $index => $account)
        {
            if (! is_array($account) || strtolower((string) ($account['email'] ?? '')) !== $email)
            {
                continue;
            }

            $used = is_numeric($account['diskused_mb'] ?? null) ? (float) $account['diskused_mb'] : 0.0;
            $account['unlimited'] = $unlimited;
            $account['diskquota_mb'] = $unlimited ? null : $quotaMb;
            $account['diskquota'] = $unlimited ? 'unlimited' : $quotaMb;
            $account['usage_percent'] = (! $unlimited && $quotaMb) ? (int) min(100, round(($used / $quotaMb) * 100)) : null;
            $accounts[$index] = $account;
        }

        $data['email_accounts'] = $accounts;
        $domain->data = $data;
        $domain->save();
    }
}
