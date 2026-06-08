<?php

namespace App\Http\Controllers;

use App\Enums\ExternalProvider;
use App\Http\Requests\UpdateTeamEmailSenderRequest;
use App\Http\Requests\UpdateTeamSettingsRequest;
use App\Models\ContactValoration;
use App\Models\CustomTranslation;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Services\AssistantChatService;
use App\Services\AstralChartService;
use App\Services\DefaultAssistantFlowPromptsService;
use App\Services\TokenUsageLogService;
use App\Services\WebDavApiClient;
use App\Support\TeamDefaultShortcuts;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;

use function Laravel\Ai\agent;

class TeamSettingController extends Controller
{
    public function index(Team $team, WebDavApiClient $webDavApiClient)
    {
        $this->authorize('update', $team);

        // Get all team settings grouped by group
        $groupedSettings = $team->settings()
            ->orderBy('group')
            ->get()
            ->groupBy('group');

        $googleExternalAccount = $team->externalAccounts()
            ->where('provider', ExternalProvider::Google)
            ->latest('id')
            ->first();

        $webDavExternalAccount = $team->externalAccounts()
            ->where('provider', ExternalProvider::WebDav)
            ->latest('id')
            ->first();

        $webDavApiConfigured = $webDavApiClient->isConfigured();

        $performanceInsightsModule = Module::query()
            ->where('key', 'performance_insights')
            ->where('status', 1)
            ->first();

        $performanceInsightsEnabled = $team->hasModule('performance_insights');

        return view('team-settings.index', compact(
            'team',
            'groupedSettings',
            'googleExternalAccount',
            'webDavExternalAccount',
            'webDavApiConfigured',
            'performanceInsightsModule',
            'performanceInsightsEnabled',
        ));
    }

    public function businessConfig(Team $team)
    {
        $this->authorize('update', $team);

        return view('settings.business-config', compact('team'));
    }

    /**
     * Generate a concise business improvement summary using AI, Apollo context and human archetype (birth data).
     * Uses the landing prompt instruction if available, plus the user's problemática and form context.
     */
    public function generateBusinessSummary(Request $request, Team $team): JsonResponse
    {
        $this->authorize('update', $team);

        $challenge = trim((string) $request->input('business_challenge', ''));
        $birthDate = $request->input('birth_date');
        $birthTime = $request->input('birth_time');

        $contextParts = [];

        $contextParts[] = 'Datos del negocio:';
        $contextParts[] = '- Nombre: '.trim((string) $request->input('business_name', ''));
        $contextParts[] = '- Rubro/Sector: '.trim((string) $request->input('business_industry', ''));
        $contextParts[] = '- Ubicación: '.trim((string) $request->input('business_location', ''));
        $contextParts[] = '- Código postal: '.trim((string) $request->input('business_postal_code', ''));
        $contextParts[] = '- Teléfono: '.trim((string) $request->input('business_phone', ''));
        $contextParts[] = '- WhatsApp: '.trim((string) $request->input('business_whatsapp', ''));
        $contextParts[] = '- Página web: '.trim((string) $request->input('business_website', ''));
        $contextParts[] = '- Email: '.trim((string) $request->input('business_email', ''));
        $contextParts[] = '- Eslogan: '.trim((string) $request->input('business_tagline', ''));
        $contextParts[] = '- Descripción: '.trim((string) $request->input('business_description', ''));

        if ($birthDate)
        {
            try
            {
                $astral = new AstralChartService;
                $birthCarbon = Carbon::parse($birthDate);
                $zodiac = $astral->getZodiacSign($birthCarbon);
                $northNode = $astral->getNorthNode($birthCarbon);
                $contextParts[] = '';
                $contextParts[] = 'Arquetipo humano (fecha y hora de nacimiento):';
                $contextParts[] = '- Signo zodiacal: '.($zodiac['sign'] ?? '').' '.($zodiac['symbol'] ?? '').' ('.($zodiac['element'] ?? '').')';
                $contextParts[] = '- Nodo Norte: '.($northNode['north'] ?? '');
                $contextParts[] = '- Nodo Sur: '.($northNode['south'] ?? '');
                if ($birthTime)
                {
                    $contextParts[] = '- Hora de nacimiento: '.$birthTime;
                }
            } catch (\Throwable $e)
            {
                Log::warning('AstralChartService in business summary', ['error' => $e->getMessage()]);
            }
        }

        $context = implode("\n", $contextParts);
        $userMessage = $challenge !== ''
            ? "Problemática actual del negocio:\n\n".$challenge."\n\n---\n\n".$context
            : $context;

        $prompt = Prompt::findByRoutingKey('landing');
        $teamId = $team->id;

        if ($prompt)
        {
            try
            {
                $service = app(AssistantChatService::class);
                $result = $service->run($userMessage, $teamId, null, null, false, 'landing');
                $summary = $result['response'] ?? '';
            } catch (\Throwable $e)
            {
                Log::error('AssistantChatService business summary failed', ['error' => $e->getMessage()]);

                return response()->json([
                    'summary' => null,
                    'message' => 'Error al generar el resumen con la IA. Intenta de nuevo.',
                ], 500);
            }
        } else
        {
            $defaultInstruction = 'Eres un consultor de negocio. Con el contexto que te proporcionan (datos del negocio, problemática actual y arquetipo humano por fecha de nacimiento), genera un resumen muy conciso (máximo 1 párrafo corto o 3-5 puntos) de lo que esta empresa necesita para mejorar. Sé directo y práctico.';
            try
            {
                $agent = agent(
                    instructions: $defaultInstruction,
                    messages: [],
                    tools: [],
                );
                $response = $agent->prompt($userMessage, [], Lab::Anthropic);
                $summary = $response->text ?: '';

                TokenUsageLogService::logFromAiResponse(
                    teamId: (int) $team->id,
                    service: 'TeamSettingController::businessSummary',
                    usage: $response->usage ?? null,
                    moduleKey: 'landings',
                    inputSize: strlen($userMessage),
                );
            } catch (\Throwable $e)
            {
                Log::error('Business summary AI fallback failed', ['error' => $e->getMessage()]);

                return response()->json([
                    'summary' => null,
                    'message' => 'Error al generar el resumen. Comprueba la configuración de IA.',
                ], 500);
            }
        }

        return response()->json([
            'summary' => $summary,
        ]);
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

        foreach ($request->validated() as $group => $groupPayload)
        {
            $settings = is_array($groupPayload) ? $groupPayload : [];

            // Restrict email plan settings to admin users only
            if ($group === 'email-plans' && ! auth()->user()->hasRole('admin'))
            {
                // Allow only non-sensitive email fields for regular users
                $allowedKeys = ['email_monthly_used', 'email_daily_used']; // For manual sync
                $settings = array_intersect_key($settings, array_flip($allowedKeys));
            }

            if ($group === 'affiliates' && ! auth()->user()->hasRole('admin'))
            {
                $settings = [];
            }

            foreach ($settings as $key => $value)
            {
                if ($group === 'email' && in_array($key, ['mail_from_name', 'mail_from_address'], true) && trim((string) $value) === '')
                {
                    $team->removeSetting($key);

                    continue;
                }

                $type = $this->getSettingType($key);
                $isBoolean = $type === 'boolean';
                $shouldSet = $isBoolean
                    ? true
                    : (! empty($value) || $value === '0' || in_array($key, ['assistant_whatsapp_blacklist_numbers'], true));
                if ($shouldSet)
                {
                    $storedValue = $isBoolean ? (bool) ($value ?? false) : $value;
                    $team->setSetting($key, $storedValue, [
                        'group' => $group,
                        'type' => $type,
                        'is_encrypted' => in_array($key, ['stripe_secret', 'stripe_webhook', 'api_token_hash', 'api_token_plain', 'twilio_token', 'mail_password', 'imap_password', 'woocommerce_consumer_secret', 'wordpress_application_password', 'analytics_credentials_json']),
                    ]);
                }
            }

            // When group is chat, ensure boolean team settings are persisted (unchecked = false in POST)
            if ($group === 'chat' && ! array_key_exists('assistant_auto_respond', $settings))
            {
                $team->setSetting('assistant_auto_respond', false, [
                    'group' => 'chat',
                    'type' => 'boolean',
                    'is_encrypted' => false,
                ]);
            }

            if ($group === 'chat' && ! array_key_exists('assistant_chat_stub', $settings))
            {
                $team->setSetting('assistant_chat_stub', false, [
                    'group' => 'chat',
                    'type' => 'boolean',
                    'is_encrypted' => false,
                ]);
            }

            if ($group === 'chat' && ! array_key_exists('assistant_keyword_intent_routing', $settings))
            {
                $team->setSetting('assistant_keyword_intent_routing', false, [
                    'group' => 'chat',
                    'type' => 'boolean',
                    'is_encrypted' => false,
                ]);
            }

            if ($group === 'chat' && ! array_key_exists('chat_ai_assistance_blocked', $settings))
            {
                $team->setSetting('chat_ai_assistance_blocked', false, [
                    'group' => 'chat',
                    'type' => 'boolean',
                    'is_encrypted' => false,
                ]);
            }

            if ($group === 'public_shop' && ! array_key_exists('public_catalog_enabled', $settings))
            {
                $team->setSetting('public_catalog_enabled', false, [
                    'group' => 'public_shop',
                    'type' => 'boolean',
                    'is_encrypted' => false,
                ]);
            }

            if ($group === 'notifications')
            {
                foreach ([
                    'notifications_email_enabled',
                    'notifications_sms_enabled',
                    'performance_insights_in_app_notification',
                ] as $notificationBooleanKey)
                {
                    if (! array_key_exists($notificationBooleanKey, $settings))
                    {
                        $team->setSetting($notificationBooleanKey, false, [
                            'group' => 'notifications',
                            'type' => 'boolean',
                            'is_encrypted' => false,
                        ]);
                    }
                }
            }

            if ($group === 'google')
            {
                foreach ([
                    'google_contacts_inbound_sync_enabled',
                    'google_contacts_outbound_sync_enabled',
                    'google_calendar_inbound_sync_enabled',
                    'google_calendar_outbound_sync_enabled',
                ] as $googleSyncBooleanKey)
                {
                    if (! array_key_exists($googleSyncBooleanKey, $settings))
                    {
                        $team->setSetting($googleSyncBooleanKey, false, [
                            'group' => 'google',
                            'type' => 'boolean',
                            'is_encrypted' => false,
                        ]);
                    }
                }
            }

            if ($group === 'webdav')
            {
                foreach ([
                    'webdav_contacts_inbound_sync_enabled',
                    'webdav_contacts_outbound_sync_enabled',
                    'webdav_calendar_inbound_sync_enabled',
                    'webdav_calendar_outbound_sync_enabled',
                    'webdav_tasks_inbound_sync_enabled',
                    'webdav_tasks_outbound_sync_enabled',
                ] as $webDavSyncBooleanKey)
                {
                    if (! array_key_exists($webDavSyncBooleanKey, $settings))
                    {
                        $team->setSetting($webDavSyncBooleanKey, false, [
                            'group' => 'webdav',
                            'type' => 'boolean',
                            'is_encrypted' => false,
                        ]);
                    }
                }
            }
        }

        $group = array_key_first($request->validated());
        $message = ucfirst($group).' settings updated successfully';

        return redirect()
            ->back()
            ->with('success', $message);
    }

