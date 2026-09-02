<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Http\Request;

class BudgetPreviewUrl
{
    public static function forToken(string $token, ?Project $project = null): ?string
    {
        $token = trim($token);
        if ($token === '')
        {
            return null;
        }

        $base = self::frontendBase($project);
        if ($base !== null)
        {
            return $base.'/p/budget/'.rawurlencode($token);
        }

        return route('project.budget-preview', $token, true);
    }

    /**
     * @return array{preview_url: string|null, download_url: string|null}
     */
    public static function pair(string $token, ?Project $project = null): array
    {
        if ($project instanceof Project)
        {
            self::rememberFromRequest($project);
        }

        $previewUrl = self::forToken($token, $project);

        return [
            'preview_url' => $previewUrl,
            'download_url' => $previewUrl ? $previewUrl.'?download=1' : null,
        ];
    }

    public static function frontendBase(?Project $project = null): ?string
    {
        $stored = self::normalizeOrigin((string) data_get($project?->data, 'budget_preview_base_url'));
        if ($stored !== null && self::isAllowedOrigin($stored))
        {
            return $stored;
        }

        $fromRequest = self::originFromRequest(request());
        if ($fromRequest !== null)
        {
            return $fromRequest;
        }

        $configured = self::normalizeOrigin((string) config('projects.budget_preview_base_url'));
        if ($configured !== null && self::isAllowedOrigin($configured))
        {
            return $configured;
        }

        return null;
    }

    public static function rememberFromRequest(Project $project, ?Request $request = null): void
    {
        $origin = self::originFromRequest($request ?? request());
        if ($origin === null)
        {
            return;
        }

        $current = self::normalizeOrigin((string) data_get($project->data, 'budget_preview_base_url'));
        if ($current === $origin)
        {
            return;
        }

        $data = is_array($project->data) ? $project->data : [];
        $data['budget_preview_base_url'] = $origin;
        $project->forceFill(['data' => $data])->save();
    }

    public static function originFromRequest(?Request $request): ?string
    {
        if (! $request instanceof Request)
        {
            return null;
        }

        foreach ([$request->headers->get('Origin'), $request->headers->get('Referer')] as $candidate)
        {
            $origin = self::normalizeOrigin((string) $candidate);
            if ($origin !== null && self::isAllowedOrigin($origin))
            {
                return $origin;
            }
        }

        return null;
    }

    public static function normalizeOrigin(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '')
        {
            return null;
        }

        $parts = parse_url($value);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '')
        {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port;
    }

    public static function isAllowedOrigin(string $origin): bool
    {
        $normalized = self::normalizeOrigin($origin);
        if ($normalized === null)
        {
            return false;
        }

        $appOrigin = self::normalizeOrigin((string) config('app.url'));
        if ($appOrigin !== null && $normalized === $appOrigin)
        {
            return false;
        }

        foreach (self::configuredOrigins() as $allowed)
        {
            if ($normalized === $allowed)
            {
                return true;
            }
        }

        $host = (string) parse_url($normalized, PHP_URL_HOST);

        return $host === 'localhost'
            || $host === '127.0.0.1'
            || str_ends_with($host, '.humano.app')
            || str_ends_with($host, '.idoneo.dev');
    }

    /**
     * @return list<string>
     */
    private static function configuredOrigins(): array
    {
        $origins = [];

        foreach ([(string) config('projects.budget_preview_base_url'), ...self::extraAllowedOrigins()] as $candidate)
        {
            $normalized = self::normalizeOrigin($candidate);
            if ($normalized !== null)
            {
                $origins[] = $normalized;
            }
        }

        return array_values(array_unique($origins));
    }

    /**
     * @return list<string>
     */
    private static function extraAllowedOrigins(): array
    {
        $raw = config('projects.budget_preview_allowed_origins');
        if (is_array($raw))
        {
            return array_map(static fn ($value): string => (string) $value, $raw);
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
    }
}
