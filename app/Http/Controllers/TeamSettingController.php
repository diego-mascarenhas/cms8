<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTeamSettingsRequest;
use App\Models\ContactValoration;
use App\Models\CustomTranslation;
use App\Models\Team;
use Illuminate\Http\Request;

class TeamSettingController extends Controller
{
    public function index(Team $team)
    {
        $this->authorize('update', $team);

        // Get all team settings grouped by group
        $groupedSettings = $team->settings()
            ->orderBy('group')
            ->get()
            ->groupBy('group');

        return view('team-settings.index', compact('team', 'groupedSettings'));
    }

    public function edit(Team $team, $group = 'stripe')
    {
        $this->authorize('update', $team);

        $settings = $this->getSettingsConfig($team, $group);

        return view('team-settings.edit', compact('team', 'settings', 'group'));
    }

    public function update(UpdateTeamSettingsRequest $request, Team $team)
    {
        $this->authorize('update', $team);

        foreach ($request->validated() as $group => $settings) {
            foreach ($settings as $key => $value) {
                if (! empty($value) || $value === '0') {
                    $team->setSetting($key, $value, [
                        'group' => $group,
                        'is_encrypted' => in_array($key, ['stripe_secret', 'stripe_webhook', 'api_token_hash', 'twilio_token']),
                    ]);
                }
            }
        }

        $group = array_key_first($request->validated());
        $message = ucfirst($group).' settings updated successfully';

        return redirect()
            ->back()
            ->with('success', $message);
    }

