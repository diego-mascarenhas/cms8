<?php

namespace App\Http\Controllers;

class HelpController extends Controller
{
    /**
     * Get API token for authenticated user
     */
    private function getUserApiToken()
    {
        if (! auth()->check())
        {
            return 'YOUR_API_TOKEN';
        }

        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return 'YOUR_API_TOKEN';
        }

        // Get the plain token if it exists, otherwise show placeholder
        $plainToken = $team->getSetting('api_token_plain', null);
        if ($plainToken)
        {
            return $plainToken;
        }

        // If token exists but no plain version, generate a new one
        $hasToken = $team->getSetting('api_token_hash');
        if ($hasToken)
        {
            // Generate new token for existing users
            $tokenValue = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $tokenValue);

            $team->setSetting('api_token_hash', $tokenHash, [
                'group' => 'api',
                'is_encrypted' => true,
            ]);

            $team->setSetting('api_token_plain', $tokenValue, [
                'group' => 'api',
                'is_encrypted' => true,
            ]);

            return $tokenValue;
        }

        return 'YOUR_API_TOKEN';
    }

    /**
     * Display the help documentation index
     */
    public function index()
    {
        return view('help.index', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }

    /**
     * Display contacts management documentation
     */
    public function contacts()
    {
        return view('help.contacts', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }

    /**
     * Display API documentation
     */
    public function api()
    {
        return view('help.api', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }

    /**
     * Display API authentication documentation
     */
    public function apiAuthentication()
    {
        return view('help.api-authentication', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }

    /**
     * Display contacts API documentation
     */
    public function apiContacts()
    {
        return view('help.api-contacts', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }

    /**
     * Display general usage documentation
     */
    public function usage()
    {
        return view('help.usage', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }
}
