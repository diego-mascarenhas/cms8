<?php

namespace App\Services\Mail;

class EmailDomainDns
{
    public static function domain(string $email): ?string
    {
        $email = strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false)
        {
            return null;
        }

        $domain = substr($email, (int) strrpos($email, '@') + 1);
        if ($domain === '')
        {
            return null;
        }

        if (function_exists('idn_to_ascii'))
        {
            $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($ascii) && $ascii !== '')
            {
                $domain = strtolower($ascii);
            }
        }

        return $domain;
    }

    public function domainAcceptsMail(string $domain): bool
    {
        return $this->hasRecord($domain, 'MX');
    }

    protected function hasRecord(string $domain, string $type): bool
    {
        return checkdnsrr($domain, $type);
    }
}