    /**
     * Get the settings configuration for a specific group
     */
    protected function getSettingsConfig(Team $team, $group)
    {
        $config = [
            'stripe' => [
                'title' => 'Stripe Integration',
                'icon' => 'ti ti-brand-stripe',
                'settings' => [
                    'stripe_public' => [
                        'label' => 'Public Key',
                        'type' => 'text',
                        'value' => $team->getSetting('stripe_public'),
                        'is_encrypted' => false,
                    ],
                    'stripe_secret' => [
                        'label' => 'Secret Key',
                        'type' => 'password',
                        'value' => $team->getSetting('stripe_secret'),
                        'is_encrypted' => true,
                    ],
                    'stripe_webhook' => [
                        'label' => 'Webhook Secret',
                        'type' => 'password',
                        'value' => $team->getSetting('stripe_webhook'),
                        'is_encrypted' => true,
                    ],
                ],
            ],
            'categories' => [
                'title' => 'Categories Configuration',
                'icon' => 'ti ti-category',
                'settings' => [
                    'categories_default_status' => [
                        'label' => 'Default Status',
                        'type' => 'select',
                        'options' => ['active' => 'Active', 'inactive' => 'Inactive'],
                        'value' => $team->getSetting('categories_default_status', 'active'),
                        'is_encrypted' => false,
                    ],
                    'categories_require_approval' => [
                        'label' => 'Require Approval',
                        'type' => 'checkbox',
                        'value' => $team->getSetting('categories_require_approval', '0'),
                        'is_encrypted' => false,
                    ],
                    'categories_max_depth' => [
                        'label' => 'Maximum Subcategory Depth',
                        'type' => 'select',
                        'options' => [
                            '1' => '1 Level',
                            '2' => '2 Levels',
                            '3' => '3 Levels',
                        ],
                        'value' => $team->getSetting('categories_max_depth', '2'),
                        'is_encrypted' => false,
                    ],
                    'categories_allow_multiple_parents' => [
                        'label' => 'Allow Multiple Parent Categories',
                        'type' => 'checkbox',
                        'value' => $team->getSetting('categories_allow_multiple_parents', '0'),
                        'is_encrypted' => false,
                    ],
                    'categories_default_ordering' => [
                        'label' => 'Default Ordering',
                        'type' => 'select',
                        'options' => [
                            'name_asc' => 'Name (A-Z)',
                            'name_desc' => 'Name (Z-A)',
                            'created_desc' => 'Newest First',
                            'created_asc' => 'Oldest First',
                            'custom' => 'Custom Order',
                        ],
                        'value' => $team->getSetting('categories_default_ordering', 'name_asc'),
                        'is_encrypted' => false,
                    ],
                ],
            ],
            'notifications' => [
                'title' => 'Notification Settings',
                'icon' => 'ti ti-bell',
                'settings' => [
                    'notifications_email' => [
                        'label' => 'Email Notifications',
                        'type' => 'checkbox',
                        'value' => $team->getSetting('notifications_email', '1'),
                        'is_encrypted' => false,
                    ],
                    'notifications_sms' => [
                        'label' => 'SMS Notifications',
                        'type' => 'checkbox',
                        'value' => $team->getSetting('notifications_sms', '0'),
                        'is_encrypted' => false,
                    ],
                ],
            ],
            'api' => [
                'title' => 'API Access Token',
                'icon' => 'ti ti-key',
                'settings' => [
                    'api_token_name' => [
                        'label' => 'Token Name',
                        'type' => 'text',
                        'value' => $team->getSetting('api_token_name', 'API Access Token'),
                        'is_encrypted' => false,
                    ],
                    'api_token_abilities' => [
                        'label' => 'Token Abilities',
                        'type' => 'select',
                        'options' => [
                            '*' => 'All Abilities',
                            'read' => 'Read Only',
                            'write' => 'Write Only',
                            'read,write' => 'Read & Write',
                        ],
                        'value' => $team->getSetting('api_token_abilities', '*'),
                        'is_encrypted' => false,
                    ],
                ],
            ],
            'twilio' => [
                'title' => 'Twilio Configuration',
                'icon' => 'ti ti-phone',
                'settings' => [
                    'twilio_sid' => [
                        'label' => 'Account SID',
                        'type' => 'text',
                        'value' => $team->getSetting('twilio_sid'),
                        'is_encrypted' => false,
                    ],
                    'twilio_token' => [
                        'label' => 'Auth Token',
                        'type' => 'password',
                        'value' => $team->getSetting('twilio_token'),
                        'is_encrypted' => true,
                    ],
                    'twilio_sms_from' => [
                        'label' => 'SMS From Number',
                        'type' => 'text',
                        'value' => $team->getSetting('twilio_sms_from'),
                        'is_encrypted' => false,
                    ],
                    'twilio_whatsapp_from' => [
                        'label' => 'WhatsApp From Number',
                        'type' => 'text',
                        'value' => $team->getSetting('twilio_whatsapp_from'),
                        'is_encrypted' => false,
                    ],
                    'twilio_webhook_url' => [
                        'label' => 'Webhook URL',
                        'type' => 'readonly',
                        'value' => $team->getTwilioWebhookUrl(),
                        'is_encrypted' => false,
                        'help' => 'This URL is automatically generated for your team. Use this in your Twilio Console.',
                        'readonly' => true,
                    ],
                    'twilio_status_callback_url' => [
                        'label' => 'Status Callback URL',
                        'type' => 'readonly',
                        'value' => $team->getTwilioStatusCallbackUrl(),
                        'is_encrypted' => false,
                        'help' => 'This URL is automatically generated for your team. Use this in your Twilio Console.',
                        'readonly' => true,
                    ],
                ],
            ],
        ];

        return isset($config[$group]) ? [$group => $config[$group]] : [];
    }

    /**
     * Show valorations management page
     */
    public function valorations(Team $team)
    {
        $this->authorize('update', $team);

        $valorations = ContactValoration::where('team_id', $team->id)
            ->orderBy('id')
            ->get();

        return view('team-settings.valorations', compact('team', 'valorations'));
    }

