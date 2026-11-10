<?php

namespace App\Support;

use App\Helpers\DnsHelper;
use App\Models\Domain;

class HostingAccountPresenter
{
    /**
     * Cached cPanel account shown on the hosting detail page.
     *
     * @return array<string, mixed>
     */
    public static function forDomain(Domain $domain, bool $autoSuspend = false): array
    {
        $display = $domain->getCachedDisplayInfo();
        $server = $domain->server;
        $requiredNameservers = $server?->getProvisioningNameservers()
            ?: config('humano_hosting.default_nameservers', []);
        $currentNameservers = $display['nameservers'] ?? [];
        $spf = $domain->getCachedPublicSpfCheck();
        $ssl = is_array($display['ssl_status'] ?? null) ? $display['ssl_status'] : null;

        return [
            'id' => $domain->id,
            'domain' => $domain->domain,
            'suspended' => (bool) $domain->suspended,
            'needs_update' => (bool) $domain->needs_update,
            'is_working' => (bool) $domain->is_working,
            'username' => $domain->username,
            'plan' => $domain->plan,
            'site_type' => $domain->site_type,
            'php_version' => $domain->php_version,
            'notes' => $domain->notes,
            'contact_email' => data_get($domain->data, 'email'),
            'auto_suspend' => $autoSuspend,
            'web_ip' => $display['web_ip'] ?? null,
            'mail_ip' => $display['mail_ip'] ?? null,
            'server' => $server?->server_url,
            'control_panel' => $server?->control_panel,
            'cpanel_url' => $server?->getCpanelUrl(),
            'webmail_url' => $server?->getWebmailUrl(),
            'disk' => self::disk($domain),
            'ssl' => [
                'valid' => ($ssl['valid'] ?? false) === true,
                'expires' => $ssl['expires'] ?? null,
            ],
            'emails' => array_values($domain->getCachedEmailAccounts()),
            'mx_records' => array_values($domain->getCachedMxRecords()),
            'nameservers' => [
                'current' => array_values($currentNameservers),
                'required' => array_values($requiredNameservers),
                'match' => self::nameserversMatch($currentNameservers, $requiredNameservers),
            ],
            'spf' => [
                'exists' => (bool) ($spf['exists'] ?? false),
                'has_mailbaby' => (bool) ($spf['has_mailbaby'] ?? false),
                'record' => $spf['record'] ?? null,
                'recommended' => $server?->getProvisioningSpfRecord() ?: DnsHelper::REQUIRED_REVISION_ALPHA_SPF_TXT,
            ],
            'updated_at' => $domain->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{used_mb: float, limit_mb: float|null, unlimited: bool, usage_percent: int}|null
     */
    private static function disk(Domain $domain): ?array
    {
        $cached = $domain->getCachedAccountDisk();
        if ($cached !== null)
        {
            return $cached;
        }

        $used = self::megabytes(data_get($domain->data, 'disk_used'));
        $limitRaw = data_get($domain->data, 'disk_limit');
        if ($used === null && ($limitRaw === null || $limitRaw === ''))
        {
            return null;
        }

        $unlimited = is_string($limitRaw) && strtolower(trim($limitRaw)) === 'unlimited';
        $limit = $unlimited ? null : self::megabytes($limitRaw);
        $percent = ! $unlimited && $limit
            ? (int) min(100, round(($used ?? 0) / $limit * 100))
            : 0;

        return [
            'used_mb' => round($used ?? 0, 1),
            'limit_mb' => $limit === null ? null : round($limit, 1),
            'unlimited' => $unlimited,
            'usage_percent' => $percent,
        ];
    }

    private static function megabytes(mixed $value): ?float
    {
        if (! is_string($value) && ! is_numeric($value))
        {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '' || strtolower($value) === 'unlimited')
        {
            return null;
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*([KMGT])?/i', $value, $matches) !== 1)
        {
            return null;
        }

        $amount = (float) $matches[1];

        return match (strtoupper($matches[2] ?? 'M'))
        {
            'K' => $amount / 1024,
            'G' => $amount * 1024,
            'T' => $amount * 1024 * 1024,
            default => $amount,
        };
    }

    /**
     * @param  array<int, string>  $current
     * @param  array<int, string>  $required
     */
    private static function nameserversMatch(array $current, array $required): bool
    {
        if ($required === [] || $current === [])
        {
            return false;
        }

        $normalize = static fn (array $nameservers): array => collect($nameservers)
            ->map(fn ($nameserver) => strtolower(rtrim((string) $nameserver, '.')))
            ->sort()
            ->values()
            ->all();

        return $normalize($current) === $normalize($required);
    }
}