    public function updateEmailSender(UpdateTeamEmailSenderRequest $request, Team $team): JsonResponse
    {
        $validated = $request->validated();

        $team->setSetting('mail_from_name', $validated['mail_from_name'], [
            'group' => 'email',
            'type' => 'text',
            'is_encrypted' => false,
        ]);
        $team->setSetting('mail_from_address', $validated['mail_from_address'], [
            'group' => 'email',
            'type' => 'email',
            'is_encrypted' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('app.email_sender_config_saved'),
            'sender' => [
                'from_name' => $validated['mail_from_name'],
                'from_address' => $validated['mail_from_address'],
            ],
        ]);
    }

    /**
     * Create missing default assistant flow rows in module_prompts (editable in /prompt/list).
     * Does not overwrite existing prompt_instruction text.
     */
    public function seedDefaultAssistantFlowPrompts(Team $team)
    {
        $this->authorize('update', $team);

        if ((int) $team->id !== (int) (auth()->user()->currentTeam?->id))
        {
            return redirect()
                ->back()
                ->with('error', __('Switch to this team in the app bar to run this action.'));
        }

        DefaultAssistantFlowPromptsService::syncForTeam((int) $team->id);

        return redirect()
            ->back()
            ->with('success', __('Default assistant flow prompts are ready. You can review and edit them in Prompts.'));
    }

