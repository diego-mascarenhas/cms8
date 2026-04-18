<?php

namespace App\Support;

/**
 * OAuth / API scopes used for Google People + Calendar (read + write).
 */
final class GoogleIntegrationScopes
{
    public const CALENDAR_EVENTS = 'https://www.googleapis.com/auth/calendar.events';

    public const CONTACTS = 'https://www.googleapis.com/auth/contacts';

    /**
     * @return array<int, string>
     */
    public static function calendarForApiClient(): array
    {
        return [self::CALENDAR_EVENTS];
    }

    /**
     * @return array<int, string>
     */
    public static function contactsForApiClient(): array
    {
        return [self::CONTACTS];
    }
}
