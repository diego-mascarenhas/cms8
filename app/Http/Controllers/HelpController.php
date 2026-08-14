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
        $tokens = $team->getApiTokens();
        $plainToken = $tokens[0]['plain'] ?? $team->getSetting('api_token_plain', null);
        if ($plainToken)
        {
            return $plainToken;
        }

        // If no token exists, leave placeholder for docs
        if ($tokens !== [] || $team->getSetting('api_token_hash'))
        {
            $created = $team->createApiToken('API Access Token', '*');

            return $created['plain'];
        }

        return 'YOUR_API_TOKEN';
    }

    /**
     * Display the help documentation index
     */
    public function index()
    {
        $registrationMode = strtolower((string) config('registration.mode', 'free'));
        $showOnboardingRegistrationPaymentStep = in_array($registrationMode, ['checkout', 'gate'], true);

        return view('help.index', [
            'apiToken' => $this->getUserApiToken(),
            'showOnboardingRegistrationPaymentStep' => $showOnboardingRegistrationPaymentStep,
        ]);
    }

    /**
     * Display post-payment onboarding documentation
     */
    public function onboarding()
    {
        return view('help.onboarding', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }

    /**
     * Display chat and assistant documentation
     */
    public function chatAssistant()
    {
        return view('help.chat-assistant', [
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
     * Display CMS posts API documentation
     */
    public function apiPosts()
    {
        return view('help.api-posts', [
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
     * Display Google People & Calendar (OAuth) sync configuration documentation
     */
    public function googlePeopleCalendarSync()
    {
        return view('help.environment-variables-google-people-calendar', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }

    /**
     * Display team social networks (OAuth) documentation
     */
    public function teamSocialNetworks()
    {
        return view('help.team-social-networks', [
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
     * PostgreSQL unaccent extension and SearchNormalizer (accent-insensitive search).
     */
    public function postgresqlSearchUnaccent()
    {
        return view('help.postgresql-search-unaccent', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }

    /**
     * SPF / DNS for outgoing email (system SMTP).
     */
    public function emailSpfDns()
    {
        return view('help.email-spf-dns', [
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

    /**
     * Display WhatsApp send API documentation
     */
    public function apiWhatsApp()
    {
        return view('help.api-whatsapp', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }

    /**
     * Stripe webhook URL and events (Cashier + Humano handlers).
     */
    public function stripeWebhook()
    {
        return view('help.stripe-webhook', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }

    /**
     * WordPress MCP Adapter + Cursor mcp.json setup.
     */
    public function wordpressMcpCursor()
    {
        return view('help.wordpress-mcp-cursor', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }

    /**
     * Paid Ads platform setup: developer portals, OAuth apps and redirect URIs.
     */
    public function paidAdsSetup()
    {
        return view('help.paid-ads-setup', [
            'apiToken' => $this->getUserApiToken(),
        ]);
    }

    /**
     * Catalog of downloadable IDONEO WordPress plugins.
     *
     * @return array<string, array<string, string>>
     */
    private function pluginCatalog(): array
    {
        return [
            'idoneo-custom-fields' => [
                'name' => 'IDONEO Custom Fields',
                'file' => 'idoneo-custom-fields.zip',
                'version' => '1.0.0',
                'icon' => 'ti ti-forms',
                'description' => __('help_plugins.custom_fields_desc'),
            ],
            'idoneo-cms-sync-for-humano' => [
                'name' => 'IDONEO CMS Sync para Humano',
                'file' => 'idoneo-cms-sync-for-humano.zip',
                'version' => '0.1.0',
                'icon' => 'ti ti-refresh',
                'description' => __('help_plugins.cms_sync_desc'),
            ],
            'idoneo-chat-for-humano' => [
                'name' => 'IDONEO Chat for Humano',
                'file' => 'idoneo-chat-for-humano.zip',
                'version' => '0.9.3',
                'icon' => 'ti ti-message-chatbot',
                'description' => __('help_plugins.chat_desc'),
            ],
        ];
    }

    /**
     * Display the WordPress plugins download page (manual + downloads).
     */
    public function plugins()
    {
        $catalog = $this->pluginCatalog();
        foreach ($catalog as $slug => &$plugin)
        {
            $path = public_path('downloads/wordpress-plugins/'.$plugin['file']);
            $plugin['available'] = is_file($path);
            $plugin['size'] = $plugin['available'] ? $this->humanFilesize((int) filesize($path)) : null;
        }
        unset($plugin);

        return view('help.plugins', [
            'apiToken' => $this->getUserApiToken(),
            'plugins' => $catalog,
        ]);
    }

    /**
     * Stream a plugin zip as a download (slug is validated against the catalog).
     */
    public function downloadPlugin(string $slug): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $catalog = $this->pluginCatalog();
        abort_unless(isset($catalog[$slug]), 404);

        $path = public_path('downloads/wordpress-plugins/'.$catalog[$slug]['file']);
        abort_unless(is_file($path), 404);

        return response()->download($path, $catalog[$slug]['file']);
    }

    private function humanFilesize(int $bytes): string
    {
        if ($bytes >= 1048576)
        {
            return round($bytes / 1048576, 1).' MB';
        }
        if ($bytes >= 1024)
        {
            return round($bytes / 1024).' KB';
        }

        return $bytes.' B';
    }
}
