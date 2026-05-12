<?php

namespace App\Support;

use Illuminate\Http\Request;

final class TemplateEditorReturnUrl
{
    /**
     * Accept only same-origin targets: relative paths starting with "/" (not "//"),
     * or absolute URLs whose host matches the current request host.
     */
    public static function validatedFromRequest(Request $request): ?string
    {
        $candidate = $request->query('return_url');

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
}
