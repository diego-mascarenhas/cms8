<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\Http;
use Throwable;

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

    public function domainAcceptsMail(string $domain): ?bool
    {
        return $this->hasRecord($domain, 'MX');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function acceptsFromDnsJson(array $payload): ?bool
    {
        $status = $payload['Status'] ?? null;
        if (! is_int($status) && ! (is_string($status) && is_numeric($status)))
        {
            return null;
        }

        $status = (int) $status;
        if ($status === 2 || $status === 1)
        {
            return null;
        }

        if ($status === 3)
        {
            return false;
        }

        if ($status !== 0)
        {
            return null;
        }

        $answers = $payload['Answer'] ?? [];
        if (! is_array($answers))
        {
            return false;
        }

        $hasMx = false;
        foreach ($answers as $answer)
        {
            if (! is_array($answer) || (int) ($answer['type'] ?? 0) !== 15)
            {
                continue;
            }

            $data = strtolower(trim((string) ($answer['data'] ?? '')));
            if ($data === '0 .' || $data === '.')
            {
                return false;
            }

            if ($data !== '')
            {
                $hasMx = true;
            }
        }

        return $hasMx;
    }

    protected function hasRecord(string $domain, string $type): ?bool
    {
        if ($type !== 'MX' || $domain === '')
        {
            return null;
        }

        try
        {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->withHeaders(['Accept' => 'application/dns-json'])
                ->get('https://cloudflare-dns.com/dns-query', [
                    'name' => $domain,
                    'type' => 'MX',
                ]);
        } catch (Throwable)
        {
            return null;
        }

        if (! $response->successful())
        {
            return null;
        }

        $payload = $response->json();

        return is_array($payload) ? self::acceptsFromDnsJson($payload) : null;
    }
}
