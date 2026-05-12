<?php

namespace App\Http\Controllers;

use App\DataTables\CampaignDataTable;
use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Helpers\DnsHelper;
use App\Http\Requests\UpdateCampaignRequest;
use App\Http\Requests\UpdateCampaignSequenceRequest;
use App\Models\Campaign;
use App\Models\Content;
use App\Models\Message;
use App\Models\MessageType;
use App\Models\Product;
use App\Models\SubscriptionProduct;
use App\Models\Template;
use App\Services\CampaignClassicEditorPersistence;
use App\Support\TemplateEditorReturnUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CampaignsController extends Controller
{
    public function __construct(
        private readonly CampaignClassicEditorPersistence $classicEditorPersistence,
    ) {}

    public function index(CampaignDataTable $dataTable): View|RedirectResponse|JsonResponse
    {
        if (! auth()->user()?->currentTeam)
        {
            return redirect()->route('error-without-team');
        }

        return $dataTable->render('campaigns.index', [
            'campaignTypes' => CampaignType::cases(),
        ]);
    }

    public function edit(Campaign $campaign): View|RedirectResponse
    {
        if (! auth()->user()?->currentTeam)
        {
            return redirect()->route('error-without-team');
        }

        $catalogProducts = Product::query()->active()->orderBy('name')->get(['id', 'name']);
        $subscriptionProducts = SubscriptionProduct::query()->active()->orderBy('name')->limit(500)->get(['id', 'name', 'recurring_interval']);
        $formContents = Content::query()
            ->where('status', 1)
            ->orderBy('order')
            ->orderBy('id')
            ->limit(500)
            ->get(['id', 'title']);

        $formContentsForSelect = $formContents->map(fn (Content $c): array => [
            'id' => $c->id,
            'label' => $this->contentPrimaryTitle($c),
        ]);

        return view('campaigns.edit', [
            'campaign' => $campaign,
            'storedTimezone' => old('send_time_zone', data_get($campaign->settings, 'send_time_zone', 'Europe/Madrid')),
            'catalogProducts' => $catalogProducts,
            'subscriptionProducts' => $subscriptionProducts,
            'formContentsForSelect' => $formContentsForSelect,
            'selectedOfferRefs' => old('exclude_offer_refs', $this->selectedOfferRefsFromCampaign($campaign)),
            'selectedContentIds' => array_map('intval', old('exclude_content_ids', data_get($campaign->settings, 'sequence_exclusions.content_ids', []) ?: [])),
        ]);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        if (! auth()->user()?->currentTeam)
        {
            return redirect()->route('error-without-team');
        }

        $validated = $request->validated();

        $settings = $campaign->settings ?? [];
        if (! empty($validated['send_time_zone']))
        {
            $settings['send_time_zone'] = $validated['send_time_zone'];
        }

        if ($request->boolean('sequence_exclusions_present'))
        {
            $splitRefs = $this->splitSequenceExclusionRefs($validated['exclude_offer_refs'] ?? []);
            $settings['sequence_exclusions'] = [
                'product_ids' => $splitRefs['product_ids'],
                'subscription_product_ids' => $splitRefs['subscription_product_ids'],
                'content_ids' => array_values(array_map('intval', $validated['exclude_content_ids'] ?? [])),
            ];
        }

        $campaign->update([
            'name' => $validated['title'],
            'settings' => $settings,
        ]);

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('success', __('Cambios guardados.'));
    }

    public function updateSequence(UpdateCampaignSequenceRequest $request, Campaign $campaign): RedirectResponse
    {
        if (! auth()->user()?->currentTeam)
        {
            return redirect()->route('error-without-team');
        }

        foreach ($request->validated('sequence') as $row)
        {
            $delay = $row['delay_minutes_after_previous'] ?? null;
            $campaign->messages()->updateExistingPivot((int) $row['message_id'], [
                'sort_order' => (int) $row['sort_order'],
                'delay_minutes_after_previous' => $delay === null || $delay === '' ? null : (int) $delay,
                'conditions' => $this->conditionPresetToPivotConditions($row['condition_preset'] ?? 'none'),
            ]);
        }

        if ($request->boolean('manage_automations'))
        {
            $automationRows = $this->automationRowsFromSequenceInput($request->validated('sequence') ?? []);
            $settings = $campaign->settings ?? [];
            $settings['automations'] = $this->normalizedAutomationsFromValidated($automationRows);

            $campaign->update([
                'settings' => $settings,
            ]);
        }

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('success', $request->boolean('manage_automations')
                ? __('Secuencia y automatizaciones guardadas.')
                : __('Secuencia actualizada.'));
    }

    public function show(Campaign $campaign): View|RedirectResponse
    {
        if (! auth()->user()?->currentTeam)
        {
            return redirect()->route('error-without-team');
        }

        $campaign->load([
            'messages' => function ($q): void
            {
                $q->select(
                    'messages.id',
                    'messages.name',
                    'messages.team_id',
                    'messages.min_hours_between_emails',
                    'messages.type_id',
                    'messages.template_id',
                    'messages.status_id',
                    'messages.started_at',
                )
                    ->with('type')
                    ->withCount([
                        'deliveries',
                        'deliveries as delivered_deliveries_count' => static function ($query): void
                        {
                            $query->whereNotNull('delivered_at');
                        },
                    ])
                    ->orderBy('campaign_message.sort_order')
                    ->orderBy('campaign_message.id');
            },
        ]);

        $messageTypes = MessageType::query()->where('status', 1)->orderBy('id')->get();
        $automationMessages = Message::query()->with('type')->orderBy('name')->get();

        $dnsStatus = class_exists(DnsHelper::class)
            ? DnsHelper::outgoingDnsStatusForAuthUser(auth()->user())
            : null;

        return view('campaigns.show', [
            'campaign' => $campaign,
            'deliveryStats' => $campaign->deliveryStatistics(),
            'automationsGroupedByStepMessageId' => $this->automationsGroupedByStepMessageId(
                data_get($campaign->settings, 'automations'),
                $campaign->messages,
            ),
            'messageTypes' => $messageTypes,
            'automationMessages' => $automationMessages,
            'dnsStatus' => $dnsStatus,
            'campaignSendToolbar' => $this->campaignSendToolbarContext($campaign, $dnsStatus),
        ]);
    }

    /**
     * Pause all linked messages for this campaign (same effect as pausing each message).
     */
    public function pauseMessages(Campaign $campaign): JsonResponse
    {
        try
        {
            $campaign->load('messages');

            foreach ($campaign->messages as $message)
            {
                if ($message->status_id)
                {
                    $message->update(['status_id' => 0]);
                }
            }

            $stored = CampaignStatus::tryFrom($campaign->status);
            if ($stored !== CampaignStatus::Sent && $stored !== CampaignStatus::Scheduled)
            {
                $campaign->update(['status' => CampaignStatus::Paused->value]);
            }

            return response()->json([
                'success' => true,
                'message' => __('Campaña pausada exitosamente.'),
            ]);
        } catch (\Throwable $e)
        {
            return response()->json([
                'success' => false,
                'message' => __('Error al pausar la campaña: :error', ['error' => $e->getMessage()]),
            ], 500);
        }
    }

    /**
     * @param  array<string, mixed>|null  $dnsStatus
     * @return array{
     *     first_message_id: int|null,
     *     can_send: bool,
     *     show_pause: bool,
     *     show_send_now: bool,
     *     show_recalculate: bool
     * }
     */
    private function campaignSendToolbarContext(Campaign $campaign, ?array $dnsStatus): array
    {
        $firstMessage = $campaign->messages->first();

        $usingSystemSmtp = auth()->user()->currentTeam->isUsingSystemSmtp();
        $canSend = DnsHelper::canSendBroadcastFromUi($dnsStatus, $usingSystemSmtp);

        $showPause = false;
        $hasDeliveriesPending = false;

        foreach ($campaign->messages as $message)
        {
            $totalDeliveries = (int) $message->deliveries_count;
            $deliveredCount = (int) $message->delivered_deliveries_count;

            if ($totalDeliveries > $deliveredCount)
            {
                $hasDeliveriesPending = true;
            }

            if (Campaign::messageIsOperationalForToolbar($message))
            {
                $showPause = true;
            }
        }

        return [
            'first_message_id' => $firstMessage?->id,
            'can_send' => $canSend,
            'show_pause' => $showPause,
            'show_send_now' => $firstMessage !== null && ! $showPause,
            'show_recalculate' => $showPause && $hasDeliveriesPending,
        ];
    }

    public function selectTemplate(Request $request): View
    {
        $selectedType = $request->string('type')->toString();
        $selectedTitle = $request->string('title')->toString();
        $campaignForContext = null;
        $campaignIdParam = $request->integer('campaign_id');
        if ($campaignIdParam > 0)
        {
            $campaignForContext = Campaign::query()->whereKey($campaignIdParam)->first();
        }

        $templateDefinitions = $this->getCampaignTemplateDefinitions();
        $templatesByLegacyId = $this->syncCampaignTemplatesToDatabase($templateDefinitions);

        $curatedTemplateIds = collect($templatesByLegacyId)
            ->filter(fn ($template): bool => $template instanceof Template)
            ->pluck('id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $teamId = auth()->user()?->currentTeam?->id;
        $userTemplates = [];

        if ($teamId !== null)
        {
            $userTemplatesQuery = Template::withoutGlobalScopes()
                ->where('team_id', $teamId)
                ->orderByDesc('updated_at')
                ->orderByDesc('id');

            if ($curatedTemplateIds !== [])
            {
                $userTemplatesQuery->whereNotIn('id', $curatedTemplateIds);
            }

            $userTemplates = $userTemplatesQuery->get()->map(function (Template $template): array
            {
                $label = Str::limit($template->name, 48, '');
                $placeholder = 'https://placehold.co/640x360/f8f9fa/adb5bd?text='.rawurlencode($label);

                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => __('app.campaign_select_user_template_description'),
                    'preview' => $placeholder,
                    'full_preview' => $placeholder,
                ];
            })->values()->all();
        }

        $selectedTypeLabel = match ($selectedType)
        {
            'sequences' => __('Secuencia de correo'),
            'messages' => __('Mensaje / newsletter'),
            default => __('Difusión por correo'),
        };

        return view('campaigns.templates-select', [
            'selectedType' => $selectedType,
            'selectedTypeLabel' => $selectedTypeLabel,
            'selectedTitle' => $selectedTitle,
            'selectedCampaignId' => $campaignForContext?->id ?? 0,
            'contextCampaignName' => $campaignForContext?->name,
            'customTemplates' => array_values(array_map(function (array $definition) use ($templatesByLegacyId): array
            {
                $template = $templatesByLegacyId[$definition['legacy_id']] ?? null;

                return [
                    'id' => $template?->id,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'preview' => $definition['preview'],
                    'full_preview' => $definition['full_preview'],
                ];
            }, array_filter($templateDefinitions, fn (array $item): bool => $item['group'] === 'custom'))),
            'kajabiTemplates' => array_values(array_map(function (array $definition) use ($templatesByLegacyId): array
            {
                $template = $templatesByLegacyId[$definition['legacy_id']] ?? null;

                return [
                    'id' => $template?->id,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'preview' => $definition['preview'],
                    'full_preview' => $definition['full_preview'],
                ];
            }, array_filter($templateDefinitions, fn (array $item): bool => $item['group'] === 'kajabi'))),
            'userTemplates' => $userTemplates,
        ]);
    }

    public function classicEditor(Request $request): View
    {
        return view('campaigns.classic-editor', $this->buildClassicEditorData($request));
    }

    public function classicEditorGrapes(Request $request): View
    {
        return view('campaigns.classic-editor-grapes', $this->buildClassicEditorData($request));
    }

    public function storeClassicEditor(Request $request): RedirectResponse
    {
        $user = auth()->user();
        if (! $user?->currentTeam)
        {
            return redirect()->route('error-without-team');
        }

        $validated = [];
        $ids = ['campaign_id' => 0, 'message_id' => 0];

        try
        {
            $validated = $request->validate([
                'intent' => ['required', 'string', 'in:save,save_next'],
                'type' => ['nullable', 'string', 'max:40'],
                'title' => ['nullable', 'string', 'max:500'],
                'template_id' => ['nullable', 'integer', 'min:0'],
                'campaign_id' => ['nullable', 'integer', 'min:0'],
                'message_id' => ['nullable', 'integer', 'min:0'],
                'subject' => ['nullable', 'string', 'max:140'],
                'preview_text' => ['nullable', 'string', 'max:140'],
                'internal_title' => ['nullable', 'string', 'max:255'],
                'body' => ['nullable', 'string'],
            ]);

            $ids = $this->classicEditorPersistence->persist($user, $validated);
        } catch (ValidationException $e)
        {
            return redirect()
                ->route('campaigns.classic-editor', $request->only(['type', 'title', 'template_id', 'campaign_id', 'message_id']))
                ->withErrors($e->errors())
                ->withInput();
        }

        if (($validated['intent'] ?? '') === 'save')
        {
            $campaign = Campaign::query()->findOrFail($ids['campaign_id']);

            return redirect()
                ->route('campaigns.show', $campaign)
                ->with('success', __('Borrador guardado.'));
        }

        $query = array_filter(
            [
                'type' => $validated['type'] ?? null,
                'title' => $validated['title'] ?? null,
                'template_id' => isset($validated['template_id']) && (int) $validated['template_id'] > 0
                    ? (int) $validated['template_id']
                    : null,
                'campaign_id' => $ids['campaign_id'] > 0 ? $ids['campaign_id'] : null,
            ],
            fn ($value) => $value !== null && $value !== '',
        );

        $query['message_id'] = $ids['message_id'];

        $prefill = [
            'internal_title' => (string) ($validated['internal_title'] ?? ''),
            'subject' => (string) ($validated['subject'] ?? ''),
            'preview_text' => (string) ($validated['preview_text'] ?? ''),
        ];

        return redirect()
            ->route('campaigns.classic-editor', $query)
            ->with('status', __('Paso guardado. Sigues en este correo; puedes añadir el siguiente cuando quieras.'))
            ->with('classic_editor_prefill', $prefill);
    }

    private function buildClassicEditorData(Request $request): array
    {
        $selectedType = $request->string('type')->toString();
        $selectedTitle = $request->string('title')->toString();
        $selectedTemplateId = $request->integer('template_id');
        $campaignId = $request->integer('campaign_id');
        $messageId = $request->integer('message_id');
        $prefill = $request->session()->get('classic_editor_prefill', []);

        $defaultInternalTitle = $prefill['internal_title'] ?? ($selectedTitle !== '' ? $selectedTitle : ($selectedType === 'sequences' ? 'Correo de secuencia' : 'Correo de difusión'));
        $defaultSubject = $prefill['subject'] ?? ($selectedTitle !== '' ? 'Actualización: '.$selectedTitle : 'Asunto');
        $defaultPreviewText = $prefill['preview_text'] ?? 'Descubre los detalles y próximos pasos de esta campaña.';
        $templateDefinitions = $this->getCampaignTemplateDefinitions();
        $templatesByLegacyId = $this->syncCampaignTemplatesToDatabase($templateDefinitions);
        $selectedTemplate = Template::withoutGlobalScopes()->find($selectedTemplateId);
        $selectedDefinition = collect($templateDefinitions)->first(function (array $definition) use ($selectedTemplate): bool
        {
            return $selectedTemplate instanceof Template && $definition['name'] === $selectedTemplate->name;
        });
        if (! is_array($selectedDefinition))
        {
            $selectedDefinition = [
                'name' => $selectedTemplate?->name ?? 'Plantilla personalizada',
                'full_preview' => 'https://placehold.co/1300x1800/f8f9fa/adb5bd?text=Plantilla+Email',
            ];
        }

        $defaultBodyShell = $this->buildTemplateHtmlShell($selectedDefinition);
        $defaultBodyContent = $this->defaultEmailCanvasInnerHtml();
        $defaultBodyTemplate = $defaultBodyShell;
        $defaultBody = str_replace('__EMAIL_BODY__', $defaultBodyContent, $defaultBodyShell);
        if ($selectedTemplate instanceof Template)
        {
            $selectedTemplate = $this->ensureTemplateHasGjsStructure($selectedTemplate, $defaultBody);
        }
        $storedBody = is_array($selectedTemplate?->gjs_data) ? ($selectedTemplate->gjs_data['html'] ?? null) : null;
        if (is_string($storedBody) && $storedBody !== '')
        {
            $defaultBody = $storedBody;
            $defaultBodyContent = $this->extractEditableRegionFromMergedTemplate($storedBody);
        }

        if ($messageId > 0 && auth()->user()?->current_team_id)
        {
            $existingMessage = Message::withoutGlobalScopes()
                ->where('team_id', auth()->user()->current_team_id)
                ->where('id', $messageId)
                ->first();
            if ($existingMessage)
            {
                if (($prefill['internal_title'] ?? '') === '')
                {
                    $defaultInternalTitle = $existingMessage->name;
                }
                $trimmedText = trim($existingMessage->text);
                if (($prefill['preview_text'] ?? '') === '' && $trimmedText !== '')
                {
                    $defaultPreviewText = Str::limit($trimmedText, 140, '');
                }
            }
        }

        $grapesEditorUrl = '#';
        $templateHashedIdForDuplicate = null;
        $classicEditorReturnUrl = $request->fullUrl();
        if ($selectedTemplate instanceof Template)
        {
            $grapesEditorUrl = TemplateEditorReturnUrl::editorRouteWithReturn(
                route('template.editor', $selectedTemplate->getHashedId()),
                $classicEditorReturnUrl,
            );
            $templateHashedIdForDuplicate = $selectedTemplate->getHashedId();
        } elseif ($selectedTemplateId > 0)
        {
            foreach ($templatesByLegacyId as $legacyTemplate)
            {
                if ($legacyTemplate->id === $selectedTemplateId)
                {
                    $grapesEditorUrl = TemplateEditorReturnUrl::editorRouteWithReturn(
                        route('template.editor', $legacyTemplate->getHashedId()),
                        $classicEditorReturnUrl,
                    );
                    $templateHashedIdForDuplicate = $legacyTemplate->getHashedId();
                    break;
                }
            }
        }

        return [
            'selectedType' => $selectedType,
            'isSequenceCampaign' => $selectedType === 'sequences',
            'selectedTypeLabel' => $selectedType === 'sequences' ? 'Secuencia de correo' : 'Difusión por correo',
            'selectedTitle' => $selectedTitle,
            'selectedTemplateName' => $selectedDefinition['name'] ?? '',
            'selectedTemplateId' => $selectedTemplateId,
            'campaignId' => $campaignId,
            'messageId' => $messageId,
            'grapesEditorUrl' => $grapesEditorUrl,
            'templateHashedIdForDuplicate' => $templateHashedIdForDuplicate,
            'defaultInternalTitle' => $defaultInternalTitle,
            'defaultSubject' => $defaultSubject,
            'defaultPreviewText' => $defaultPreviewText,
            'defaultBodyContent' => $defaultBodyContent,
            'defaultBodyTemplate' => $defaultBodyTemplate,
            'defaultBody' => $defaultBody,
        ];
    }

    private function getCampaignTemplateDefinitions(): array
    {
        return [
            [
                'legacy_id' => 1,
                'group' => 'custom',
                'name' => 'Nova Bienvenida',
                'description' => 'Onboarding y primer contacto con estilo editorial limpio; perfecta para altas y confirmaciones que generan confianza.',
                'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_bridge/thumbnail.jpg',
                'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_bridge/full.jpg',
            ],
            [
                'legacy_id' => 2,
                'group' => 'custom',
                'name' => 'Pulse Editorial',
                'description' => 'Boletines y actualizaciones frecuentes con cabecera que destaca contenido largo entrevistas, cursos y artículos.',
                'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_boardwell/thumbnail.jpg',
                'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_boardwell/full.jpg',
            ],
            [
                'legacy_id' => 3,
                'group' => 'custom',
                'name' => 'Stripe Promo',
                'description' => 'Lanzamientos rápidos y ofertas con foco visual; ideal para flash sales y promos donde la imagen manda.',
                'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_strum/thumbnail.jpg',
                'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_strum/full.jpg',
            ],
            ['legacy_id' => 101, 'group' => 'kajabi', 'name' => 'Squiggle', 'description' => 'Let your copy shine with this uncomplicated template.', 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_squiggle/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_squiggle/full.jpg'],
            ['legacy_id' => 102, 'group' => 'kajabi', 'name' => 'Slice', 'description' => "Who says templates can't be playful? Use this one when you want to welcome new subscribers with a fun twist.", 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_slice/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_slice/full.jpg'],
            ['legacy_id' => 103, 'group' => 'kajabi', 'name' => 'Timber', 'description' => 'A minimal, earthy template perfect for newsletter updates.', 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_timber/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_timber/full.jpg'],
            ['legacy_id' => 104, 'group' => 'kajabi', 'name' => 'Brush', 'description' => "This template's prominent header helps you showcase your message with style.", 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_brush/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_brush/full.jpg'],
            ['legacy_id' => 105, 'group' => 'kajabi', 'name' => 'Mocha', 'description' => "Make a splash with this clean, simple email template that's perfect for sending content updates.", 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_mocha/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_mocha/full.jpg'],
            ['legacy_id' => 106, 'group' => 'kajabi', 'name' => 'Strum', 'description' => 'This minimal, image-focused template is perfect for sending promotions.', 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_strum/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_strum/full.jpg'],
            ['legacy_id' => 107, 'group' => 'kajabi', 'name' => 'Bridge', 'description' => 'Give your audience a warm welcome with this simple yet refined signup confirmation template.', 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_bridge/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_bridge/full.jpg'],
            ['legacy_id' => 108, 'group' => 'kajabi', 'name' => 'Boardwell', 'description' => 'Send your latest interviews, courses, blog posts and other content in a beautiful, attractive template.', 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_boardwell/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_boardwell/full.jpg'],
            ['legacy_id' => 109, 'group' => 'kajabi', 'name' => 'Ballast', 'description' => 'A great template to use when you need to grab attention with striking visuals and video.', 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_ballast/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_ballast/full.jpg'],
            ['legacy_id' => 110, 'group' => 'kajabi', 'name' => 'Stem', 'description' => 'Use this lively, image-based template to keep your fans in the loop.', 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_stem/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_stem/full.jpg'],
            ['legacy_id' => 111, 'group' => 'kajabi', 'name' => 'Myriad', 'description' => 'A quick and bright template that you can craft to fit any purpose.', 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_myriad/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_myriad/full.jpg'],
            ['legacy_id' => 112, 'group' => 'kajabi', 'name' => 'Climb', 'description' => 'Customize this highly-versatile template to suit any need for your growing business.', 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_climb/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_climb/full.jpg'],
            ['legacy_id' => 113, 'group' => 'kajabi', 'name' => 'Make a Referral', 'description' => 'Personaliza, enlaza tu invitación o beneficio para referidos y envía en segundos.', 'preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_referral/thumbnail.jpg', 'full_preview' => 'https://kajabi-storefronts-production.kajabi-cdn.com/kajabi-storefronts-production/canonical_themes/presets/encore_email_referral/full.jpg'],
        ];
    }

    private function syncCampaignTemplatesToDatabase(array $definitions): array
    {
        $templatesByLegacyId = [];

        foreach ($definitions as $definition)
        {
            $initialHtml = $this->buildTemplateHtmlFromDefinition($definition);
            $teamId = auth()->check() ? auth()->user()?->currentTeam?->id : null;
            $template = Template::withoutGlobalScopes()->where('name', $definition['name'])->first();
            if (! $template)
            {
                $template = Template::withoutEvents(function () use ($definition, $teamId, $initialHtml)
                {
                    return Template::withoutGlobalScopes()->create([
                        'name' => $definition['name'],
                        'team_id' => $teamId,
                        'status_id' => 1,
                        'gjs_data' => $this->buildDefaultGjsData($initialHtml),
                    ]);
                });
            }

            $template = $this->ensureTemplateHasGjsStructure($template, $initialHtml);

            $templatesByLegacyId[$definition['legacy_id']] = $template;
        }

        return $templatesByLegacyId;
    }

    private function defaultEmailCanvasInnerHtml(): string
    {
        return '<p style="margin:0;color:#777777;font-size:14px;line-height:1.5;font-weight:300;">&nbsp;</p>';
    }

    /**
     * Email layout shell: hero image + editable region marker + footer. No campaign copy or template branding.
     */
    private function buildTemplateHtmlShell(array $definition): string
    {
        $fullPreview = $definition['full_preview'] ?? '';
        $nameForAlt = htmlspecialchars($definition['name'] ?? 'Email', ENT_QUOTES | ENT_HTML5);

        return <<<HTML
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb;padding:24px 0;font-family:Arial,sans-serif;">
  <tr>
    <td align="center">
      <table width="640" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:10px;overflow:hidden;">
        <tr>
          <td>
            <img src="{$fullPreview}" alt="{$nameForAlt}" width="640" style="display:block;width:100%;height:auto;">
          </td>
        </tr>
        <tr>
          <td style="padding:28px;">
            __EMAIL_BODY__
            <hr style="border:none;border-top:1px solid #eceef2;margin:26px 0;">
            <p style="margin:0;color:#6c757d;font-size:13px;">Equipo Humano</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
HTML;
    }

    private function buildTemplateHtmlFromDefinition(array $definition): string
    {
        return str_replace(
            '__EMAIL_BODY__',
            $this->defaultEmailCanvasInnerHtml(),
            $this->buildTemplateHtmlShell($definition),
        );
    }

    private function buildDefaultGjsData(string $html): array
    {
        return [
            'components' => $this->htmlToGrapesComponents($html),
            'styles' => '[]',
            'css' => '* { box-sizing: border-box; } body { margin: 0; }',
            'html' => $html,
        ];
    }

    private function ensureTemplateHasGjsStructure(Template $template, string $fallbackHtml): Template
    {
        $current = is_array($template->gjs_data) ? $template->gjs_data : [];
        $currentHtml = $current['html'] ?? $fallbackHtml;
        $components = $current['components'] ?? null;
        $styles = $current['styles'] ?? null;
        $css = $current['css'] ?? null;

        $isTriviallyEmptyHtml = is_string($currentHtml)
            && (str_contains($currentHtml, '<body></body>') || str_contains($currentHtml, '<body> </body>'));
        $isTriviallyEmptyComponents = is_string($components)
            && (str_contains($components, '<body></body>') || str_contains($components, '<body> </body>'));
        $isHtmlInsteadOfComponents = is_string($components) && str_contains($components, '<table');

        $hasLegacyCampaignStarterMarkup = is_string($currentHtml) && (
            str_contains($currentHtml, 'Este correo se creó con la plantilla')
            || str_contains($currentHtml, 'Reemplaza este contenido por tu mensaje')
        );

        $needsUpdate = ! is_string($components) || trim($components) === '' || trim($components) === '[]';
        $needsUpdate = $needsUpdate || ! is_string($currentHtml) || trim($currentHtml) === '';
        $needsUpdate = $needsUpdate || ! is_string($styles) || trim($styles) === '';
        $needsUpdate = $needsUpdate || ! is_string($css) || trim($css) === '';
        $needsUpdate = $needsUpdate || $isTriviallyEmptyHtml || $isTriviallyEmptyComponents;
        $needsUpdate = $needsUpdate || $isHtmlInsteadOfComponents;
        $needsUpdate = $needsUpdate || $hasLegacyCampaignStarterMarkup;

        if (! $needsUpdate)
        {
            return $template;
        }

        $useFallbackMarkup = $isTriviallyEmptyHtml || $hasLegacyCampaignStarterMarkup;
        $normalizedHtml = $useFallbackMarkup ? $fallbackHtml : $currentHtml;
        $normalizedComponents = ($isTriviallyEmptyComponents || $hasLegacyCampaignStarterMarkup)
            ? $fallbackHtml
            : $components;

        $template->update([
            'gjs_data' => [
                'components' => $this->normalizeComponentsValue($normalizedComponents, $normalizedHtml),
                'styles' => is_string($styles) && trim($styles) !== '' ? $styles : '[]',
                'css' => is_string($css) && trim($css) !== '' ? $css : '* { box-sizing: border-box; } body { margin: 0; }',
                'html' => $normalizedHtml,
            ],
        ]);

        return $template->fresh();
    }

    private function normalizeComponentsValue(mixed $components, string $fallbackHtml): string
    {
        if (is_string($components) && trim($components) !== '')
        {
            $decoded = json_decode($components, true);
            if (is_array($decoded))
            {
                return $components;
            }
        }

        if (is_array($components))
        {
            return json_encode($components) ?: '[]';
        }

        return $this->htmlToGrapesComponents($fallbackHtml);
    }

    private function htmlToGrapesComponents(string $html): string
    {
        $normalizedHtml = trim($html) !== '' ? $html : '<table><tr><td></td></tr></table>';
        $components = [
            [
                'type' => 'wrapper',
                'components' => [
                    [
                        'type' => 'text',
                        'content' => $normalizedHtml,
                    ],
                ],
            ],
        ];

        return json_encode($components) ?: '[]';
    }

    private function extractEditableRegionFromMergedTemplate(string $mergedHtml): string
    {
        if (preg_match(
            '/<td\s+style="padding:28px;">\s*(.+?)\s*<hr\b/is',
            $mergedHtml,
            $matches,
        ))
        {
            return trim($matches[1]);
        }

        return $this->defaultEmailCanvasInnerHtml();
    }

    /**
     * @param  array<int, array<string, mixed>>  $sequenceRows
     * @return array<int, array<string, mixed>>
     */
    private function automationRowsFromSequenceInput(array $sequenceRows): array
    {
        $rows = [];
        foreach ($sequenceRows as $seqRow)
        {
            if (! is_array($seqRow))
            {
                continue;
            }
            $stepMessageId = (int) ($seqRow['message_id'] ?? 0);
            if ($stepMessageId < 1)
            {
                continue;
            }

            $automationsList = $seqRow['automations'] ?? null;
            if ($automationsList === null && isset($seqRow['automation']) && is_array($seqRow['automation']))
            {
                $legacy = $seqRow['automation'];
                $automationsList = $this->stepAutomationRowHasPayload($legacy) ? [$legacy] : [];
            }
            if (! is_array($automationsList))
            {
                $automationsList = [];
            }

            foreach ($automationsList as $auto)
            {
                if (! is_array($auto) || ! $this->stepAutomationRowHasPayload($auto))
                {
                    continue;
                }
                $row = $auto;
                $row['step_message_id'] = $stepMessageId;
                if (array_key_exists('linked_message_id', $row))
                {
                    if (filled($row['linked_message_id']))
                    {
                        $row['message_id'] = (int) $row['linked_message_id'];
                    }
                    unset($row['linked_message_id']);
                }

                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function stepAutomationRowHasPayload(array $row): bool
    {
        return filled($row['trigger'] ?? null)
            || filled($row['channel_type_id'] ?? null)
            || filled($row['linked_message_id'] ?? null)
            || filled($row['notes'] ?? null)
            || filled($row['delay_hours'] ?? null);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Message>  $orderedMessages
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function automationsGroupedByStepMessageId(mixed $stored, $orderedMessages): array
    {
        $groups = [];
        if (! is_array($stored))
        {
            return $groups;
        }

        $legacyFlat = [];
        foreach ($stored as $row)
        {
            if (! is_array($row))
            {
                continue;
            }
            $sid = (int) ($row['step_message_id'] ?? 0);
            if ($sid > 0)
            {
                $payload = $row;
                unset($payload['step_message_id']);
                if (! isset($groups[$sid]))
                {
                    $groups[$sid] = [];
                }
                $groups[$sid][] = $payload;

                continue;
            }
            $legacyFlat[] = $row;
        }

        foreach ($legacyFlat as $i => $row)
        {
            $message = $orderedMessages->get($i);
            if ($message === null)
            {
                break;
            }
            $mid = (int) $message->id;
            if (! isset($groups[$mid]))
            {
                $groups[$mid] = [];
            }
            $clean = $row;
            unset($clean['step_message_id']);
            $groups[$mid][] = $clean;
        }

        return $groups;
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizedAutomationsFromValidated(array $rows): array
    {
        $out = [];
        foreach ($rows as $row)
        {
            if (! is_array($row))
            {
                continue;
            }
            $trigger = $row['trigger'] ?? null;
            $channelTypeId = $row['channel_type_id'] ?? null;
            if (! filled($trigger) || ! filled($channelTypeId))
            {
                continue;
            }
            $item = [
                'trigger' => (string) $trigger,
                'channel_type_id' => (int) $channelTypeId,
            ];
            if (filled($row['delay_hours'] ?? null))
            {
                $item['delay_hours'] = (int) $row['delay_hours'];
            }
            if (filled($row['message_id'] ?? null))
            {
                $item['message_id'] = (int) $row['message_id'];
            }
            if (filled($row['notes'] ?? null))
            {
                $item['notes'] = (string) $row['notes'];
            }
            if (filled($row['step_message_id'] ?? null))
            {
                $item['step_message_id'] = (int) $row['step_message_id'];
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @return array<string, string>|null
     */
    private function conditionPresetToPivotConditions(string $preset): ?array
    {
        return match ($preset)
        {
            'opened' => ['require_previous' => 'opened'],
            'clicked' => ['require_previous' => 'clicked'],
            default => null,
        };
    }

    /**
     * @return array{product_ids: array<int, int>, subscription_product_ids: array<int, int>}
     */
    private function splitSequenceExclusionRefs(array $refs): array
    {
        $productIds = [];
        $subscriptionIds = [];

        foreach ($refs as $ref)
        {
            if (! is_string($ref))
            {
                continue;
            }
            if (preg_match('/^product:(\d+)$/', $ref, $m))
            {
                $productIds[] = (int) $m[1];

                continue;
            }
            if (preg_match('/^subscription:(\d+)$/', $ref, $m))
            {
                $subscriptionIds[] = (int) $m[1];
            }
        }

        return [
            'product_ids' => array_values(array_unique($productIds)),
            'subscription_product_ids' => array_values(array_unique($subscriptionIds)),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function selectedOfferRefsFromCampaign(Campaign $campaign): array
    {
        $refs = [];
        $productIds = data_get($campaign->settings, 'sequence_exclusions.product_ids', []);
        $subscriptionIds = data_get($campaign->settings, 'sequence_exclusions.subscription_product_ids', []);

        if (is_array($productIds))
        {
            foreach ($productIds as $id)
            {
                $refs[] = 'product:'.(int) $id;
            }
        }
        if (is_array($subscriptionIds))
        {
            foreach ($subscriptionIds as $id)
            {
                $refs[] = 'subscription:'.(int) $id;
            }
        }

        return $refs;
    }

    private function contentPrimaryTitle(Content $content): string
    {
        $title = $content->title;
        if (! is_array($title))
        {
            $s = trim((string) $title);

            return $s !== '' ? $s : '—';
        }

        foreach ($title as $value)
        {
            if (is_string($value) && trim($value) !== '')
            {
                return $value;
            }
        }

        return '—';
    }
}
