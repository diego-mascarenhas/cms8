<?php

namespace App\Support;

class CpanelUsername
{
    public static function suggestFromDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = (string) preg_replace('/^https?:\/\//', '', $domain);
        $domain = explode('/', $domain)[0] ?? $domain;
        $label = explode('.', $domain)[0] ?? $domain;

        $username = (string) preg_replace('/[^a-z0-9_]/', '', str_replace(['-', '.'], '', $label));

        if ($username === '' || ! ctype_alpha($username[0]))
        {
            $username = 'u'.preg_replace('/[^a-z0-9_]/', '', $label);
        }

        $username = substr($username, 0, 16);

        return $username !== '' ? $username : 'site';
    }

    public static function isValid(string $username): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_]{0,15}$/', $username);
    }
}