    /**
     * Get the appropriate type for a setting
     */
    private function getSettingType(string $key): string
    {
        $integerFields = [
            'email_monthly_limit', 'email_daily_limit', 'contact_limit',
            'email_monthly_used', 'email_daily_used',
        ];

        $booleanFields = [
            'categories_require_approval', 'categories_allow_multiple_parents',
            'notifications_email_enabled',
            'notifications_sms_enabled',
            'performance_insights_in_app_notification',
            'assistant_auto_respond',
            'assistant_auto_respond_admins_when_off',
            'assistant_chat_stub',
            'assistant_keyword_intent_routing',
            'chat_ai_assistance_blocked',
            'google_contacts_outbound_sync_enabled',
            'google_calendar_outbound_sync_enabled',
            'google_contacts_inbound_sync_enabled',
            'google_calendar_inbound_sync_enabled',
            'webdav_contacts_outbound_sync_enabled',
            'webdav_calendar_outbound_sync_enabled',
            'webdav_tasks_outbound_sync_enabled',
            'webdav_contacts_inbound_sync_enabled',
            'webdav_calendar_inbound_sync_enabled',
            'webdav_tasks_inbound_sync_enabled',
            'public_catalog_enabled',
        ];

        if (in_array($key, $integerFields))
        {
            return 'integer';
        }

        if (in_array($key, $booleanFields))
        {
            return 'boolean';
        }

        return 'string';
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
                'icon' => 'ti ti-speakerphone',
                'settings' => array_merge([
                    'notifications_email_enabled' => [
                        'label' => 'Email Notifications',
                        'type' => 'checkbox',
                        'value' => $team->getSetting('notifications_email_enabled', '0'), // Default disabled
                        'is_encrypted' => false,
                        'section' => 'general',
                        'row' => 1,
                    ],
                    'notifications_sms_enabled' => [
                        'label' => 'SMS Notifications',
                        'type' => 'checkbox',
                        'value' => $team->getSetting('notifications_sms_enabled', '0'), // Default disabled
                        'is_encrypted' => false,
                        'section' => 'general',
                        'row' => 1,
                    ],
                ], $team->hasModule('performance_insights') ? [
                    'performance_insights_in_app_notification' => [
                        'label' => __('app.team_setting_performance_insights_in_app_notification'),
                        'type' => 'checkbox',
                        'value' => $team->getSetting('performance_insights_in_app_notification', '1'),
                        'is_encrypted' => false,
                        'section' => 'performance_insights',
                        'row' => 3,
                    ],
                ] : [], [
                    'notifications_from_name' => [
                        'label' => 'From Name',
                        'type' => 'text',
                        'value' => $team->getSetting('notifications_from_name', ''),
                        'is_encrypted' => false,
                        'section' => 'sender',
                        'row' => 2,
                        'placeholder' => 'Your Company Name',
                    ],
                    'notifications_from_email' => [
                        'label' => 'From Email Address',
                        'type' => 'email',
                        'value' => $team->getSetting('notifications_from_email', ''),
                        'is_encrypted' => false,
                        'section' => 'sender',
                        'row' => 2,
                        'placeholder' => 'notifications@yourdomain.com',
                    ],
                ]),
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
            'chat' => [
                'title' => __('Chat / Asistente'),
                'icon' => 'ti ti-lifebuoy',
                'settings' => [
                    'assistant_auto_respond' => [
                        'label' => __('Humano Assistant replies'),
                        'type' => 'checkbox',
                        'value' => $team->getSetting('assistant_auto_respond', '1') ? '1' : '0',
                        'is_encrypted' => false,
                        'help' => __('When enabled, the assistant can reply automatically. Turn off to pause (same as the chat sidebar).'),
                    ],
                    'assistant_auto_respond_admins_when_off' => [
                        'label' => __('Assistant replies only for admins (when assistant off)'),
                        'type' => 'checkbox',
                        'value' => $team->getSetting('assistant_auto_respond_admins_when_off', false) ? '1' : '0',
                        'is_encrypted' => false,
                        'help' => __('When Humano Assistant replies is off, still auto-reply only for team admins and editors (not clients).'),
                    ],
                    'assistant_chat_stub' => [
                        'label' => __('Predefined test responses'),
                        'type' => 'checkbox',
                        'value' => $team->getSetting('assistant_chat_stub', false) ? '1' : '0',
                        'is_encrypted' => false,
                        'help' => __('If enabled, chat and WhatsApp do not call the real AI; they return a test response (no credits, same as the chat sidebar).'),
                    ],
                    'assistant_keyword_intent_routing' => [
                        'label' => __('Keyword routing'),
                        'type' => 'checkbox',
                        'value' => $team->getSetting('assistant_keyword_intent_routing', false) ? '1' : '0',
                        'is_encrypted' => false,
                        'section' => 'routing',
                        'help' => __('Off means :default. On means :keyword, using module prompts and assistant tool intent. Same value as the “default flow” (inverted) switch in the chat sidebar.', [
                            'default' => __('Default assistant flow (AI discovery)'),
                            'keyword' => __('Keyword routing'),
                        ]),
                    ],
                    'chat_ai_assistance_blocked' => [
                        'label' => __('Block assistant AI button'),
                        'type' => 'checkbox',
                        'value' => $team->getSetting('chat_ai_assistance_blocked', false) ? '1' : '0',
                        'is_encrypted' => false,
                        'help' => __('If enabled, the chat AI toggle starts off for the team. Per-contact preferences still take priority (same as the chat sidebar).'),
                    ],
                    'assistant_whatsapp_blacklist_numbers' => [
                        'label' => __('WhatsApp auto-reply blacklist numbers'),
                        'type' => 'textarea',
                        'value' => (string) $team->getSetting('assistant_whatsapp_blacklist_numbers', ''),
                        'is_encrypted' => false,
                        'help' => __('Numbers separated by comma, semicolon, or line break. If a number is listed here, the assistant will never auto-reply on inbound WhatsApp for that sender.'),
                    ],
                ],
            ],
            'documents' => [
                'title' => __('Document OCR'),
                'icon' => 'ti ti-scan',
                'settings' => [
                    'documents_ocr_mode' => [
                        'label' => __('OCR engine mode'),
                        'type' => 'select',
                        'options' => [
                            'local' => __('Local (Tesseract)'),
                            'ai' => __('AI (vision model)'),
                            'hybrid' => __('Hybrid (runs both, picks best)'),
                        ],
                        'value' => $team->getSetting('documents_ocr_mode', 'ai'),
                        'is_encrypted' => false,
                        'help' => __('Choose how documents are read for OCR in the ingestion pipeline (chat, WhatsApp, uploads).'),
                    ],
                ],
            ],
            'public_shop' => [
                'title' => __('Public assistant shop'),
                'icon' => 'ti ti-shopping-bag',
                'settings' => [
                    'public_catalog_enabled' => [
                        'label' => __('Enable assistant shop'),
                        'type' => 'checkbox',
                        'value' => $team->getSetting('public_catalog_enabled') ? '1' : '0',
                        'is_encrypted' => false,
                        'help' => __('The address uses your business website domain from the business configuration wizard (no https:// or trailing slash). Published products only.'),
                    ],
                    'public_catalog_url_hint' => [
                        'label' => __('Shop URL'),
                        'type' => 'readonly',
                        'value' => $team->publicCatalogShopUrl()
                            ?? __('Save your business website in the business wizard to generate the link.'),
                        'is_encrypted' => false,
                        'readonly' => true,
                    ],
                ],
            ],
            'wordpress' => [
                'title' => 'WordPress Connection',
                'icon' => 'ti ti-world',
                'settings' => [
                    'wordpress_url' => [
                        'label' => 'Site URL',
                        'type' => 'text',
                        'value' => $team->getSetting('wordpress_url'),
                        'is_encrypted' => false,
                        'placeholder' => 'https://tu-sitio.com',
                        'help' => 'URL completa del sitio WordPress (sin /wp-json ni barra final).',
                        'section' => 'connection',
                        'row' => 1,
                    ],
                    'wordpress_username' => [
                        'label' => 'Username',
                        'type' => 'text',
                        'value' => $team->getSetting('wordpress_username'),
                        'is_encrypted' => false,
                        'placeholder' => 'admin',
                        'help' => 'Usuario de WordPress con permisos para editar entradas y páginas.',
                        'section' => 'connection',
                        'row' => 2,
                    ],
                    'wordpress_application_password' => [
                        'label' => 'Application Password',
                        'type' => 'password',
                        'value' => $team->getSetting('wordpress_application_password'),
                        'is_encrypted' => true,
                        'placeholder' => 'xxxx xxxx xxxx xxxx xxxx xxxx',
                        'help' => 'Generado en WordPress: Usuarios → tu usuario → Contraseñas de aplicación. Se almacena cifrado.',
                        'section' => 'connection',
                        'row' => 2,
                    ],
                ],
            ],
            'woocommerce' => [
                'title' => 'WooCommerce Integration',
                'icon' => 'ti ti-brand-wordpress',
                'settings' => [
                    'woocommerce_url' => [
                        'label' => 'Store URL',
                        'type' => 'text',
                        'value' => $team->getSetting('woocommerce_url'),
                        'is_encrypted' => false,
                        'placeholder' => 'https://tu-tienda.com',
                        'help' => 'La URL completa de tu tienda WooCommerce',
                        'section' => 'connection',
                        'row' => 1,
                    ],
                    'woocommerce_api_version' => [
                        'label' => 'API Version',
                        'type' => 'select',
                        'options' => [
                            'wc/v3' => 'v3 (Recomendado)',
                            'wc/v2' => 'v2',
                            'wc/v1' => 'v1',
                        ],
                        'value' => $team->getSetting('woocommerce_api_version', 'wc/v3'),
                        'is_encrypted' => false,
                        'section' => 'connection',
                        'row' => 1,
                    ],
                    'woocommerce_consumer_key' => [
                        'label' => 'Consumer Key',
                        'type' => 'text',
                        'value' => $team->getSetting('woocommerce_consumer_key'),
                        'is_encrypted' => false,
                        'placeholder' => 'ck_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
                        'help' => 'Consumer Key generado en WooCommerce > Settings > Advanced > REST API',
                        'section' => 'credentials',
                        'row' => 2,
                    ],
                    'woocommerce_consumer_secret' => [
                        'label' => 'Consumer Secret',
                        'type' => 'password',
                        'value' => $team->getSetting('woocommerce_consumer_secret'),
                        'is_encrypted' => true,
                        'placeholder' => 'cs_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
                        'help' => 'Consumer Secret generado en WooCommerce > Settings > Advanced > REST API',
                        'section' => 'credentials',
                        'row' => 2,
                    ],
                    'woocommerce_verify_ssl' => [
                        'label' => 'Verify SSL Certificate',
                        'type' => 'checkbox',
                        'value' => $team->getSetting('woocommerce_verify_ssl', '1'),
                        'is_encrypted' => false,
                        'help' => 'Recomendado activar para sitios en producción con SSL válido',
                        'section' => 'security',
                        'row' => 3,
                    ],
                ],
            ],
            'email' => [
                'title' => 'Email Configuration',
                'icon' => 'ti ti-mail',
                'settings' => [
                    // Sender Information - Row 1 (Always visible)
                    'mail_from_name' => [
                        'label' => 'From Name',
                        'type' => 'text',
                        'value' => $team->getSetting('mail_from_name'),
                        'is_encrypted' => false,
                        'placeholder' => env('MAIL_FROM_NAME'),
                        'help' => 'Leave empty to use: '.env('MAIL_FROM_NAME'),
                        'section' => 'sender',
                        'row' => 1,
                    ],
                    'mail_from_address' => [
                        'label' => 'From Email Address',
                        'type' => 'email',
                        'value' => $team->getSetting('mail_from_address'),
                        'is_encrypted' => false,
                        'placeholder' => env('MAIL_FROM_ADDRESS'),
                        'help' => 'Leave empty to use: '.env('MAIL_FROM_ADDRESS'),
                        'section' => 'sender',
                        'row' => 1,
                    ],
                    // Outgoing Email (SMTP) - Row 1 (Server Configuration)
                    'mail_host' => [
                        'label' => 'SMTP Host',
                        'type' => 'text',
                        'value' => $team->getSetting('mail_host'),
                        'is_encrypted' => false,
                        'placeholder' => env('MAIL_HOST'),
                        'help' => 'Leave empty to use system SMTP: '.env('MAIL_HOST'),
                        'section' => 'outgoing',
                        'row' => 1,
                    ],
                    'mail_port' => [
                        'label' => 'SMTP Port',
                        'type' => 'number',
                        'value' => $team->getSetting('mail_port', '587'),
                        'is_encrypted' => false,
                        'placeholder' => '587',
                        'help' => 'Default: 587 (TLS), 465 (SSL), 25 (unencrypted). Leave empty to use system SMTP.',
                        'section' => 'outgoing',
                        'row' => 1,
                    ],
                    'mail_encryption' => [
                        'label' => 'Encryption',
                        'type' => 'select',
                        'options' => [
                            'tls' => 'TLS',
                            'ssl' => 'SSL',
                            'none' => 'None',
                        ],
                        'value' => $team->getSetting('mail_encryption'),
                        'is_encrypted' => false,
                        'placeholder' => env('MAIL_ENCRYPTION'),
                        'help' => 'Leave empty to use system SMTP: '.env('MAIL_ENCRYPTION'),
                        'section' => 'outgoing',
                        'row' => 1,
                    ],
                    // Outgoing Email (SMTP) - Row 2 (Authentication)
                    'mail_username' => [
                        'label' => 'SMTP Username',
                        'type' => 'text',
                        'value' => $team->getSetting('mail_username'),
                        'is_encrypted' => false,
                        'placeholder' => env('MAIL_USERNAME'),
                        'help' => 'Username for SMTP authentication. Leave empty to use system SMTP: '.env('MAIL_USERNAME'),
                        'section' => 'outgoing',
                        'row' => 2,
                    ],
                    'mail_password' => [
                        'label' => 'SMTP Password',
                        'type' => 'password',
                        'value' => $team->getSetting('mail_password'),
                        'is_encrypted' => true,
                        'help' => 'Leave empty to use system SMTP configuration',
                        'section' => 'outgoing',
                        'row' => 2,
                    ],
                    // Incoming Email (IMAP) - Row 1
                    'imap_host' => [
                        'label' => 'IMAP Host',
                        'type' => 'text',
                        'value' => $team->getSetting('imap_host'),
                        'is_encrypted' => false,
                        'help' => 'For incoming email processing (optional)',
                        'section' => 'incoming',
                        'row' => 1,
                    ],
                    'imap_port' => [
                        'label' => 'IMAP Port',
                        'type' => 'number',
                        'value' => $team->getSetting('imap_port', '993'),
                        'is_encrypted' => false,
                        'help' => 'Usually 993 for IMAP SSL or 143 for IMAP',
                        'section' => 'incoming',
                        'row' => 1,
                    ],
                    'imap_encryption' => [
                        'label' => 'IMAP Encryption',
                        'type' => 'select',
                        'options' => [
                            'tls' => 'TLS',
                            'ssl' => 'SSL',
                            'none' => 'None',
                        ],
                        'value' => $team->getSetting('imap_encryption', 'ssl'),
                        'is_encrypted' => false,
                        'help' => 'Usually SSL for port 993',
                        'section' => 'incoming',
                        'row' => 1,
                    ],
                    // Incoming Email (IMAP) - Row 2
                    'imap_username' => [
                        'label' => 'IMAP Username',
                        'type' => 'text',
                        'value' => $team->getSetting('imap_username'),
                        'is_encrypted' => false,
                        'help' => 'Username for IMAP authentication (can be email or account ID). Usually same as SMTP username',
                        'section' => 'incoming',
                        'row' => 2,
                    ],
                    'imap_password' => [
                        'label' => 'IMAP Password',
                        'type' => 'password',
                        'value' => $team->getSetting('imap_password'),
                        'is_encrypted' => true,
                        'help' => 'Usually same as SMTP password',
                        'section' => 'incoming',
                        'row' => 2,
                    ],
                    'mailbox_spam_ai_enabled' => [
                        'label' => 'AI spam classification',
                        'type' => 'checkbox',
                        'value' => filter_var($team->getSetting('mailbox_spam_ai_enabled'), FILTER_VALIDATE_BOOLEAN),
                        'is_encrypted' => false,
                        'help' => 'When enabled, new inbound messages are classified with AI and moved to Spam when detected.',
                        'section' => 'incoming',
                        'row' => 3,
                    ],
                    'mailbox_spam_ai_prompt' => [
                        'label' => 'AI spam classification prompt',
                        'type' => 'textarea',
                        'value' => $team->getSetting('mailbox_spam_ai_prompt'),
                        'is_encrypted' => false,
                        'help' => 'Optional custom instructions for spam detection. Leave empty to use the default prompt.',
                        'section' => 'incoming',
                        'row' => 3,
                    ],
                ],
            ],
            'email-plans' => [
                'title' => 'Email Plans & Limits',
                'icon' => 'ti ti-mail-bolt',
                'settings' => [
                    // Plan Information (Read-only display)
                    'email_plan_display' => [
                        'label' => 'Current Plan',
                        'type' => 'display',
                        'value' => $team->getEmailPlan()->getDisplayName() ?? 'BASIC',
                        'help' => 'Only admin users can change email plans',
                        'is_encrypted' => false,
                        'section' => 'plan',
                        'row' => 1,
                    ],
                    'email_plan_description' => [
                        'label' => 'Plan Description',
                        'type' => 'display',
                        'value' => $team->getEmailPlan()->getDescription() ?? 'Basic email plan',
                        'is_encrypted' => false,
                        'section' => 'plan',
                        'row' => 1,
                    ],

                    // Monthly Limits
                    'email_monthly_limit' => [
                        'label' => 'Monthly Email Limit',
                        'type' => 'number',
                        'value' => $team->getSetting('email_monthly_limit', '10000'),
                        'is_encrypted' => false,
                        'help' => 'Maximum emails per month',
                        'section' => 'limits',
                        'row' => 2,
                    ],
                    'email_monthly_used' => [
                        'label' => 'Monthly Used',
                        'type' => 'display',
                        'value' => $team->getSetting('email_monthly_used', '0'),
                        'is_encrypted' => false,
                        'section' => 'limits',
                        'row' => 2,
                    ],

                    // Daily Limits
                    'email_daily_limit' => [
                        'label' => 'Daily Email Limit',
                        'type' => 'number',
                        'value' => $team->getSetting('email_daily_limit', '500'),
                        'is_encrypted' => false,
                        'help' => 'Maximum emails per day (0 = unlimited)',
                        'section' => 'limits',
                        'row' => 3,
                    ],
                    'email_daily_used' => [
                        'label' => 'Daily Used',
                        'type' => 'display',
                        'value' => $team->getSetting('email_daily_used', '0'),
                        'is_encrypted' => false,
                        'section' => 'limits',
                        'row' => 3,
                    ],

                    // Contact Limits
                    'contact_limit' => [
                        'label' => 'Contact Limit',
                        'type' => 'number',
                        'value' => $team->getSetting('contact_limit', '10000'),
                        'is_encrypted' => false,
                        'help' => 'Maximum number of contacts allowed',
                        'section' => 'contacts',
                        'row' => 4,
                    ],
                    'contact_count' => [
                        'label' => 'Current Contacts',
                        'type' => 'display',
                        'value' => $team->contacts()->count(),
                        'is_encrypted' => false,
                        'section' => 'contacts',
                        'row' => 4,
                    ],

                    // Reset Information
                    'email_monthly_reset_at' => [
                        'label' => 'Monthly Reset Date',
                        'type' => 'display',
                        'value' => $team->getSetting('email_monthly_reset_at') ? \Carbon\Carbon::parse($team->getSetting('email_monthly_reset_at'))->format('d/m/Y H:i') : 'Not set',
                        'is_encrypted' => false,
                        'section' => 'reset',
                        'row' => 5,
                    ],
                    'email_daily_reset_date' => [
                        'label' => 'Daily Reset Date',
                        'type' => 'display',
                        'value' => $team->getSetting('email_daily_reset_date', 'Not set'),
                        'is_encrypted' => false,
                        'section' => 'reset',
                        'row' => 5,
                    ],
                ],
            ],
            'analytics' => [
                'title' => 'Google Services',
                'icon' => 'ti ti-brand-google',
                'settings' => [
                    'analytics_credentials_json' => [
                        'label' => 'Service account credentials (JSON)',
                        'type' => 'textarea',
                        'value' => $team->getSetting('analytics_credentials_json'),
                        'is_encrypted' => true,
                        'placeholder' => 'Paste the full JSON key from Google Cloud Console...',
                        'help' => 'Create a service account in Google Cloud, enable Google Analytics Data API, and download the JSON key.',
                    ],
                    'analytics_property_id' => [
                        'label' => 'GA4 Property ID',
                        'type' => 'text',
                        'value' => $team->getSetting('analytics_property_id'),
                        'is_encrypted' => false,
                        'placeholder' => '123456789',
                        'help' => 'Find this in Google Analytics: Admin > Property Settings. Use the numeric Property ID.',
                    ],
                ],
            ],
            'google' => [
                'title' => __('app.team_setting_google_sync_title'),
                'icon' => 'ti ti-arrows-exchange',
                'settings' => [
                    'google_contacts_inbound_sync_enabled' => [
                        'label' => __('app.team_setting_google_contacts_inbound_sync'),
                        'type' => 'checkbox',
                        'value' => $team->googleContactsInboundSyncEnabled() ? '1' : '0',
                        'is_encrypted' => false,
                        'section' => 'inbound',
                        'row' => 1,
                        'help' => __('app.team_setting_google_contacts_inbound_sync_help'),
                    ],
                    'google_calendar_inbound_sync_enabled' => [
                        'label' => __('app.team_setting_google_calendar_inbound_sync'),
                        'type' => 'checkbox',
                        'value' => $team->googleCalendarInboundSyncEnabled() ? '1' : '0',
                        'is_encrypted' => false,
                        'section' => 'inbound',
                        'row' => 1,
                        'help' => __('app.team_setting_google_calendar_inbound_sync_help'),
                    ],
                    'google_contacts_outbound_sync_enabled' => [
                        'label' => __('app.team_setting_google_contacts_outbound_sync'),
                        'type' => 'checkbox',
                        'value' => $team->googleContactsOutboundSyncEnabled() ? '1' : '0',
                        'is_encrypted' => false,
                        'section' => 'outbound',
                        'row' => 2,
                        'help' => __('app.team_setting_google_contacts_outbound_sync_help'),
                    ],
                    'google_calendar_outbound_sync_enabled' => [
                        'label' => __('app.team_setting_google_calendar_outbound_sync'),
                        'type' => 'checkbox',
                        'value' => $team->googleCalendarOutboundSyncEnabled() ? '1' : '0',
                        'is_encrypted' => false,
                        'section' => 'outbound',
                        'row' => 2,
                        'help' => __('app.team_setting_google_calendar_outbound_sync_help'),
                    ],
                ],
            ],
            'webdav' => [
                'title' => __('app.team_setting_webdav_sync_title'),
                'icon' => 'ti ti-cloud-data-connection',
                'settings' => [
                    'webdav_contacts_inbound_sync_enabled' => [
                        'label' => __('app.team_setting_webdav_contacts_inbound_sync'),
                        'type' => 'checkbox',
                        'value' => $team->webdavContactsInboundSyncEnabled() ? '1' : '0',
                        'is_encrypted' => false,
                        'section' => 'inbound',
                        'row' => 1,
                        'help' => __('app.team_setting_webdav_contacts_inbound_sync_help'),
                    ],
                    'webdav_calendar_inbound_sync_enabled' => [
                        'label' => __('app.team_setting_webdav_calendar_inbound_sync'),
                        'type' => 'checkbox',
                        'value' => $team->webdavCalendarInboundSyncEnabled() ? '1' : '0',
                        'is_encrypted' => false,
                        'section' => 'inbound',
                        'row' => 1,
                        'help' => __('app.team_setting_webdav_calendar_inbound_sync_help'),
                    ],
                    'webdav_tasks_inbound_sync_enabled' => [
                        'label' => __('app.team_setting_webdav_tasks_inbound_sync'),
                        'type' => 'checkbox',
                        'value' => $team->webdavTasksInboundSyncEnabled() ? '1' : '0',
                        'is_encrypted' => false,
                        'section' => 'inbound',
                        'row' => 1,
                        'help' => __('app.team_setting_webdav_tasks_inbound_sync_help'),
                    ],
                    'webdav_contacts_outbound_sync_enabled' => [
                        'label' => __('app.team_setting_webdav_contacts_outbound_sync'),
                        'type' => 'checkbox',
                        'value' => $team->webdavContactsOutboundSyncEnabled() ? '1' : '0',
                        'is_encrypted' => false,
                        'section' => 'outbound',
                        'row' => 2,
                        'help' => __('app.team_setting_webdav_contacts_outbound_sync_help'),
                    ],
                    'webdav_calendar_outbound_sync_enabled' => [
                        'label' => __('app.team_setting_webdav_calendar_outbound_sync'),
                        'type' => 'checkbox',
                        'value' => $team->webdavCalendarOutboundSyncEnabled() ? '1' : '0',
                        'is_encrypted' => false,
                        'section' => 'outbound',
                        'row' => 2,
                        'help' => __('app.team_setting_webdav_calendar_outbound_sync_help'),
                    ],
                    'webdav_tasks_outbound_sync_enabled' => [
                        'label' => __('app.team_setting_webdav_tasks_outbound_sync'),
                        'type' => 'checkbox',
                        'value' => $team->webdavTasksOutboundSyncEnabled() ? '1' : '0',
                        'is_encrypted' => false,
                        'section' => 'outbound',
                        'row' => 2,
                        'help' => __('app.team_setting_webdav_tasks_outbound_sync_help'),
                    ],
                ],
            ],
            'calendar' => [
                'title' => 'Calendar',
                'icon' => 'ti ti-calendar-event',
                'settings' => [
                    'google_calendar_id' => [
                        'label' => 'Google Calendar ID (Optional)',
                        'type' => 'text',
                        'value' => $team->getSetting('google_calendar_id'),
                        'is_encrypted' => false,
                        'placeholder' => 'primary or your-calendar@group.calendar.google.com',
                        'help' => 'Leave empty to use "primary". To sync a specific calendar, paste its Calendar ID from Google Calendar settings.',
                    ],
                ],
            ],
            'affiliates' => [
                'title' => 'Affiliates (billing)',
                'icon' => 'ti ti-affiliate',
                'settings' => [
                    'affiliate_commission_percent' => [
                        'label' => 'Global commission % (this team as referrer)',
                        'type' => 'text',
                        'value' => $team->getSetting('affiliate_commission_percent', '0'),
                        'is_encrypted' => false,
                        'help' => 'Applies to referred client enterprises (referred_by = same-team referrer enterprise id, or legacy / external public code). 0 disables. Example: 10 = 10% of the paid invoice amount.',
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
        if ($valoration->team_id !== $team->id)
        {
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
        if ($valoration->team_id !== $team->id)
        {
            abort(403);
        }

        // Check if any contacts are using this valoration
        $contactsCount = \App\Models\Contact::where('valoration_id', $valoration->id)->count();

        if ($contactsCount > 0)
        {
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

    public function passwords(Team $team)
    {
        $this->authorize('update', $team);

        $hasMasterKey = $team->hasPasswordsMasterKey();
        $masterKeyHint = (string) $team->getSetting('passwords_master_key_hint', '');
        $rotationAt = $team->getSetting('passwords_rotation_at');

        return view('team-settings.passwords', compact('team', 'hasMasterKey', 'masterKeyHint', 'rotationAt'));
    }

    public function updatePasswordsMasterKey(Request $request, Team $team)
    {
        $this->authorize('update', $team);

        $rules = [
            'new_master_key' => ['required', 'string', 'min:8', 'confirmed'],
            'master_key_hint' => ['nullable', 'string', 'max:120'],
        ];

        if ($team->hasPasswordsMasterKey())
        {
            $rules['current_master_key'] = ['required', 'string'];
        }

        $validated = $request->validate($rules);

        if ($team->hasPasswordsMasterKey() && ! $team->verifyPasswordsMasterKey((string) $validated['current_master_key']))
        {
            return redirect()
                ->back()
                ->withErrors(['current_master_key' => __('The current master key is invalid.')])
                ->withInput();
        }

        $team->setSetting('passwords_master_key_hash', Hash::make((string) $validated['new_master_key']), [
            'group' => 'passwords',
            'type' => 'string',
            'is_encrypted' => true,
        ]);

        $team->setSetting('passwords_master_key_hint', (string) ($validated['master_key_hint'] ?? ''), [
            'group' => 'passwords',
            'type' => 'string',
            'is_encrypted' => false,
        ]);

        $team->setSetting('passwords_rotation_at', now()->toDateTimeString(), [
            'group' => 'passwords',
            'type' => 'string',
            'is_encrypted' => false,
        ]);

        $request->session()->forget("passwords_unlocked_team_{$team->id}");
        $request->session()->forget("passwords_unlocked_until_team_{$team->id}");

        return redirect()
            ->route('team-settings.passwords', $team)
            ->with('success', __('Master key saved successfully.'));
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

        // Store the plain token for display in documentation
        $team->setSetting('api_token_plain', $tokenValue, [
            'group' => 'api',
            'is_encrypted' => true, // Encrypt for security
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
     * Reveal the current API token (plain value) for viewing/copying
     */
    public function revealApiToken(Team $team)
    {
        $this->authorize('update', $team);

        $plainToken = $team->getSetting('api_token_plain');

        if (empty($plainToken))
        {
            return response()->json(['error' => 'No API token found'], 404);
        }

        return response()->json(['token' => $plainToken]);
    }

    /**
     * Update the current API token name and abilities (token value unchanged)
     */
    public function updateApiToken(Request $request, Team $team)
    {
        $this->authorize('update', $team);

        if (empty($team->getSetting('api_token_hash')))
        {
            return redirect()->route('team-settings.api-tokens', $team)
                ->with('error', 'No API token to update.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'required|string|max:255',
        ]);

        $team->setSetting('api_token_name', $request->name, [
            'group' => 'api',
            'is_encrypted' => false,
        ]);

        $team->setSetting('api_token_abilities', $request->abilities, [
            'group' => 'api',
            'is_encrypted' => false,
        ]);

        return redirect()->back()->with('success', 'API token updated successfully.');
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

        if ($existing)
        {
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
        if ($translation->team_id !== $team->id)
        {
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
        if ($translation->team_id !== $team->id)
        {
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

        foreach ($request->translations as $translationData)
        {
            $existing = CustomTranslation::where('team_id', $team->id)
                ->where('key', $translationData['key'])
                ->where('group', $translationData['group'])
                ->where('locale', $translationData['locale'])
                ->first();

            if ($existing)
            {
                $existing->update([
                    'value' => $translationData['value'],
                    'updated_at' => now(),
                ]);
                $updated++;
            } else
            {
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

    /**
     * Test SMTP connection
     */
    public function testSmtpConnection(Team $team)
    {
        $this->authorize('update', $team);

        try
        {
            $config = $team->getOutgoingEmailConfig();

            if (empty($config['host']) || empty($config['username']))
            {
                return response()->json([
                    'success' => false,
                    'message' => 'SMTP configuration is incomplete. Please configure host and username.',
                ]);
            }

            // Test with simple socket connection first
            $host = $config['host'];
            $port = $config['port'] ?? 587;
            $timeout = 10;

            // Test basic connectivity
            $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
            if (! $socket)
            {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot connect to {$host}:{$port} - {$errstr} ({$errno})",
                ]);
            }
            fclose($socket);

            // Test with Laravel's Mail facade using temporary config
            $originalConfig = config('mail.mailers.smtp');

            config([
                'mail.mailers.smtp.host' => $config['host'],
                'mail.mailers.smtp.port' => $config['port'] ?? 587,
                'mail.mailers.smtp.encryption' => $config['encryption'] ?? 'tls',
                'mail.mailers.smtp.username' => $config['username'],
                'mail.mailers.smtp.password' => $config['password'] ?? '',
            ]);

            // Create test transport
            $transport = app('mail.manager')->createSymfonyTransport([
                'transport' => 'smtp',
                'host' => $config['host'],
                'port' => $config['port'] ?? 587,
                'encryption' => $config['encryption'] ?? 'tls',
                'username' => $config['username'],
                'password' => $config['password'] ?? '',
            ]);

            // Test the connection
            $transport->start();

            // Restore original config
            config(['mail.mailers.smtp' => $originalConfig]);

            return response()->json([
                'success' => true,
                'message' => 'SMTP connection successful!',
            ]);
        } catch (\Exception $e)
        {
            // Restore original config on error
            if (isset($originalConfig))
            {
                config(['mail.mailers.smtp' => $originalConfig]);
            }

            return response()->json([
                'success' => false,
                'message' => 'SMTP connection failed: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Test IMAP connection
     */
    public function testImapConnection(Team $team)
    {
        $this->authorize('update', $team);

        try
        {
            $config = $team->getIncomingEmailConfig();

            if (empty($config['host']) || empty($config['username']))
            {
                return response()->json([
                    'success' => false,
                    'message' => 'IMAP configuration is incomplete. Please configure host and username.',
                ]);
            }

            $connectionString = "{{$config['host']}:{$config['port']}/imap";

            if ($config['encryption'] === 'ssl')
            {
                $connectionString .= '/ssl';
            } elseif ($config['encryption'] === 'tls')
            {
                $connectionString .= '/tls';
            }

            $connectionString .= '/novalidate-cert}';

            // Test IMAP connection
            $connection = @imap_open($connectionString, $config['username'], $config['password'] ?? '');

            if ($connection)
            {
                imap_close($connection);

                return response()->json([
                    'success' => true,
                    'message' => 'IMAP connection successful!',
                ]);
            } else
            {
                return response()->json([
                    'success' => false,
                    'message' => 'IMAP connection failed: '.imap_last_error(),
                ]);
            }
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'IMAP connection failed: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Test Stripe connection
     */
    public function testStripeConnection(Team $team)
    {
        $this->authorize('update', $team);

        try
        {
            $publicKey = $team->getSetting('stripe_public');
            $secretKey = $team->getSetting('stripe_secret');

            if (empty($publicKey) || empty($secretKey))
            {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe configuration is incomplete. Please configure both public and secret keys.',
                ]);
            }

            // Validate key format first
            if (! str_starts_with($publicKey, 'pk_'))
            {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid public key format. Must start with pk_',
                ]);
            }

            if (! str_starts_with($secretKey, 'sk_'))
            {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid secret key format. Must start with sk_',
                ]);
            }

            // Test Stripe API with more comprehensive checks
            \Stripe\Stripe::setApiKey($secretKey);

            // Try multiple API calls to ensure credentials are valid
            $account = \Stripe\Account::retrieve();

            // Additional validation - try to list payment methods (requires valid keys)
            $paymentMethods = \Stripe\PaymentMethod::all(['limit' => 1]);

            // Try to create a test product (and immediately delete it)
            $testProduct = \Stripe\Product::create([
                'name' => 'Test Connection Product - Delete Me',
                'type' => 'service',
            ]);

            // Clean up test product
            \Stripe\Product::update($testProduct->id, ['active' => false]);

            $accountName = $account->display_name ?? $account->business_profile->name ?? 'Account';

            return response()->json([
                'success' => true,
                'message' => "Stripe connection successful! Account: {$accountName} ({$account->country})",
            ]);
        } catch (\Stripe\Exception\AuthenticationException $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Stripe authentication failed: Invalid API keys',
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Stripe request failed: '.$e->getMessage(),
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Stripe API error: '.$e->getMessage(),
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Stripe connection failed: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Test Twilio connection
     */
    public function testTwilioConnection(Team $team)
    {
        $this->authorize('update', $team);

        try
        {
            $config = $team->getTwilioConfig();

            if (empty($config['sid']) || empty($config['token']))
            {
                return response()->json([
                    'success' => false,
                    'message' => 'Twilio configuration is incomplete. Please configure SID and Token.',
                ]);
            }

            // Validate SID format
            if (! str_starts_with($config['sid'], 'AC'))
            {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Account SID format. Must start with AC',
                ]);
            }

            // Test Twilio API connection
            $twilio = new \Twilio\Rest\Client($config['sid'], $config['token']);

            // Retrieve account information to test credentials
            $account = $twilio->api->v2010->account->fetch();

            // Additional test - try to list incoming phone numbers (safe read operation)
            $phoneNumbers = $twilio->incomingPhoneNumbers->read(['limit' => 1]);

            // Check if account is active
            if ($account->status !== 'active')
            {
                return response()->json([
                    'success' => false,
                    'message' => "Twilio account status: {$account->status}. Account must be active.",
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Twilio connection successful! Account: {$account->friendlyName} ({$account->status})",
            ]);
        } catch (\Twilio\Exceptions\RestException $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Twilio API error: '.$e->getMessage(),
            ]);
        } catch (\Twilio\Exceptions\TwilioException $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Twilio connection failed: '.$e->getMessage(),
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Twilio test failed: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Show team shortcuts configuration
     */
    public function shortcuts(Team $team): \Illuminate\View\View
    {
        $this->authorize('update', $team);

        $shortcutsIconVisible = (bool) $team->getSetting('shortcuts_icon_visible', false);
        $savedShortcuts = $team->getSetting('team_shortcuts', []) ?? [];

        // Normalize legacy custom shortcuts (no type / enabled field)
        $savedShortcuts = array_map(function ($sc)
        {
            if (! isset($sc['type']))
            {
                $sc['type'] = 'custom';
            }

            if (($sc['type'] ?? '') === 'custom' && ! array_key_exists('enabled', $sc))
            {
                $sc['enabled'] = true;
            }

            return $sc;
        }, $savedShortcuts);

        // Inject any default shortcuts not yet stored so they appear in the UI (disabled)
        $availableDefaults = $this->getAvailableDefaultShortcuts($team);
        $savedDefaultKeys = array_column(
            array_filter($savedShortcuts, fn ($sc) => ($sc['type'] ?? '') === 'default'),
            'key',
        );

        foreach (array_keys($availableDefaults) as $key)
        {
            if (! in_array($key, $savedDefaultKeys, true))
            {
                $savedShortcuts[] = [
                    'type' => 'default',
                    'key' => $key,
                    'enabled' => TeamDefaultShortcuts::isEnabledByDefault($key, $team),
                ];
            }
        }

        $shortcuts = array_values($savedShortcuts);

        return view('team-settings.shortcuts', compact('team', 'shortcuts', 'shortcutsIconVisible', 'availableDefaults'));
    }

    /**
     * Store team shortcuts configuration
     */
    public function storeShortcuts(Request $request, Team $team): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $team);

        $request->validate([
            'shortcuts_icon_visible' => 'nullable|boolean',
            'shortcuts' => 'nullable|array|max:11',
            'shortcuts.*.type' => 'required|in:default,custom',
            'shortcuts.*.key' => 'nullable|string|max:50',
            'shortcuts.*.enabled' => 'nullable|boolean',
            'shortcuts.*.title' => 'nullable|string|max:50',
            'shortcuts.*.subtitle' => 'nullable|string|max:100',
            'shortcuts.*.url' => 'nullable|string|max:255',
            'shortcuts.*.icon' => 'nullable|string|max:50',
            'shortcuts.*.open_in_new_tab' => 'nullable|boolean',
            'shortcuts.*.order' => 'nullable|integer|min:0',
        ]);

        $team->setSetting('shortcuts_icon_visible', $request->boolean('shortcuts_icon_visible'), [
            'type' => 'boolean',
            'group' => 'shortcuts',
        ]);

        $raw = $request->input('shortcuts', []);
        $processed = [];

        foreach ($raw as $sc)
        {
            $type = $sc['type'] ?? 'custom';

            if ($type === 'default')
            {
                $processed[] = [
                    'type' => 'default',
                    'key' => $sc['key'],
                    'enabled' => (bool) ($sc['enabled'] ?? false),
                    'order' => (int) ($sc['order'] ?? 99),
                ];
            } else
            {
                if (! empty($sc['title']) && ! empty($sc['url']) && ! empty($sc['icon']))
                {
                    $processed[] = [
                        'type' => 'custom',
                        'title' => $sc['title'],
                        'subtitle' => $sc['subtitle'] ?? null,
                        'url' => $sc['url'],
                        'icon' => $sc['icon'],
                        'enabled' => (bool) ($sc['enabled'] ?? false),
                        'open_in_new_tab' => (bool) ($sc['open_in_new_tab'] ?? false),
                        'order' => (int) ($sc['order'] ?? 99),
                    ];
                }
            }
        }

        usort($processed, fn ($a, $b) => $a['order'] - $b['order']);

        $processed = array_map(function ($sc)
        {
            unset($sc['order']);

            return $sc;
        }, $processed);

        $team->setSetting('team_shortcuts', array_values($processed), [
            'type' => 'json',
            'group' => 'shortcuts',
        ]);

        return redirect()
            ->back()
            ->with('success', 'Team shortcuts updated successfully');
    }

    /**
     * Default system shortcuts available for all teams.
     *
     * @return array<string, array<string, string>>
     */
    private function getAvailableDefaultShortcuts(Team $team): array
    {
        return collect(TeamDefaultShortcuts::definitions())
            ->filter(fn (array $definition): bool => $team->hasModule($definition['module']))
            ->mapWithKeys(fn (array $definition, string $key): array => [
                $key => [
                    'title' => $definition['title'],
                    'subtitle' => $definition['subtitle'],
                    'icon' => $definition['icon'],
                ],
            ])
            ->all();
    }
}
