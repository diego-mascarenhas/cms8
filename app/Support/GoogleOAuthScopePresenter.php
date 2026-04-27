<?php

namespace App\Support;

/**
 * Human-readable labels for Google OAuth scope URLs stored on external_accounts.
 */
final class GoogleOAuthScopePresenter
{
    /**
     * @param  array<int, mixed>|null  $scopes
     * @return array<int, string>
     */
    public static function normalized(?array $scopes): array
    {
        if (! is_array($scopes) || $scopes === [])
        {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('strval', $scopes))));
    }

    /**
     * @param  array<int, mixed>|null  $scopes
     * @return 'none'|'readonly'|'write'
     */
    public static function calendarAccessLevel(?array $scopes): string
    {
        foreach (self::normalized($scopes) as $s)
        {
            if (str_contains($s, 'calendar.readonly'))
            {
                return 'readonly';
            }
        }

        foreach (self::normalized($scopes) as $s)
        {
            if (str_contains($s, 'calendar.events') || str_ends_with($s, '/auth/calendar'))
            {
                return 'write';
            }
        }

        return 'none';
    }

    /**
     * @param  array<int, mixed>|null  $scopes
     * @return 'none'|'readonly'|'write'
     */
    public static function contactsAccessLevel(?array $scopes): string
    {
        foreach (self::normalized($scopes) as $s)
        {
            if (str_contains($s, 'contacts.readonly'))
            {
                return 'readonly';
            }
        }

        foreach (self::normalized($scopes) as $s)
        {
            if (str_ends_with($s, '/auth/contacts'))
            {
                return 'write';
            }
        }

        return 'none';
    }

    /**
     * @param  array<int, mixed>|null  $scopes
     */
    public static function calendarPermissionLabel(?array $scopes): string
    {
        return match (self::calendarAccessLevel($scopes))
        {
            'readonly' => __('app.Google permission calendar read only'),
            'write' => self::calendarWriteLabel($scopes),
            default => __('app.Google permission calendar none'),
        };
    }

    /**
     * @param  array<int, mixed>|null  $scopes
     */
    public static function contactsPermissionLabel(?array $scopes): string
    {
        return match (self::contactsAccessLevel($scopes))
        {
            'readonly' => __('app.Google permission contacts read only'),
            'write' => __('app.Google permission contacts read write'),
            default => __('app.Google permission contacts none'),
        };
    }

    /**
     * Short badge text for the Google accounts table (no "Calendario:" / "Contactos:" prefix).
     *
     * @param  array<int, mixed>|null  $scopes
     */
    public static function calendarBadgeLabel(?array $scopes): string
    {
        return match (self::calendarAccessLevel($scopes))
        {
            'readonly' => __('app.Google badge readonly'),
            'write' => self::calendarBadgeWriteLabel($scopes),
            default => __('app.Google badge no access'),
        };
    }

    /**
     * @param  array<int, mixed>|null  $scopes
     */
    public static function contactsBadgeLabel(?array $scopes): string
    {
        return match (self::contactsAccessLevel($scopes))
        {
            'readonly' => __('app.Google badge readonly'),
            'write' => __('app.Google badge read write'),
            default => __('app.Google badge no access'),
        };
    }

    /**
     * @param  array<int, mixed>|null  $scopes
     */
    private static function calendarBadgeWriteLabel(?array $scopes): string
    {
        foreach (self::normalized($scopes) as $s)
        {
            if (str_contains($s, 'calendar.events'))
            {
                return __('app.Google badge read write');
            }
        }

        return __('app.Google badge calendar full');
    }

    /**
     * @param  array<int, mixed>|null  $scopes
     */
    private static function calendarWriteLabel(?array $scopes): string
    {
        foreach (self::normalized($scopes) as $s)
        {
            if (str_contains($s, 'calendar.events'))
            {
                return __('app.Google permission calendar read write events');
            }
        }

        return __('app.Google permission calendar full');
    }

    public static function describeScope(string $scope): string
    {
        return match (true)
        {
            str_contains($scope, 'calendar.readonly') => __('app.Google scope calendar readonly'),
            str_contains($scope, 'calendar.events') => __('app.Google scope calendar events'),
            str_ends_with($scope, '/auth/calendar') => __('app.Google scope calendar full'),
            str_contains($scope, 'contacts.readonly') => __('app.Google scope contacts readonly'),
            str_ends_with($scope, '/auth/contacts') => __('app.Google scope contacts'),
            $scope === 'openid' => __('app.Google scope openid'),
            $scope === 'email' => __('app.Google scope email'),
            $scope === 'profile' => __('app.Google scope profile'),
            str_contains($scope, 'userinfo.email') => __('app.Google scope userinfo email'),
            str_contains($scope, 'userinfo.profile') => __('app.Google scope userinfo profile'),
            str_contains($scope, 'userinfo.openid') => __('app.Google scope openid'),
            default => $scope,
        };
    }
}
