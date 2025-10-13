<?php

namespace App\Helpers;

class DnsHelper
{
    /**
     * Check SPF record for a domain
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

            foreach ($txtRecords as $record)
            {
                $txt = $record['txt'] ?? '';

                // Check if it's an SPF record
                if (strpos($txt, 'v=spf1') === 0)
                {
                    $result = self::checkSpfRecursive($txt, $domain);

                    return [
                        'exists' => true,
                        'has_mailbaby' => $result['has_mailbaby'],
                        'record' => $txt,
                        'error' => null,
                        'includes_checked' => $result['includes_checked'],
                    ];
                }
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
     * Recursively check SPF includes for MailBaby
     */
    private static function checkSpfRecursive($spfRecord, $originalDomain, $checkedDomains = [], $depth = 0)
    {
        // Prevent infinite recursion
        if ($depth > 5)
        {
            return ['has_mailbaby' => false, 'includes_checked' => $checkedDomains];
        }

        $includesChecked = $checkedDomains;

        // Check for REVISION ALPHA SPF includes
        $hasRevisionAlpha = (
            strpos($spfRecord, 'include:spf.revisionalpha.com') !== false ||
            strpos($spfRecord, 'include:mail.baby') !== false ||
            strpos($spfRecord, 'include:spf-c.mailbaby.net') !== false ||
            strpos($spfRecord, 'include:relay.mailbaby.net') !== false
        );

        if ($hasRevisionAlpha)
        {
            return ['has_mailbaby' => true, 'includes_checked' => $includesChecked];
        }

        // Find all include: directives
        preg_match_all('/include:([^\s]+)/', $spfRecord, $matches);

        if (! empty($matches[1]))
        {
            foreach ($matches[1] as $includeDomain)
            {
                // Skip if we've already checked this domain
                if (in_array($includeDomain, $checkedDomains))
                {
                    continue;
                }

                $includesChecked[] = $includeDomain;

                try
                {
                    $includeTxtRecords = dns_get_record($includeDomain, DNS_TXT);

                    if ($includeTxtRecords)
                    {
                        foreach ($includeTxtRecords as $includeRecord)
                        {
                            $includeTxt = $includeRecord['txt'] ?? '';

                            if (strpos($includeTxt, 'v=spf1') === 0)
                            {
                                $result = self::checkSpfRecursive(
                                    $includeTxt,
                                    $originalDomain,
                                    $includesChecked,
                                    $depth + 1,
                                );

                                $includesChecked = $result['includes_checked'];

                                if ($result['has_mailbaby'])
                                {
                                    return ['has_mailbaby' => true, 'includes_checked' => $includesChecked];
                                }
                                break;
                            }
                        }
                    }
                } catch (\Exception $e)
                {
                    // Continue checking other includes if one fails
                    continue;
                }
            }
        }

        return ['has_mailbaby' => false, 'includes_checked' => $includesChecked];
    }

    /**
     * Check MailBaby authorization record (_mb)
     */
    public static function checkMailBabyAuth($domain, $expectedUser = null)
    {
        try
        {
            // Special case: mail.baby domain doesn't need _mb record
            if (strtolower($domain) === 'mail.baby')
            {
                return [
                    'exists' => true,
                    'authorized' => true,
                    'users' => ['mail.baby'],
                    'record' => 'Native MailBaby domain',
                    'error' => null,
                ];
            }

            $mbDomain = '_mb.'.$domain;
            $txtRecords = dns_get_record($mbDomain, DNS_TXT);

            if (! $txtRecords)
            {
                return [
                    'exists' => false,
                    'authorized' => false,
                    'users' => [],
                    'record' => null,
                    'error' => 'No _mb TXT record found',
                ];
            }

            foreach ($txtRecords as $record)
            {
                $txt = $record['txt'] ?? '';

                // MailBaby auth records typically contain user IDs like "mb80474"
                if (preg_match_all('/mb\d+/', $txt, $matches))
                {
                    $users = $matches[0];
                    $authorized = $expectedUser ? in_array($expectedUser, $users) : ! empty($users);

                    return [
                        'exists' => true,
                        'authorized' => $authorized,
                        'users' => $users,
                        'record' => $txt,
                        'error' => null,
                    ];
                }
            }

            return [
                'exists' => true,
                'authorized' => false,
                'users' => [],
                'record' => $txtRecords[0]['txt'] ?? '',
                'error' => 'Invalid MailBaby auth record format',
            ];
        } catch (\Exception $e)
        {
            return [
                'exists' => false,
                'authorized' => false,
                'users' => [],
                'record' => null,
                'error' => $e->getMessage(),
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
     * Check both SPF and MailBaby auth for an email address
     */
    public static function checkEmailDomainConfiguration($email, $expectedMailBabyUser = null)
    {
        $domain = self::getDomainFromEmail($email);

        if (! $domain)
        {
            return [
                'domain' => null,
                'spf' => ['exists' => false, 'error' => 'Invalid email format'],
                'mailbaby_auth' => ['exists' => false, 'error' => 'Invalid email format'],
            ];
        }

        return [
            'domain' => $domain,
            'spf' => self::checkSpfRecord($domain),
            'mailbaby_auth' => self::checkMailBabyAuth($domain, $expectedMailBabyUser),
        ];
    }
}
