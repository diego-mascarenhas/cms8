<?php

namespace App\Services;

use App\Models\Team;
use Google\Client;

class GoogleCredentialsService
{
    /**
     * Get Google Client configured with team credentials
     *
     * @param  array  $scopes  Google API scopes
     *
     * @throws \Exception
     */
    public static function getClient(Team $team, array $scopes = []): Client
    {
        // Get credentials from team settings
        $credentialsJson = $team->getSetting('analytics_credentials_json');

        if (empty($credentialsJson))
        {
            throw new \Exception('Google credentials not configured for this team. Please configure them in Team Settings > Analytics.');
        }

        // Parse JSON credentials
        $credentials = json_decode($credentialsJson, true);

        if (json_last_error() !== JSON_ERROR_NONE)
        {
            throw new \Exception('Invalid Google credentials JSON format.');
        }

        // Create and configure Google Client
        $client = new Client;
        $client->setAuthConfig($credentials);

        // Set scopes if provided
        if (! empty($scopes))
        {
            $client->setScopes($scopes);
        }

        return $client;
    }

    /**
     * Get Google Client for Analytics
     */
    public static function getAnalyticsClient(Team $team): Client
    {
        return self::getClient($team, [
            'https://www.googleapis.com/auth/analytics.readonly',
        ]);
    }

    /**
     * Get Google Client for Calendar
     */
    public static function getCalendarClient(Team $team): Client
    {
        return self::getClient($team, [
            'https://www.googleapis.com/auth/calendar',
            'https://www.googleapis.com/auth/calendar.events',
        ]);
    }

    /**
     * Check if team has Google credentials configured
     */
    public static function hasCredentials(Team $team): bool
    {
        $credentialsJson = $team->getSetting('analytics_credentials_json');

        return ! empty($credentialsJson);
    }

    /**
     * Get Analytics Property ID for team
     */
    public static function getAnalyticsPropertyId(Team $team): ?string
    {
        return $team->getSetting('analytics_property_id');
    }

    /**
     * Get Calendar ID for team
     */
    public static function getCalendarId(Team $team): string
    {
        // Check if team has custom calendar ID
        $calendarId = $team->getSetting('google_calendar_id');

        if (! empty($calendarId))
        {
            return $calendarId;
        }

        // Extract email from service account credentials
        $credentialsJson = $team->getSetting('analytics_credentials_json');

        if (! empty($credentialsJson))
        {
            $credentials = json_decode($credentialsJson, true);

            if (isset($credentials['client_email']))
            {
                return $credentials['client_email'];
            }
        }

        // Default to 'primary'
        return 'primary';
    }
}
