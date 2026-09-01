<?php

namespace App\Support;

class BudgetPreviewUrl
{
    public static function forToken(string $token): ?string
    {
        $token = trim($token);
        if ($token === '')
        {
            return null;
        }

        $base = rtrim((string) config('projects.budget_preview_base_url'), '/');
        if ($base !== '')
        {
            return $base.'/p/budget/'.rawurlencode($token);
        }

        return route('project.budget-preview', $token, true);
    }

    /**
     * @return array{preview_url: string|null, download_url: string|null}
     */
    public static function pair(string $token): array
    {
        $previewUrl = self::forToken($token);

        return [
            'preview_url' => $previewUrl,
            'download_url' => $previewUrl ? $previewUrl.'?download=1' : null,
        ];
    }
}
