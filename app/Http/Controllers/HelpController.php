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
     * Display contents API documentation
     */
    public function apiContents()
    {
        return view('help.api-contents', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }

    /**
     * Display enterprises API documentation
     */
    public function apiEnterprises()
    {
        return view('help.api-enterprises', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }

    /**
     * Display payments API documentation
     */
    public function apiPayments()
    {
        return view('help.api-payments', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }

    /**
     * Display products API documentation
     */
    public function apiProducts()
    {
        return view('help.api-products', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }

    /**
     * Display orders API documentation
     */
    public function apiOrders()
    {
        return view('help.api-orders', [
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

    /**
     * Display environment variables and team configuration documentation index
     */
    public function environmentVariables()
    {
        return view('help.environment-variables-index', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }

    /**
     * Display Google Analytics configuration documentation
     */
    public function environmentVariablesGoogleAnalytics()
    {
        return view('help.environment-variables-google-analytics', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }

    /**
     * Display WooCommerce configuration documentation
     */
    public function woocommerceConfiguration()
    {
        return view('help.woocommerce-configuration', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }

    /**
     * Display tasks API documentation
     */
    public function apiTasks()
    {
        return view('help.api-tasks', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }

    /**
     * Display prompts API documentation
     */
    public function apiPrompts()
    {
        return view('help.api-prompts', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }
}
