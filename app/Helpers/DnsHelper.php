<?php

namespace App\Helpers;

class DnsHelper
{
    /**
     * Required SPF TXT for domains sending via system SMTP (Revision Alpha).
     */
    public const REQUIRED_REVISION_ALPHA_SPF_TXT = 'v=spf1 include:spf.revisionalpha.com -all';

    /**
     * Whether a TXT record body matches {@see REQUIRED_REVISION_ALPHA_SPF_TXT} (case and inner whitespace insensitive).
     */
    public static function revisionAlphaSpfIsCanonical(string $spfTxt): bool
    {
        $trimmed = trim($spfTxt);

        return self::normalizeSpfForCompare($trimmed) === self::normalizeSpfForCompare(self::REQUIRED_REVISION_ALPHA_SPF_TXT);
    }

    private static function normalizeSpfForCompare(string $spf): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($spf)));
    }

    /**
     * Check SPF TXT on the domain apex: must be exactly the Revision Alpha record.
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

            foreach ($txtRecords as $record)
            {
                $txt = trim($record['txt'] ?? '');

                if (strpos($txt, 'v=spf1') !== 0)
                {
                    continue;
                }

                $spfFound = true;

                if ($firstSpfRecord === null)
                {
                    $firstSpfRecord = $txt;
                }

                if (self::revisionAlphaSpfIsCanonical($txt))
                {
                    return [
                        'exists' => true,
                        'has_mailbaby' => true,
                        'record' => $txt,
                        'error' => null,
                        'includes_checked' => [],
                    ];
                }
            }

            if ($spfFound)
            {
                return [
                    'exists' => true,
                    'has_mailbaby' => false,
                    'record' => $firstSpfRecord,
                    'error' => __('app.email_spf_record_required_exact'),
                    'includes_checked' => [],
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
     * Whether the broadcast "Send now" UI may proceed: own SMTP, or SPF authorizes the system provider.
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
