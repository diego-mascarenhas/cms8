<?php

namespace App\Support;

class MailExchange
{
    /**
     * MX targets applied when the company mail stays outside the hosting.
     *
     * @return list<array{priority: int, target: string}>
     */
    public static function records(string $provider, string $exchange = ''): array
    {
        $provider = strtolower(trim($provider));

        if ($provider === 'google')
        {
            return [
                ['priority' => 1, 'target' => 'aspmx.l.google.com'],
                ['priority' => 5, 'target' => 'alt1.aspmx.l.google.com'],
                ['priority' => 5, 'target' => 'alt2.aspmx.l.google.com'],
                ['priority' => 10, 'target' => 'alt3.aspmx.l.google.com'],
                ['priority' => 10, 'target' => 'alt4.aspmx.l.google.com'],
            ];
        }

        if ($provider === 'outlook' || $provider === 'other')
        {
            $host = strtolower(rtrim(trim($exchange), '.'));

            if ($host === '')
            {
                return [];
            }

            return [
                ['priority' => 0, 'target' => $host],
            ];
        }

        return [];
    }
}
