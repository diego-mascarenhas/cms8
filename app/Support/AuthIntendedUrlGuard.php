<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Prevents non-HTML endpoints (e.g. QR image proxies) from becoming post-login redirects.
 */
final class AuthIntendedUrlGuard
{
    /**
     * @var list<string>
     */
    private const NON_PAGE_ROUTE_NAMES = [
        'chat.whatsapp-qr-image',
        'registration.onboarding.chat-link-qr-image',
    ];

    public static function shouldSkipStoringIntended(Request $request): bool
    {
        if (! $request->isMethod('GET') || $request->expectsJson())
        {
            return true;
        }

        $routeName = $request->route()?->getName();
        if ($routeName !== null && in_array($routeName, self::NON_PAGE_ROUTE_NAMES, true))
        {
            return true;
        }

        return self::pathLooksLikeNonPageAsset($request->path());
    }

    public static function sanitizeIntendedUrl(?string $url, string $default): string
    {
        if ($url === null || $url === '')
        {
            return $default;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '' || $path === '/')
        {
            return $url;
        }

        if (self::pathLooksLikeNonPageAsset(ltrim($path, '/')))
        {
            return $default;
        }

        foreach (self::NON_PAGE_ROUTE_NAMES as $routeName)
        {
            if (! self::routeExists($routeName))
            {
                continue;
            }

            $routePath = parse_url(route($routeName), PHP_URL_PATH);
            if (is_string($routePath) && $routePath !== '' && str_starts_with($path, $routePath))
            {
                return $default;
            }
        }

        return $url;
    }

    private static function pathLooksLikeNonPageAsset(string $path): bool
    {
        $normalized = '/'.ltrim($path, '/');

        return str_contains($normalized, '/chat/whatsapp-qr-image')
            || str_contains($normalized, '/registration/onboarding/chat-link-qr')
            || str_ends_with($normalized, '.png')
            || str_ends_with($normalized, '.jpg')
            || str_ends_with($normalized, '.jpeg')
            || str_ends_with($normalized, '.webp');
    }

    private static function routeExists(string $name): bool
    {
        try
        {
            return app('router')->has($name);
        } catch (\Throwable)
        {
            return false;
        }
    }
}