    /**
     * Store a new valoration
     */
    public function storeValoration(Request $request, Team $team)
    {
        $this->authorize('update', $team);

        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'required|string|max:10',
        ]);

        // Get next ID for this team
        $lastValoration = ContactValoration::where('team_id', $team->id)
            ->orderBy('id', 'desc')
            ->first();

        $nextId = $lastValoration ? $lastValoration->id + 1 : ($team->id * 10) + 1;

        ContactValoration::create([
            'id' => $nextId,
            'team_id' => $team->id,
            'name' => $request->name,
            'icon' => $request->icon,
        ]);

        return redirect()->back()->with('success', 'Valoración creada exitosamente');
    }

    /**
     * Update an existing valoration
     */
    public function updateValoration(Request $request, Team $team, ContactValoration $valoration)
    {
        $this->authorize('update', $team);

        // Ensure the valoration belongs to this team
        if ($valoration->team_id !== $team->id) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'required|string|max:10',
        ]);

        $valoration->update([
            'name' => $request->name,
            'icon' => $request->icon,
        ]);

        return redirect()->back()->with('success', 'Valoración actualizada exitosamente');
    }

    /**
     * Delete a valoration
     */
    public function destroyValoration(Team $team, ContactValoration $valoration)
    {
        $this->authorize('update', $team);

        // Ensure the valoration belongs to this team
        if ($valoration->team_id !== $team->id) {
            abort(403);
        }

        // Check if any contacts are using this valoration
        $contactsCount = \App\Models\Contact::where('valoration_id', $valoration->id)->count();

        if ($contactsCount > 0) {
            return redirect()->back()->with('error', "No se puede eliminar la valoración porque hay {$contactsCount} contactos que la están usando");
        }

        $valoration->delete();

        return redirect()->back()->with('success', 'Valoración eliminada exitosamente');
    }

    /**
     * Show API tokens management page
     */
    public function apiTokens(Team $team)
    {
        $this->authorize('update', $team);

        // Get current API token (if exists)
        $currentToken = $team->getSetting('api_token_hash');
        $tokenName = $team->getSetting('api_token_name', 'API Access Token');
        $tokenAbilities = $team->getSetting('api_token_abilities', '*');
        $tokenCreated = $team->getSetting('api_token_created_at');

        return view('team-settings.api-tokens', compact('team', 'currentToken', 'tokenName', 'tokenAbilities', 'tokenCreated'));
    }

    /**
     * Generate a new API token
     */
    public function generateApiToken(Request $request, Team $team)
    {
        $this->authorize('update', $team);

        $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'required|string',
        ]);

        // Generate a new token
        $tokenValue = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $tokenValue);

        // Store token settings
        $team->setSetting('api_token_hash', $tokenHash, [
            'group' => 'api',
            'is_encrypted' => true,
        ]);

        $team->setSetting('api_token_name', $request->name, [
            'group' => 'api',
            'is_encrypted' => false,
        ]);

        $team->setSetting('api_token_abilities', $request->abilities, [
            'group' => 'api',
            'is_encrypted' => false,
        ]);

        $team->setSetting('api_token_created_at', now()->toDateTimeString(), [
            'group' => 'api',
            'is_encrypted' => false,
        ]);

        return redirect()->back()->with([
            'success' => 'API token generated successfully',
            'new_token' => $tokenValue,
        ]);
    }

    /**
     * Revoke the current API token
     */
    public function revokeApiToken(Team $team)
    {
        $this->authorize('update', $team);

        // Remove token settings
        $team->settings()->where('group', 'api')->delete();

        return redirect()->back()->with('success', 'API token revoked successfully');
    }

    /**
     * Show custom translations management page
     */
    public function customTranslations(Team $team)
    {
        $this->authorize('update', $team);

        $translations = CustomTranslation::where('team_id', $team->id)
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group');

        // Get available translation groups
        $availableGroups = [
            'app' => 'Application',
            'auth' => 'Authentication',
            'validation' => 'Validation',
            'pagination' => 'Pagination',
            'passwords' => 'Passwords',
        ];

        // Get available locales
        $availableLocales = [
            'es' => 'Español',
            'en' => 'English',
            'fr' => 'Français',
            'de' => 'Deutsch',
        ];

        return view('team-settings.custom-translations', compact('team', 'translations', 'availableGroups', 'availableLocales'));
    }

    /**
     * Store a new custom translation
     */
    public function storeCustomTranslation(Request $request, Team $team)
    {
        $this->authorize('update', $team);

        $request->validate([
            'key' => 'required|string|max:255',
            'value' => 'required|string',
            'group' => 'required|string|max:50',
            'locale' => 'required|string|max:5',
        ]);

        // Check if translation already exists
        $existing = CustomTranslation::where('team_id', $team->id)
            ->where('key', $request->input('key'))
            ->where('group', $request->input('group'))
            ->where('locale', $request->input('locale'))
            ->first();

        if ($existing) {
            return redirect()->back()->with('error', 'Esta traducción ya existe para este equipo');
        }

        CustomTranslation::create([
            'team_id' => $team->id,
            'key' => $request->input('key'),
            'value' => $request->input('value'),
            'group' => $request->input('group'),
            'locale' => $request->input('locale'),
        ]);

        // Clear cache for this translation
        app(\App\Services\CustomTranslationService::class)->clearCache($request->input('key'), $request->input('group'), $request->input('locale'));

        return redirect()->back()->with('success', 'Traducción personalizada creada exitosamente');
    }

    /**
     * Update an existing custom translation
     */
    public function updateCustomTranslation(Request $request, Team $team, CustomTranslation $translation)
    {
        $this->authorize('update', $team);

        // Ensure the translation belongs to this team
        if ($translation->team_id !== $team->id) {
            abort(403);
        }

        $request->validate([
            'key' => 'required|string|max:255',
            'value' => 'required|string',
            'group' => 'required|string|max:50',
            'locale' => 'required|string|max:5',
        ]);

        $translation->update([
            'key' => $request->input('key'),
            'value' => $request->input('value'),
            'group' => $request->input('group'),
            'locale' => $request->input('locale'),
        ]);

        // Clear cache for this translation
        app(\App\Services\CustomTranslationService::class)->clearCache($request->input('key'), $request->input('group'), $request->input('locale'));

        return redirect()->back()->with('success', 'Traducción personalizada actualizada exitosamente');
    }

    /**
     * Delete a custom translation
     */
    public function destroyCustomTranslation(Team $team, CustomTranslation $translation)
    {
        $this->authorize('update', $team);

        // Ensure the translation belongs to this team
        if ($translation->team_id !== $team->id) {
            abort(403);
        }

        $translation->delete();

        // Clear cache for this translation
        app(\App\Services\CustomTranslationService::class)->clearCache($translation->key, $translation->group, $translation->locale);

        return redirect()->back()->with('success', 'Traducción personalizada eliminada exitosamente');
    }

    /**
     * Bulk import custom translations
     */
    public function importCustomTranslations(Request $request, Team $team)
    {
        $this->authorize('update', $team);

        $request->validate([
            'translations' => 'required|array',
            'translations.*.key' => 'required|string|max:255',
            'translations.*.value' => 'required|string',
            'translations.*.group' => 'required|string|max:50',
            'translations.*.locale' => 'required|string|max:5',
        ]);

        $imported = 0;
        $updated = 0;

        foreach ($request->translations as $translationData) {
            $existing = CustomTranslation::where('team_id', $team->id)
                ->where('key', $translationData['key'])
                ->where('group', $translationData['group'])
                ->where('locale', $translationData['locale'])
                ->first();

            if ($existing) {
                $existing->update([
                    'value' => $translationData['value'],
                    'updated_at' => now(),
                ]);
                $updated++;
            } else {
                CustomTranslation::create([
                    'team_id' => $team->id,
                    'key' => $translationData['key'],
                    'value' => $translationData['value'],
                    'group' => $translationData['group'],
                    'locale' => $translationData['locale'],
                ]);
                $imported++;
            }
        }

        // Clear all cache for this team
        app(\App\Services\CustomTranslationService::class)->clearCache();

        $message = "Importación completada: {$imported} nuevas traducciones, {$updated} actualizadas";
        return redirect()->back()->with('success', $message);
    }
}
