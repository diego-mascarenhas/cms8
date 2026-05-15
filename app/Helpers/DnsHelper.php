<?php

namespace App\Helpers;

class DnsHelper
{
    /**
     * SPF mechanism that must appear on the apex SPF record or inside a followed {@code include:} chain.
     */
    public const REVISION_ALPHA_SPF_INCLUDE = 'include:spf.revisionalpha.com';

    /**
     * Example minimal SPF (shown in UI / help); the live record may add other mechanisms.
     */
    public const REQUIRED_REVISION_ALPHA_SPF_TXT = 'v=spf1 include:spf.revisionalpha.com -all';

    /**
     * Whether this SPF string contains the Revision Alpha include (case-insensitive).
     */
    public static function spfIncludesRevisionAlpha(string $spfRecord): bool
    {
        return stripos($spfRecord, self::REVISION_ALPHA_SPF_INCLUDE) !== false;
    }

    /**
     * @param  list<string>  $checkedDomains
     * @return array{ok: bool, includes_checked: list<string>}
     */
    private static function spfIncludesRevisionAlphaRecursive(string $spfRecord, array $checkedDomains, int $depth): array
    {
        if ($depth > 5)
        {
            return ['ok' => false, 'includes_checked' => $checkedDomains];
        }

        if (self::spfIncludesRevisionAlpha($spfRecord))
        {
            return ['ok' => true, 'includes_checked' => $checkedDomains];
        }

        preg_match_all('/include:([^\s]+)/i', $spfRecord, $matches);

        if (empty($matches[1]))
        {
            return ['ok' => false, 'includes_checked' => $checkedDomains];
        }

        foreach ($matches[1] as $includeDomain)
        {
            if (in_array($includeDomain, $checkedDomains, true))
            {
                continue;
            }

            $checkedDomains[] = $includeDomain;

            try
            {
                $includeTxtRecords = dns_get_record($includeDomain, DNS_TXT);

                if (! $includeTxtRecords)
                {
                    continue;
                }

                foreach ($includeTxtRecords as $includeRecord)
                {
                    $includeTxt = trim($includeRecord['txt'] ?? '');

                    if (stripos($includeTxt, 'v=spf1') !== 0)
                    {
                        continue;
                    }

                    $result = self::spfIncludesRevisionAlphaRecursive($includeTxt, $checkedDomains, $depth + 1);
                    $checkedDomains = $result['includes_checked'];

                    if ($result['ok'])
                    {
                        return ['ok' => true, 'includes_checked' => $checkedDomains];
                    }

                    break;
                }
            } catch (\Exception $e)
            {
                continue;
            }
        }

        return ['ok' => false, 'includes_checked' => $checkedDomains];
    }

    /**
     * Check SPF on the domain apex: must include {@see REVISION_ALPHA_SPF_INCLUDE} (directly or via {@code include:} chain).
     *
     * @return array{
     *     exists: bool,
     *     has_mailbaby: bool,
     *     record: string|null,
     *     error: string|null,
     *     includes_checked: list<string>
     * }
     */
    public static function checkSpfRecord($domain)
    {
        try
        {
            $txtRecords = dns_get_record($domain, DNS_TXT);

            if (! $txtRecords)
            {
                return [
                    'exists' => false,
                    'has_mailbaby' => false,
                    'record' => null,
                    'error' => 'No TXT records found',
                    'includes_checked' => [],
                ];
            }

            $spfFound = false;
            $firstSpfRecord = null;
            $lastIncludesChecked = [];

            foreach ($txtRecords as $record)
            {
                $txt = trim($record['txt'] ?? '');

                if (stripos($txt, 'v=spf1') !== 0)
                {
                    continue;
                }

                $spfFound = true;

                if ($firstSpfRecord === null)
                {
                    $firstSpfRecord = $txt;
                }

                $result = self::spfIncludesRevisionAlphaRecursive($txt, [], 0);
                $lastIncludesChecked = $result['includes_checked'];

                if ($result['ok'])
                {
                    return [
                        'exists' => true,
                        'has_mailbaby' => true,
                        'record' => $txt,
                        'error' => null,
                        'includes_checked' => $result['includes_checked'],
                    ];
                }
            }

            if ($spfFound)
            {
                return [
                    'exists' => true,
                    'has_mailbaby' => false,
                    'record' => $firstSpfRecord,
                    'error' => __('app.email_spf_record_required_include'),
                    'includes_checked' => $lastIncludesChecked,
                ];
            }

            return [
                'exists' => false,
                'has_mailbaby' => false,
                'record' => null,
                'error' => 'No SPF record found',
                'includes_checked' => [],
            ];
        } catch (\Exception $e)
        {
            return [
                'exists' => false,
                'has_mailbaby' => false,
                'record' => null,
                'error' => $e->getMessage(),
                'includes_checked' => [],
            ];
        }
    }

    /**
     * Get domain from email address
     */
    public static function getDomainFromEmail($email)
    {
        $parts = explode('@', $email);

        return isset($parts[1]) ? strtolower($parts[1]) : null;
    }

    /**
     * Check SPF for the domain of an outgoing From address.
     *
     * @return array{domain: string|null, spf: array<string, mixed>}
     */
    public static function checkEmailDomainConfiguration(string $email): array
    {
        $domain = self::getDomainFromEmail($email);

        if (! $domain)
        {
            return [
                'domain' => null,
                'spf' => ['exists' => false, 'error' => 'Invalid email format'],
            ];
        }

        return [
            'domain' => $domain,
            'spf' => self::checkSpfRecord($domain),
        ];
    }

    /**
     * DNS (SPF) for the current team's outgoing From address (same logic as message detail).
     *
     * @return array<string, mixed>|null
     */
    public static function outgoingDnsStatusForAuthUser(?\Illuminate\Contracts\Auth\Authenticatable $user): ?array
    {
        if ($user === null || ! method_exists($user, 'currentTeam'))
        {
            return null;
        }

        /** @var \App\Models\User $user */
        $team = $user->currentTeam;

        if ($team === null)
        {
            return null;
        }

        if (! $team->relationLoaded('settings'))
        {
            $team->load('settings');
        }

        $emailConfig = $team->getOutgoingEmailConfig();

        if (empty($emailConfig['from_address']))
        {
            return null;
        }

        return self::checkEmailDomainConfiguration((string) $emailConfig['from_address']);
    }

    /**
     * Whether the broadcast "Send now" UI may proceed: own SMTP, or SPF includes Revision Alpha.
     * In the local environment, SPF checks are skipped so dev mail (e.g. Mailpit) works.
     *
     * @param  bool|null  $treatAsLocal  For tests: force local bypass; null uses {@see \Illuminate\Foundation\Application::isLocal()}.
     */
    public static function canSendBroadcastFromUi(?array $dnsStatus, bool $usingSystemSmtp, ?bool $treatAsLocal = null): bool
    {
        if ($treatAsLocal ?? app()->isLocal())
        {
            return true;
        }

        $spfOk = is_array($dnsStatus) && ($dnsStatus['spf']['has_mailbaby'] ?? false);

        return ! $usingSystemSmtp || $spfOk;
    }
}
