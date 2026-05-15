<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class TemplateEditorReturnUrl
{
    /**
     * Accept only same-origin targets: relative paths starting with "/" (not "//"),
     * or absolute URLs whose host matches the current request host.
     */
    public static function validatedFromRequest(Request $request): ?string
    {
        return self::validatedCandidate($request, $request->query('return_url'));
    }

    /**
     * Same rules as the editor query string, for values supplied in POST bodies (e.g. template duplicate).
     */
    public static function validatedCandidate(Request $request, mixed $candidate): ?string
    {
        if (! is_string($candidate))
        {
            return null;
        }

        $candidate = trim($candidate);

        if ($candidate === '')
        {
            return null;
        }

        if (str_starts_with($candidate, '/') && ! str_starts_with($candidate, '//'))
        {
            return $candidate;
        }

        $parts = parse_url($candidate);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host']))
        {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https')
        {
            return null;
        }

        $requestHost = strtolower($request->getHost());
        $candidateHost = strtolower((string) $parts['host']);

        if ($candidateHost !== $requestHost)
        {
            return null;
        }

        return $candidate;
    }

    public static function editorRouteWithReturn(string $editorUrl, ?string $returnUrl): string
    {
        if ($returnUrl === null || $returnUrl === '')
        {
            return $editorUrl;
        }

        $sep = str_contains($editorUrl, '?') ? '&' : '?';

        return $editorUrl.$sep.http_build_query(['return_url' => $returnUrl]);
    }

    /**
     * When the return URL path matches $path (e.g. message create), merge query parameters into the URL.
     */
    public static function mergeQueryWhenPathMatches(?string $returnUrl, string $path, array $merge): ?string
    {
        if ($returnUrl === null || $returnUrl === '')
        {
            return $returnUrl;
        }

        $normalizedPath = self::normalizePath($path);
        $returnPath = self::normalizePath(parse_url($returnUrl, PHP_URL_PATH) ?? '');

        if ($returnPath !== $normalizedPath)
        {
            return $returnUrl;
        }

        $parts = parse_url($returnUrl);
        if ($parts === false)
        {
            return $returnUrl;
        }

        parse_str($parts['query'] ?? '', $query);
        foreach ($merge as $key => $value)
        {
            $query[$key] = $value;
        }

        $queryString = http_build_query($query);
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        if (! empty($parts['scheme']) && ! empty($parts['host']))
        {
            $port = isset($parts['port']) ? ':'.$parts['port'] : '';

            return $parts['scheme'].'://'.$parts['host'].$port.($parts['path'] ?? '').($queryString !== '' ? '?'.$queryString : '').$fragment;
        }

        return ($parts['path'] ?? '').($queryString !== '' ? '?'.$queryString : '').$fragment;
    }

    private static function normalizePath(string $path): string
    {
        $path = '/'.ltrim($path, '/');

        return Str::before($path, '?') ?: '/';
    }
}
