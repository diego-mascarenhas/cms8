<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;

class PasswordResetFrontendUrl
{
    public static function register(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string
        {
            return self::urlFor($notifiable, $token, request());
        });
    }

    public static function urlFor(object $notifiable, string $token, Request $request): string
    {
        $email = $notifiable instanceof User
            ? $notifiable->getEmailForPasswordReset()
            : (string) ($notifiable->email ?? '');

        $frontend = self::resolve($request);
        if ($frontend !== null)
        {
            return $frontend.'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $email,
            ]);
        }

        return url(route('password.reset', [
            'token' => $token,
            'email' => $email,
        ]));
    }

    public static function resolve(Request $request): ?string
    {
        $requested = rtrim((string) $request->input('frontend_url', ''), '/');
        $allowed = self::allowed();

        if ($requested !== '' && in_array($requested, $allowed, true))
        {
            return $requested;
        }

        if ($request->is('api/auth/forgot-password'))
        {
            return $allowed[0] ?? null;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function allowed(): array
    {
        $allowed = [];

        foreach ([config('services.assistant.url')] as $candidate)
        {
            $normalized = rtrim((string) $candidate, '/');
            if ($normalized !== '')
            {
                $allowed[] = $normalized;
            }
        }

        return array_values(array_unique($allowed));
    }
}
