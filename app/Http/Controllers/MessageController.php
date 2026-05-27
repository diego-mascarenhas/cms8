<?php

namespace App\Http\Controllers;

use App\DataTables\MessageDataTable;
use App\Enums\CampaignStatus;
use App\Enums\MessageDeliverySendProfile;
use App\Helpers\GrapesJsHelper;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\SyncMessageTemplateHtmlForEditorRequest;
use App\Http\Requests\TestMessageFromTemplateRequest;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\MessageDeliveryLink;
use App\Models\MessageDeliveryStat;
use App\Models\MessageType;
use App\Models\Team;
use App\Models\Template;
use App\Models\User;
use App\Services\MessageDeliveryDispatcher;
use App\Services\MessageFormTemplateResolver;
use App\Support\MessageTemplateMergeFields;
use App\Support\TemplateEditorReturnUrl;
use App\Traits\ConfiguresTeamMail;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;
use Twilio\Rest\Client;

class MessageController extends Controller
{
    use ConfiguresTeamMail;

    public function index(MessageDataTable $dataTable)
    {
        return $dataTable->render('message.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $data = new stdClass;
        $data->templates = Template::getOptions();
        $data->contactStatuses = \App\Models\ContactStatus::getOptions();

        if ($request->integer('template_id') > 0)
        {
            $template = Template::query()->whereKey($request->integer('template_id'))->first();

            if (! $template)
            {
                return redirect()
                    ->route('message.create')
                    ->with('error', __('La plantilla seleccionada no está disponible.'));
            }

            $data->template_id = $template->id;
            $data->template = $template;
            $data->name = old('name', $request->string('name')->toString());
            $data->type_id = old('type_id', 1);
            $data->text = old('text', __('Boletín por correo'));
        }

        $previewContext = $this->prepareMessageFormTemplatePreview($data, $request);

        return view('message.form', array_merge(compact('data'), $previewContext));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMessageRequest $request)
    {
        $validated = $request->validated();
        $data = $request->except([
            'id',
            '_token',
            'save_intent',
            'send_allowed_weekdays',
            'send_window_start',
            'send_window_end',
            'schedule_send_at',
        ]);

        $saveIntent = (string) $request->input('save_intent', 'save');
        if (! in_array($saveIntent, ['save', 'save_send', 'save_schedule'], true))
        {
            $saveIntent = 'save';
        }

        $scheduledSendAt = null;
        if ($saveIntent === 'save_schedule' && filled($validated['schedule_send_at'] ?? null))
        {
            $scheduledSendAt = Carbon::parse($validated['schedule_send_at'], config('app.timezone'))->utc();
        }

        $weekdaysSorted = array_values(array_unique(array_map('intval', $validated['send_allowed_weekdays'])));
        sort($weekdaysSorted);
        $sendAllowedWeekdays = $weekdaysSorted === range(1, 7)
            ? null
            : $weekdaysSorted;

        $sendWindowStart = filled($validated['send_window_start'] ?? null)
            ? $validated['send_window_start']
            : null;
        $sendWindowEnd = filled($validated['send_window_end'] ?? null)
            ? $validated['send_window_end']
            : null;

        $resolvedTypeId = $this->resolveTypeIdForMessageStore($request);

        $templateId = filled($data['template_id'] ?? null) ? (int) $data['template_id'] : null;
        if ($resolvedTypeId === $this->whatsappMessageTypeId())
        {
            $templateId = null;
        }

        $status_id = $request->boolean('status_id') ? 1 : 0;

        $show_unsubscribe = $request->boolean('show_unsubscribe') ? 1 : 0;
        $enable_open_tracking = $request->boolean('enable_open_tracking') ? 1 : 0;
        $enable_click_tracking = $request->boolean('enable_click_tracking') ? 1 : 0;

        $rawMinHours = isset($validated['min_hours_between_emails'])
            ? $validated['min_hours_between_emails']
            : $request->input('min_hours_between_emails', 48);

        $minHours = max(0, (int) round((float) $rawMinHours));

        $messageModel = null;

        $messageIdForGate = $request->filled('id') ? (int) $request->id : null;
        $hasDeliveries = $messageIdForGate > 0
            && MessageDelivery::query()->where('message_id', $messageIdForGate)->exists();

        $mailHtml = null;
        if ($resolvedTypeId === 1 && $templateId !== null && $templateId > 0 && ! $hasDeliveries)
        {
            $mailHtml = $this->resolveMailHtmlFromRequest($request, $templateId);
        }

        DB::transaction(function () use ($request, $validated, $data, $templateId, $resolvedTypeId, $status_id, $show_unsubscribe, $enable_open_tracking, $enable_click_tracking, $minHours, $sendAllowedWeekdays, $sendWindowStart, $sendWindowEnd, $scheduledSendAt, $mailHtml, $hasDeliveries, &$messageModel): void
        {
            $payload = [
                'name' => $validated['name'],
                'type_id' => $resolvedTypeId,
                'contact_status_id' => filled($data['contact_status_id'] ?? null) ? (int) $data['contact_status_id'] : null,
                'template_id' => $templateId,
                'text' => $validated['text'],
                'status_id' => $status_id,
                'show_unsubscribe' => $show_unsubscribe,
                'enable_open_tracking' => $enable_open_tracking,
                'enable_click_tracking' => $enable_click_tracking,
                'min_hours_between_emails' => max(0, $minHours),
                'send_allowed_weekdays' => $sendAllowedWeekdays,
                'send_window_start' => $sendWindowStart,
                'send_window_end' => $sendWindowEnd,
                'scheduled_send_at' => $scheduledSendAt,
            ];

            if ($mailHtml !== null)
            {
                if (Schema::hasColumn('messages', 'mail_html'))
                {
                    $payload['mail_html'] = $mailHtml;
                } elseif ($templateId !== null && $templateId > 0)
                {
                    $this->persistTemplateHtmlToTemplateModel($templateId, $mailHtml);
                }
            }

            $messageModel = Message::updateOrCreate(
                ['id' => $request->id],
                $payload,
            );

            if (! $hasDeliveries)
            {
                $messageModel->syncMessageCategories($data['message_category_ids'] ?? []);
            }
        });

        $messageId = (int) $messageModel->id;

        if ($saveIntent === 'save_send')
        {
            $message = Message::with(['deliveries', 'team.settings', 'campaigns'])->findOrFail($messageId);
            $activation = $this->attemptActivateMessageCampaign($message);
            if (! $activation['success'])
            {
                return redirect()->route('message.edit', $messageId)->withInput()->withErrors(['save_intent' => $activation['message']]);
            }

            return redirect()->route('message.show', $messageId)->with('success', __('app.message_save_send_success'));
        }

        if ($saveIntent === 'save_schedule')
        {
            $messageModel->refresh();
            $dtLabel = $messageModel->scheduled_send_at
                ? $messageModel->scheduled_send_at->clone()->timezone(config('app.timezone'))->locale(app()->getLocale())->translatedFormat('d M Y H:i')
                : '';

            return redirect()->route('message.index')->with('success', __('app.message_save_schedule_success', ['datetime' => $dtLabel]));
        }

        return redirect()->route('message.index')->with('success', 'Record saved successfully.');
    }

    /**
     * Debug deliveries status (temporary route)
     */
    public function debug(string $id)
    {
        $message = Message::with(['deliveries', 'team.settings'])->findOrFail($id);
        $team = auth()->user()->currentTeam;
        if ($team && ! $team->relationLoaded('settings'))
        {
            $team->load('settings');
        }

        // Get delivery statistics
        $stats = [
            'total' => MessageDelivery::where('message_id', $id)->count(),
            'pending' => MessageDelivery::where('message_id', $id)->where('status_id', 1)->count(),
            'sent' => MessageDelivery::where('message_id', $id)->whereNotNull('sent_at')->count(),
            'delivered' => MessageDelivery::where('message_id', $id)->whereNotNull('delivered_at')->count(),
            'failed' => MessageDelivery::where('message_id', $id)->where('status_id', 4)->count(),
        ];

        // Get last 5 failed deliveries
        $failedDeliveries = MessageDelivery::where('message_id', $id)
            ->where('status_id', 4)
            ->with('contact')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($d)
            {
                return [
                    'id' => $d->id,
                    'contact' => $d->contact ? $d->contact->email : 'N/A',
                    'error_type' => $d->error_type ?: 'NULL',
                    'error_message' => $d->error_message ?: ($d->provider_data['error'] ?? 'NULL'),
                    'updated_at' => $d->updated_at->format('Y-m-d H:i:s'),
                ];
            });

        // Get queue status
        $connection = MessageDelivery::query()->getModel()->getConnection();
        $queueStatus = [
            'failed_jobs_count' => $connection->table('failed_jobs')->count(),
            'jobs_count' => $connection->table('jobs')->where('queue', 'mailer')->count(),
        ];

        // Get email limits
        if ($team && ! $team->relationLoaded('settings'))
        {
            $team->load('settings');
        }
        $emailLimits = $team->getRemainingEmails();

        // Get last log entries
        $logFile = storage_path('logs/laravel.log');
        $lastLogs = [];
        if (file_exists($logFile))
        {
            $logs = file($logFile);
            $relevantLogs = array_filter($logs, function ($line)
            {
                return str_contains($line, 'SendMessageCampaignJob') ||
                       str_contains($line, 'Failed to send message delivery');
            });
            $lastLogs = array_slice($relevantLogs, -10);
        }

        return response()->json([
            'message' => [
                'id' => $message->id,
                'name' => $message->name,
                'status' => $message->status_id,
            ],
            'delivery_stats' => $stats,
            'failed_deliveries' => $failedDeliveries,
            'queue_status' => $queueStatus,
            'email_limits' => [
                'monthly_used' => $emailLimits['monthly_used'] ?? 0,
                'monthly_limit' => $emailLimits['monthly_limit'] ?? 0,
                'monthly_remaining' => $emailLimits['monthly_remaining'] ?? 0,
                'daily_used' => $emailLimits['daily_used'] ?? 0,
                'daily_limit' => $emailLimits['daily_limit'] ?? 0,
                'daily_remaining' => $emailLimits['daily_remaining'] ?? 0,
                'is_blocked' => ($emailLimits['monthly_remaining'] ?? 1) <= 0 || ($emailLimits['daily_remaining'] ?? 1) <= 0,
            ],
            'last_logs' => $lastLogs,
        ], 200, [], JSON_PRETTY_PRINT);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Obtener el mensaje con relaciones necesarias
        $message = Message::with(['contactCategories', 'deliveries', 'team.settings', 'template'])
            ->withExists('campaigns')
            ->findOrFail($id);

        // Obtener configuración de correo saliente del team con settings cargados
        $team = auth()->user()->currentTeam;
        if ($team && ! $team->relationLoaded('settings'))
        {
            $team->load('settings');
        }
        $emailConfig = $team->getOutgoingEmailConfig();

        $contactsInCategory = $message->audienceContactsQuery()->count();

        // Obtener estadísticas reales calculadas desde la base de datos (optimizado con una sola query)
        $deliveryStats = MessageDelivery::where('message_id', $message->id)
            ->selectRaw('
                COUNT(DISTINCT contact_id) as subscribers,
                SUM(CASE WHEN status_id = 0 THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN sent_at IS NOT NULL THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicks
            ')
            ->first();

        $stats = [
            'subscribers' => $deliveryStats->subscribers ?? 0,
            'remaining' => 0,  // Puedes calcularlo según tu lógica
            'failed' => $deliveryStats->failed ?? 0,
            'sent' => $deliveryStats->sent ?? 0,
            'rejected' => 0,  // Ajusta según tu lógica
            'delivered' => $deliveryStats->delivered ?? 0,
            'opened' => $deliveryStats->opened ?? 0,
            'unsubscribed' => 0,  // Si tienes tracking de desuscriptos
            'clicks' => $deliveryStats->clicks ?? 0,
            'unique_opens' => $deliveryStats->opened ?? 0,  // Same as opened for now
            'ratio' => 0,  // Se calculará después
        ];

        // Calcular el ratio de apertura (open rate)
        if ($stats['delivered'] > 0)
        {
            $stats['ratio'] = round(($stats['opened'] / $stats['delivered']) * 100, 1);
        }

        // Obtener stats de la tabla message_delivery_stats usando el modelo
        $stats_db = MessageDeliveryStat::where('message_id', $message->id)->first();
        if (! $stats_db)
        {
            $stats_db = (object) [
                'subscribers' => 0,
                'remaining' => 0,
                'failed' => 0,
                'sent' => 0,
                'rejected' => 0,
                'delivered' => 0,
                'opened' => 0,
                'unsubscribed' => 0,
                'clicks' => 0,
                'unique_opens' => 0,
                'ratio' => 0,
            ];
        }

        // Obtener entregas reales - usar la relación cargada si está disponible
        if ($message->relationLoaded('deliveries'))
        {
            $deliveries = $message->deliveries->load('contact');
        } else
        {
            $deliveries = MessageDelivery::where('message_id', $message->id)->with('contact')->get();
        }

        // Obtener links de conversión agrupados por URL única
        $links = MessageDeliveryLink::whereIn('message_delivery_id', $deliveries->pluck('id'))
            ->where('click_count', '>', 0)  // Only count links that were actually clicked
            ->with('messageDelivery.contact')
            ->get()
            ->groupBy('link')
            ->map(function ($linkGroup)
            {
                $link = $linkGroup->first()->link;
                $totalClicks = $linkGroup->sum('click_count');
                $uniqueContacts = $linkGroup->pluck('messageDelivery.contact.id')->filter()->unique();
                $uniqueClicks = $uniqueContacts->count();
                $firstClick = $linkGroup->min('created_at');
                $lastClick = $linkGroup->max('updated_at');

                return (object) [
                    'link' => $link,
                    'unique_clicks' => $uniqueClicks,
                    'total_clicks' => $totalClicks,
                    'first_click' => $firstClick,
                    'last_click' => $lastClick,
                ];
            })
            ->sortByDesc('total_clicks')
            ->values();

        $dnsStatus = class_exists(\App\Helpers\DnsHelper::class)
            ? \App\Helpers\DnsHelper::outgoingDnsStatusForAuthUser(auth()->user())
            : null;

        return view('message.show', [
            'message' => $message,
            'stats' => $stats,
            'stats_db' => $stats_db,
            'deliveries' => $deliveries,
            'links' => $links,
            'emailConfig' => $emailConfig,
            'contactsInCategory' => $contactsInCategory,
            'dnsStatus' => $dnsStatus,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, string $id)
    {
        $data = Message::with(['contactCategories', 'deliveries', 'team.settings', 'template'])->find($id);

        if (! $data)
        {
            return redirect()->route('message.index')->with('error', 'Message not found.');
        }

        $removeMailTemplate = $request->boolean('remove_mail_template');

        $data->templates = Template::getOptions();
        $data->contactStatuses = \App\Models\ContactStatus::getOptions();

        // Check if message has any deliveries created
        $data->hasDeliveries = MessageDelivery::where('message_id', $data->id)->exists();

        $previewContext = $this->prepareMessageFormTemplatePreview($data, $request, $removeMailTemplate);

        return view('message.form', array_merge(compact('data', 'removeMailTemplate'), $previewContext));
    }

    /**
     * JSON + HTML fragment for the message form when the user selects an email template (legacy / edit) before save.
     */
    public function templateEmailPreviewForMessageForm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => ['required', 'integer', 'exists:templates,id'],
            'message_id' => ['nullable', 'integer'],
            'context_name' => ['nullable', 'string', 'max:255'],
        ]);

        $template = Template::query()->whereKey($validated['template_id'])->firstOrFail();

        $messageId = isset($validated['message_id']) ? (int) $validated['message_id'] : 0;
        if ($messageId <= 0)
        {
            $messageId = null;
        } elseif (Message::query()->whereKey($messageId)->doesntExist())
        {
            $messageId = null;
        }

        $returnUrl = $this->resolveTemplateEditorReturnUrl($request, $messageId);
        $message = $messageId !== null ? Message::query()->with('template')->find($messageId) : null;
        $bundle = $this->buildEmailTemplatePreviewBundle($template, $messageId, $returnUrl, $message);

        return response()->json([
            'preview_html' => $bundle['preview_html'],
            'html' => $bundle['html'],
            'duplicate_action_url' => $bundle['duplicate_action_url'],
        ]);
    }

    /**
     * Persist Quill HTML into the linked template, then redirect to GrapesJS so the editor matches the composer.
     */
    public function syncTemplateHtmlOpenVisualEditor(SyncMessageTemplateHtmlForEditorRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $templateId = (int) $validated['template_id'];
        $messageId = isset($validated['message_id']) ? (int) $validated['message_id'] : 0;
        $messageIdGate = $messageId > 0 ? $messageId : null;
        $html = (string) $validated['template_html'];

        if ($messageIdGate !== null)
        {
            $message = Message::query()->whereKey($messageIdGate)->first();
            if (! $message || (int) $message->type_id !== 1)
            {
                abort(422, 'Invalid message for email template.');
            }
        }

        $this->persistTemplateHtmlToTemplateModel($templateId, $html);

        if ($messageIdGate !== null && Schema::hasColumn('messages', 'mail_html'))
        {
            Message::query()->whereKey($messageIdGate)->update(['mail_html' => null]);
        }

        $returnUrl = TemplateEditorReturnUrl::validatedCandidate($request, $request->input('return_url'));
        if ($returnUrl === null || $returnUrl === '')
        {
            $returnUrl = $messageIdGate !== null
                ? route('message.edit', $messageIdGate)
                : route('message.create');
        }

        if ($returnUrl !== null && $returnUrl !== '')
        {
            if ($messageIdGate !== null)
            {
                $editPath = parse_url(route('message.edit', $messageIdGate), PHP_URL_PATH) ?? '/message/edit';
                $returnUrl = TemplateEditorReturnUrl::mergeQueryWhenPathMatches(
                    $returnUrl,
                    $editPath,
                    ['template_id' => (string) $templateId],
                );
            } else
            {
                $createPath = parse_url(route('message.create'), PHP_URL_PATH) ?? '/message/create';
                $returnUrl = TemplateEditorReturnUrl::mergeQueryWhenPathMatches(
                    $returnUrl,
                    $createPath,
                    ['template_id' => (string) $templateId],
                );
            }
        }

        $template = Template::query()->whereKey($templateId)->firstOrFail();
        $editorUrl = TemplateEditorReturnUrl::editorRouteWithReturn(
            route('template.editor', $template->getHashedId()),
            $returnUrl,
        );

        return redirect()->to($editorUrl);
    }

    /**
     * Persist Quill HTML into the linked template and return to the message composer (no visual editor).
     */
    public function syncTemplateHtml(SyncMessageTemplateHtmlForEditorRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $templateId = (int) $validated['template_id'];
        $messageId = isset($validated['message_id']) ? (int) $validated['message_id'] : 0;
        $messageIdGate = $messageId > 0 ? $messageId : null;
        $html = (string) $validated['template_html'];

        if ($messageIdGate !== null)
        {
            $message = Message::query()->whereKey($messageIdGate)->first();
            if (! $message || (int) $message->type_id !== 1)
            {
                abort(422, 'Invalid message for email template.');
            }

            if (MessageDelivery::query()->where('message_id', $messageIdGate)->exists())
            {
                return redirect()
                    ->back()
                    ->with('warning', __('app.email_template_update_blocked_deliveries'));
            }
        }

        if (! $this->persistTemplateHtmlToTemplateModel($templateId, $html))
        {
            return redirect()
                ->back()
                ->with('warning', __('app.email_template_update_empty'));
        }

        if ($messageIdGate !== null && Schema::hasColumn('messages', 'mail_html'))
        {
            $message = Message::query()->whereKey($messageIdGate)->first();
            if ($message instanceof Message)
            {
                $message->forceFill(['mail_html' => trim($html)])->save();
            }
        }

        $returnUrl = TemplateEditorReturnUrl::validatedCandidate($request, $request->input('return_url'));
        if ($returnUrl === null || $returnUrl === '')
        {
            $returnUrl = $messageIdGate !== null
                ? route('message.edit', $messageIdGate)
                : route('message.create');
        }

        if ($messageIdGate === null && $returnUrl !== null && $returnUrl !== '')
        {
            $createPath = parse_url(route('message.create'), PHP_URL_PATH) ?? '/message/create';
            $returnUrl = TemplateEditorReturnUrl::mergeQueryWhenPathMatches(
                $returnUrl,
                $createPath,
                ['template_id' => (string) $templateId],
            );
        }

        return redirect()
            ->to($returnUrl)
            ->with('success', __('app.email_template_update_success'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $model = Message::with(['deliveries', 'team.settings'])->findOrFail($id);

        $model->delete();

        return redirect()->route('message.index')->with('success', 'The record has been deleted.');
    }

    public function sendSmsMessage(Request $request)
    {
        $receiverNumber = env('TWILIO_PHONE_TO');
        $message = env('APP_NAME', 'Laravel').' SMS Message testing...';

        $sid = env('TWILIO_SID');
        $token = env('TWILIO_TOKEN');
        $fromNumber = env('TWILIO_SMS_FROM');

        try
        {
            $client = new Client($sid, $token);
            $client->messages->create($receiverNumber, [
                'from' => $fromNumber,
                'body' => $message,
            ]);

            return response()->json(['status' => 'SMS Message Sent Successfully.']);
        } catch (\Twilio\Exceptions\RestException $e)
        {
            return response()->json(['error' => 'Error: '.$e->getMessage()], 400);
        }
    }

    public function sendWhatsAppMessage(Request $request)
    {
        $receiverNumber = 'whatsapp:'.env('TWILIO_WHATSAPP_FROM');
        $message = env('APP_NAME', 'Laravel').' WhatsApp Message testing...';

        $sid = env('TWILIO_SID');
        $token = env('TWILIO_TOKEN');
        $fromNumber = env('TWILIO_WHATSAPP_FROM');
        try
        {
            $client = new Client($sid, $token);

            $client->messages->create($receiverNumber, [
                'from' => $fromNumber,
                'body' => $message,
            ]);

            return response()->json(['status' => 'WhatsApp Message Sent Successfully.']);
        } catch (\Twilio\Exceptions\RestException $e)
        {
            return response()->json(['error' => 'Error: '.$e->getMessage()], 400);
        }
    }

    public function unsubscribe($email)
    {
        // Update contact status to "Perdido" (ID 4) when they unsubscribe
        // But don't change status if they are already a client (status_id 5)
        $contact = \App\Models\Contact::with(['user.roles', 'user.teams', 'user.currentTeam.settings'])
            ->where('email', $email)->first();

        if ($contact)
        {
            if ($contact->status_id != 5)
            {
                $contact->update(['status_id' => 4]);

                Log::info('Contact unsubscribed - status updated to Perdido', [
                    'contact_id' => $contact->id,
                    'contact_email' => $contact->email,
                    'previous_status' => $contact->getOriginal('status_id'),
                    'new_status' => 4,
                ]);
            } else
            {
                Log::info('Contact is a client - unsubscribed but status not changed', [
                    'contact_id' => $contact->id,
                    'contact_email' => $contact->email,
                    'current_status' => 5,
                    'action' => 'unsubscribe_attempt',
                ]);
            }
        }

        return view('message.unsubscribe', ['email' => $email]);
    }

    /**
     * Start a message campaign
     */
    public function startCampaign(Request $request, $id): JsonResponse
    {
        try
        {
            $message = Message::with(['deliveries', 'team.settings', 'campaigns'])->findOrFail($id);
            $activation = $this->attemptActivateMessageCampaign($message);

            return response()->json([
                'success' => $activation['success'],
                'message' => $activation['message'],
            ], $activation['success'] ? 200 : 400);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar campaña: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    protected function attemptActivateMessageCampaign(Message $message): array
    {
        try
        {
            $team = auth()->user()->currentTeam;
            if ($team && ! $team->relationLoaded('settings'))
            {
                $team->load('settings');
            }
            $emailConfig = $team?->getOutgoingEmailConfig() ?? [];

            if (empty($emailConfig['from_name']) || empty($emailConfig['from_address']))
            {
                return [
                    'success' => false,
                    'message' => 'El remitente de correo no está configurado. Por favor configúralo en Ajustes del Equipo.',
                ];
            }

            $updateData = ['status_id' => 1];

            if (! $message->started_at)
            {
                $updateData['started_at'] = now();
            }

            $message->update($updateData);

            $message->load('campaigns');
            foreach ($message->campaigns as $campaign)
            {
                $campaignStatus = CampaignStatus::tryFrom($campaign->status);
                if ($campaignStatus !== CampaignStatus::Sent && $campaignStatus !== CampaignStatus::Scheduled)
                {
                    $campaign->update(['status' => CampaignStatus::Active->value]);
                }
            }

            $contactsCount = $this->getContactsForMessage($message)->count();

            $pendingDeliveries = MessageDelivery::where('message_id', $message->id)
                ->where(function ($query)
                {
                    $query->whereNull('sent_at')
                        ->orWhere('sent_at', '>', now());
                })
                ->count();

            $responseMessage = 'Campaña activada exitosamente. ';

            if ($pendingDeliveries > 0)
            {
                $responseMessage .= "{$pendingDeliveries} envíos pendientes serán enviados por el programador.";
            } else
            {
                $responseMessage .= "{$contactsCount} contactos serán procesados por el programador.";
            }

            return [
                'success' => true,
                'message' => $responseMessage,
            ];
        } catch (\Exception $e)
        {
            return [
                'success' => false,
                'message' => 'Error al iniciar campaña: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get contacts for a message based on its category
     */
    private function getContactsForMessage(Message $message)
    {
        return $message->audienceContactsQuery();
    }

    /**
     * Pause a message campaign
     */
    public function pauseCampaign(Request $request, $id)
    {
        try
        {
            $message = Message::with(['deliveries', 'team.settings'])->findOrFail($id);

            // Update message status to inactive/paused
            $message->update(['status_id' => 0]);

            return response()->json([
                'success' => true,
                'message' => 'Campaña pausada exitosamente',
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error al pausar campaña: '.$e->getMessage(),
            ], 500);
        }
    }

    public function sendPendingNow(Request $request, $id)
    {
        try
        {
            $message = Message::with(['deliveries', 'team.settings'])->findOrFail($id);

            // Count ALL pending deliveries (status_id = 1, not failed = 4, not delivered)
            $pendingCount = MessageDelivery::where('message_id', $id)
                ->where('status_id', 1) // pending status (automatically excludes status_id = 4)
                ->whereNull('delivered_at') // not delivered yet
                ->count();

            if ($pendingCount === 0)
            {
                // Check how many failed
                $failedCount = MessageDelivery::where('message_id', $id)
                    ->where('status_id', 4)
                    ->count();

                $message = 'No hay deliveries pendientes. ';
                if ($failedCount > 0)
                {
                    $message .= "Hay {$failedCount} fallidos que no se reenviarán automáticamente. Usa el botón 'Reenviar' en cada uno si deseas reintentarlos.";
                } else
                {
                    $message .= 'Todos los contactos ya recibieron el correo.';
                }

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 400);
            }

            // First, reschedule ALL pending deliveries to send now
            $baseTime = now();
            $allPending = MessageDelivery::where('message_id', $id)
                ->where('status_id', 1) // only pending, not failed
                ->whereNull('delivered_at')
                ->orderBy('id', 'asc')
                ->get();

            foreach ($allPending as $index => $delivery)
            {
                // Stagger via scheduled_for so SendMessageCampaignJob can release(); do not set sent_at until mail is sent
                $delivery->update([
                    'scheduled_for' => $baseTime->copy()->addSeconds($index * 3),
                    'sent_at' => null,
                ]);
            }

            // Then, queue first 100 immediately
            $deliveries = MessageDelivery::where('message_id', $id)
                ->where('status_id', 1) // only pending, not failed
                ->whereNull('delivered_at')
                ->with(['contact', 'message', 'team'])
                ->limit(100)
                ->get();

            $queued = 0;
            $dispatcher = app(MessageDeliveryDispatcher::class);
            foreach ($deliveries as $delivery)
            {
                $dispatcher->enqueue(delivery: $delivery, withEnqueueJitter: false);
                $queued++;
            }

            $totalRescheduled = $allPending->count();

            return response()->json([
                'success' => true,
                'message' => "Se reprogramaron {$totalRescheduled} correos y se encolaron {$queued} para envío inmediato",
                'queued' => $queued,
                'rescheduled' => $totalRescheduled,
                'remaining' => max(0, $totalRescheduled - $queued),
            ]);
        } catch (\Exception $e)
        {
            Log::error('Error sending pending deliveries', [
                'message_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al encolar correos: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get contact details for a specific link
     */
    public function getLinkDetails(Request $request, $id, $encodedLink)
    {
        try
        {
            $message = Message::with(['deliveries', 'team.settings'])->findOrFail($id);
            $link = base64_decode($encodedLink);

            // Get all deliveries for this message - usar la relación cargada si está disponible
            if ($message->relationLoaded('deliveries'))
            {
                $deliveries = $message->deliveries;
            } else
            {
                $deliveries = MessageDelivery::where('message_id', $message->id)->get();
            }

            // Get contact details for this specific link - only those who actually clicked
            $linkDetails = MessageDeliveryLink::whereIn('message_delivery_id', $deliveries->pluck('id'))
                ->where('link', $link)
                ->where('click_count', '>', 0)  // Only contacts who actually clicked
                ->with(['messageDelivery.contact'])
                ->get();

            // Group by contact and sum click counts
            $contactData = [];
            $totalClicks = 0;
            $uniqueClicks = 0;

            foreach ($linkDetails as $linkDetail)
            {
                $contact = $linkDetail->messageDelivery->contact;
                if (! $contact)
                {
                    continue;
                }

                $contactId = $contact->id;
                $clickCount = $linkDetail->click_count;
                $totalClicks += $clickCount;

                if (! isset($contactData[$contactId]))
                {
                    $contactData[$contactId] = [
                        'name' => $contact->name,
                        'email' => $contact->email,
                        'click_count' => 0,
                        'first_click' => $linkDetail->created_at,
                        'last_click' => $linkDetail->updated_at,
                    ];
                    $uniqueClicks++;  // Count unique contacts
                }

                $contactData[$contactId]['click_count'] += $clickCount;

                // Update first/last click times
                if ($linkDetail->created_at < $contactData[$contactId]['first_click'])
                {
                    $contactData[$contactId]['first_click'] = $linkDetail->created_at;
                }
                if ($linkDetail->updated_at && $linkDetail->updated_at > $contactData[$contactId]['last_click'])
                {
                    $contactData[$contactId]['last_click'] = $linkDetail->updated_at;
                }
            }

            // Format the data for response
            $contacts = array_map(function ($contact)
            {
                return [
                    'name' => $contact['name'],
                    'email' => $contact['email'],
                    'click_count' => $contact['click_count'],
                    'first_click' => $contact['first_click'] ? $contact['first_click']->format('M j, Y H:i') : 'N/A',
                    'last_click' => $contact['last_click'] ? $contact['last_click']->format('M j, Y H:i') : 'Never',
                ];
            }, $contactData);

            // Sort by click count descending
            usort($contacts, function ($a, $b)
            {
                return $b['click_count'] - $a['click_count'];
            });

            return response()->json([
                'success' => true,
                'contacts' => array_values($contacts),
                'totalClicks' => $totalClicks,
                'uniqueClicks' => $uniqueClicks,
                'link' => $link,
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar detalles del enlace: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send a test email to the current user (saved message).
     */
    public function testSend(Request $request, $id)
    {
        $emails = $this->resolveTestRecipientEmails($request);
        $team = null;

        try
        {
            $message = Message::with(['deliveries', 'team.settings', 'template'])->findOrFail($id);
            $user = auth()->user();
            $team = $user->currentTeam;
            if ($team && ! $team->relationLoaded('settings'))
            {
                $team->load('settings');
            }

            Log::info('🧪 TEST SEND: Starting test email', [
                'message_id' => $message->id,
                'message_name' => $message->name,
                'user_email' => $user->email,
                'team_id' => $team->id,
                'team_name' => $team->name,
                'team_has_custom_smtp' => $team->hasOutgoingEmailConfig(),
                'before_config_host' => config('mail.mailers.smtp.host'),
                'before_config_username' => config('mail.mailers.smtp.username'),
            ]);

            $this->sendTestEmailUsingMessageContext($message, $user, $team, $emails);

            Log::info('Test message sent', [
                'message_id' => $message->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Correo de prueba enviado exitosamente',
                'email' => implode(', ', $emails),
                'emails' => $emails,
            ]);
        } catch (ValidationException $e)
        {
            throw $e;
        } catch (\Exception $e)
        {
            Log::error('Test message send failed', [
                'message_id' => $id,
                'team_id' => isset($team) ? $team->id : null,
                'error_message' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            // Determine user-friendly error message based on error type
            $userMessage = $this->getUserFriendlyErrorMessage($e);

            return response()->json([
                'success' => false,
                'message' => $userMessage,
            ]);
        }
    }

    /**
     * Send a test email using a team template before the message is saved (e.g. message create flow).
     */
    public function testSendFromTemplate(TestMessageFromTemplateRequest $request): JsonResponse
    {
        $team = null;

        try
        {
            $user = $request->user();
            $team = $user->currentTeam;

            if (! $team)
            {
                return response()->json([
                    'success' => false,
                    'message' => __('app.message_test_send_requires_team'),
                ], 422);
            }

            if (! $team->relationLoaded('settings'))
            {
                $team->load('settings');
            }

            $validated = $request->validated();
            $emails = $this->parseTestRecipientEmailsList($validated['test_recipients'] ?? null);
            $template = Template::query()->whereKey($validated['template_id'])->firstOrFail();

            $draftMessage = new Message([
                'name' => filled($validated['draft_name'] ?? null) ? $validated['draft_name'] : $template->name,
                'text' => $validated['fallback_text'] ?? '',
                'team_id' => $team->id,
                'type_id' => 1,
            ]);
            $draftMessage->setRelation('template', $template);

            Log::info('🧪 TEST SEND: Starting test email from template (draft)', [
                'template_id' => $template->id,
                'user_email' => $user->email,
                'team_id' => $team->id,
            ]);

            $this->sendTestEmailUsingMessageContext($draftMessage, $user, $team, $emails);

            Log::info('Test message sent from template', [
                'template_id' => $template->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Correo de prueba enviado exitosamente',
                'email' => implode(', ', $emails),
                'emails' => $emails,
            ]);
        } catch (ValidationException $e)
        {
            throw $e;
        } catch (\Exception $e)
        {
            Log::error('Test message send from template failed', [
                'team_id' => isset($team) ? $team->id : null,
                'error_message' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            $userMessage = $this->getUserFriendlyErrorMessage($e);

            return response()->json([
                'success' => false,
                'message' => $userMessage,
            ]);
        }
    }

    /**
     * Generate HTML content for test send (without tracking)
     */
    private function getTestHtmlForContact($message, $testContact)
    {
        $templateHtml = $message ? $message->resolveMailHtml() : '';

        if (trim($templateHtml) === '')
        {
            $templateHtml = '<p>'.e($message->text ?? '').'</p>';
        }

        return $this->replaceEmailVariables($templateHtml, $testContact, $message);
    }

    /**
     * @param  list<string>  $recipientEmails
     */
    private function sendTestEmailUsingMessageContext(Message $message, User $user, Team $team, array $recipientEmails): void
    {
        $this->configureMailForTeam($team);

        $emailProvider = config('services.email.provider', 'smtp');

        foreach ($recipientEmails as $recipientEmail)
        {
            $testContact = new stdClass;
            $testContact->name = (string) Str::of($recipientEmail)->before('@') ?: $user->name;
            $testContact->surname = '';
            $testContact->email = $recipientEmail;
            $testContact->id = 'test';

            $htmlContent = $this->getTestHtmlForContact($message, $testContact);

            switch ($emailProvider)
            {
                case 'api':
                    if (config('humano-mailer.providers.api.enabled'))
                    {
                        Mail::to($recipientEmail)->send(new \App\Mail\TestMessageMail($message, $testContact, $htmlContent));
                    } else
                    {
                        Log::warning('TEST SEND: Email API not configured, using default SMTP');
                        Mail::to($recipientEmail)->send(new \App\Mail\TestMessageMail($message, $testContact, $htmlContent));
                    }
                    break;
                case 'smtp':
                default:
                    Mail::to($recipientEmail)->send(new \App\Mail\TestMessageMail($message, $testContact, $htmlContent));
                    break;
            }
        }
    }

    /**
     * Disabled channel controls omit type_id from the request; preserve the stored value on update,
     * otherwise default to mail (1) as a last resort.
     */
    private function whatsappMessageTypeId(): int
    {
        static $cachedWhatsappMessageTypeId = null;

        if ($cachedWhatsappMessageTypeId !== null)
        {
            return $cachedWhatsappMessageTypeId;
        }

        $cachedWhatsappMessageTypeId = 2;

        foreach (MessageType::query()->cursor() as $type)
        {
            if (stripos((string) $type->name, 'whatsapp') !== false)
            {
                $cachedWhatsappMessageTypeId = (int) $type->id;
                break;
            }
        }

        return $cachedWhatsappMessageTypeId;
    }

    private function resolveTypeIdForMessageStore(Request $request): int
    {
        $raw = $request->input('type_id');
        $resolved = 1;

        if ($raw !== null && $raw !== '')
        {
            $resolved = (int) $raw;
        } elseif ($request->filled('id'))
        {
            $existing = Message::query()->whereKey((int) $request->id)->value('type_id');
            if ($existing !== null)
            {
                $resolved = (int) $existing;
            }
        }

        if (! $request->filled('id') && $resolved === $this->whatsappMessageTypeId())
        {
            return 1;
        }

        return $resolved;
    }

    /**
     * @return list<string>
     */
    private function resolveTestRecipientEmails(Request $request): array
    {
        $validated = $request->validate([
            'test_recipients' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->parseTestRecipientEmailsList($validated['test_recipients'] ?? null);
    }

    /**
     * @return list<string>
     */
    private function parseTestRecipientEmailsList(?string $raw): array
    {
        $user = auth()->user();
        if ($user === null)
        {
            throw ValidationException::withMessages([
                'test_recipients' => __('app.message_test_send_unauthenticated'),
            ]);
        }

        if ($raw === null || trim($raw) === '')
        {
            return [$user->email];
        }

        $segments = collect(explode(',', $raw))
            ->map(static fn (string $s): string => trim($s))
            ->filter()
            ->unique()
            ->values();

        if ($segments->isEmpty())
        {
            return [$user->email];
        }

        if ($segments->count() > 15)
        {
            throw ValidationException::withMessages([
                'test_recipients' => __('app.message_test_send_too_many_recipients'),
            ]);
        }

        $out = [];
        foreach ($segments as $email)
        {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL))
            {
                throw ValidationException::withMessages([
                    'test_recipients' => __('app.message_test_send_invalid_recipient', ['email' => $email]),
                ]);
            }

            $out[] = $email;
        }

        return $out;
    }

    /**
     * Full-window preview chrome; email HTML is loaded in an iframe from {@see previewHtml()}.
     */
    public function preview($id)
    {
        try
        {
            $message = Message::with(['template', 'category'])->findOrFail($id);

            return view('message.preview', [
                'message' => $message,
                'iframeSrc' => route('message.preview.html', $message->id),
            ]);
        } catch (\Exception $e)
        {
            return view('message.preview', [
                'message' => null,
                'iframeSrc' => null,
                'previewError' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Raw HTML document for the email body (iframe source). Renders the online-style preview.
     */
    public function previewHtml($id): \Illuminate\Http\Response
    {
        try
        {
            $message = Message::with(['template', 'category'])->findOrFail($id);
            $html = $this->buildMessagePreviewHtml($message);

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Throwable $e)
        {
            $safe = e($e->getMessage());

            return response(
                '<!DOCTYPE html><html lang="'.e(str_replace('_', '-', app()->getLocale())).'"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'.e(__('Error')).'</title></head><body><p>'.e(__('Error al cargar la vista previa.')).'</p><p>'.$safe.'</p></body></html>',
                500,
                [
                    'Content-Type' => 'text/html; charset=UTF-8',
                ],
            );
        }
    }

    /**
     * @return object{name?: string, surname?: string, email?: string}
     */
    private function resolvePreviewSampleContact(Message $message): object
    {
        $contact = $message->audienceContactsQuery()->first();
        if ($contact !== null)
        {
            return $contact;
        }

        return (object) [
            'name' => 'John',
            'surname' => 'Doe',
            'email' => 'john.doe@example.com',
        ];
    }

    private function buildMessagePreviewHtml(Message $message): string
    {
        $sampleContact = $this->resolvePreviewSampleContact($message);

        $htmlContent = $message->resolveMailHtml();
        if (trim($htmlContent) !== '')
        {
            $htmlContent = $this->replaceEmailVariables($htmlContent, $sampleContact, $message);
        } else
        {
            $htmlContent = '<p>'.e($message->text).'</p>';
        }

        $team = auth()->user()->currentTeam;
        $advertisingFooter = $team ? $team->getAdvertisingFooter() : '';

        if ($advertisingFooter)
        {
            if (stripos($htmlContent, '</body>') !== false)
            {
                $htmlContent = str_ireplace('</body>', $advertisingFooter.'</body>', $htmlContent);
            } else
            {
                $htmlContent .= $advertisingFooter;
            }
        }

        return $htmlContent;
    }

    /**
     * Get user-friendly error message based on exception type
     */
    private function getUserFriendlyErrorMessage(\Exception $e): string
    {
        $errorMessage = $e->getMessage();
        $errorCode = $e->getCode();

        // Check for common SMTP error patterns
        if (strpos($errorMessage, '550 domain is not configured with ORIGIN IP IN SPF') !== false ||
                strpos($errorMessage, 'SPF') !== false ||
                strpos($errorMessage, '550') !== false)
        {
            return "No se pudo enviar el email de prueba.\nPor favor, contacte con soporte técnico para autorizar la salida de emails desde su dominio.";
        }

        // Check for authentication errors
        if (strpos($errorMessage, '535') !== false ||
                strpos($errorMessage, 'authentication') !== false ||
                strpos($errorMessage, 'login') !== false)
        {
            return 'Error de autenticación en el servidor de correo. Verifique las credenciales de configuración.';
        }

        // Check for connection errors
        if (strpos($errorMessage, 'connection') !== false ||
                strpos($errorMessage, 'timeout') !== false ||
                strpos($errorMessage, 'refused') !== false)
        {
            return 'No se pudo conectar al servidor de correo. Verifique la configuración de conexión.';
        }

        // Check for quota exceeded
        if (strpos($errorMessage, 'quota') !== false ||
                strpos($errorMessage, 'limit') !== false ||
                strpos($errorMessage, 'exceeded') !== false)
        {
            return 'Se ha alcanzado el límite de envío de emails. Contacte con soporte técnico.';
        }

        // Generic error message for unknown errors
        return 'No se pudo enviar el email de prueba. Por favor, contacte con soporte técnico si el problema persiste.';
    }

    /**
     * @return array{emailPreviewBundleHtml: string|null, emailPreviewDuplicateActionUrl: string|null}
     */
    private function prepareMessageFormTemplatePreview(object $data, Request $request, bool $removeMailTemplate = false): array
    {
        $team = auth()->user()?->currentTeam;
        if (! $team)
        {
            return [
                'emailPreviewBundleHtml' => null,
                'emailPreviewDuplicateActionUrl' => null,
                'messageFormDefaultTemplateId' => null,
            ];
        }

        $requestTemplateId = $request->integer('template_id');
        $preferredTemplateId = $removeMailTemplate
            ? null
            : ($requestTemplateId > 0
                ? $requestTemplateId
                : (int) old('template_id', $data->template_id ?? 0));

        $template = app(MessageFormTemplateResolver::class)->resolveForForm(
            $preferredTemplateId > 0 ? $preferredTemplateId : null,
            (int) $team->id,
            autoPickWhenMissing: ! $removeMailTemplate,
        );

        if (! $template instanceof Template)
        {
            return [
                'emailPreviewBundleHtml' => null,
                'emailPreviewDuplicateActionUrl' => null,
                'messageFormDefaultTemplateId' => null,
            ];
        }

        $data->template_id = $template->id;

        $messageId = isset($data->id) ? (int) $data->id : null;
        $returnUrl = $this->resolveTemplateEditorReturnUrl($request, $messageId > 0 ? $messageId : null);
        $message = $messageId > 0
            ? ($data instanceof Message ? $data->loadMissing('template') : Message::query()->with('template')->find($messageId))
            : null;
        $bundle = $this->buildEmailTemplatePreviewBundle($template, $messageId > 0 ? $messageId : null, $returnUrl, $message);

        return [
            'emailPreviewBundleHtml' => $bundle['html'],
            'emailPreviewDuplicateActionUrl' => $bundle['duplicate_action_url'],
            'messageFormDefaultTemplateId' => $template->id,
        ];
    }

    /**
     * @return array{preview_html: string, html: string, duplicate_action_url: string}
     */
    private function buildEmailTemplatePreviewBundle(Template $template, ?int $messageId, string $returnUrl, ?Message $message = null): array
    {
        $mailHtmlSource = $this->resolveMailHtmlForTemplatePreview($template, $message);

        $previewHtml = $this->iframePreviewHtmlFromSource($mailHtmlSource);
        $mailHtmlTextareaValue = $mailHtmlSource;
        $grapesEditorUrl = TemplateEditorReturnUrl::editorRouteWithReturn(
            route('template.editor', $template->getHashedId()),
            $returnUrl,
        );

        $html = view('message.ajax.email-template-preview-bundle', [
            'previewHtml' => $previewHtml,
            'grapesEditorUrl' => $grapesEditorUrl,
            'templateLabel' => $template->name,
            'messageId' => $messageId,
            'templateId' => $template->id,
            'templateHashedId' => $template->getHashedId(),
            'removeTemplateUrl' => null,
            'useMailHtmlTextarea' => true,
            'mailHtmlTextareaValue' => $mailHtmlTextareaValue,
            'mailHtmlTextareaReadonly' => false,
        ])->render();

        return [
            'preview_html' => $previewHtml,
            'html' => $html,
            'duplicate_action_url' => route('template.duplicate', $template->getHashedId()),
        ];
    }

    private function resolveTemplateEditorReturnUrl(Request $request, ?int $messageId): string
    {
        if ($messageId !== null && $messageId > 0)
        {
            return route('message.edit', $messageId);
        }

        $returnUrl = TemplateEditorReturnUrl::validatedFromRequest($request);
        if ($returnUrl === null || $returnUrl === '')
        {
            return route('message.create');
        }

        return $returnUrl;
    }

    /**
     * Replace email template variables with actual values
     */
    private function iframePreviewHtmlForTemplate(Template $template): string
    {
        return $this->iframePreviewHtmlFromSource($this->rawTemplateHtmlFromModel($template));
    }

    private function iframePreviewHtmlFromSource(string $htmlContent): string
    {
        return MessageTemplateMergeFields::replace($htmlContent, MessageTemplateMergeFields::sampleContact());
    }

    private function resolveMailHtmlFromRequest(Request $request, int $templateId): string
    {
        $raw = $request->input('template_html');
        if (is_string($raw) && trim($raw) !== '')
        {
            return $raw;
        }

        $template = Template::query()->whereKey($templateId)->first();

        return $template instanceof Template ? $this->rawTemplateHtmlFromModel($template) : '';
    }

    private function rawTemplateHtmlFromModel(Template $template): string
    {
        $gjsData = is_array($template->gjs_data) ? $template->gjs_data : [];

        return (string) ($gjsData['html'] ?? '');
    }

    /**
     * When the user picks another template in the message form, show that template's HTML.
     * Only reuse message-specific HTML when the previewed template is the one already linked to the message.
     */
    private function resolveMailHtmlForTemplatePreview(Template $template, ?Message $message = null): string
    {
        $template->refresh();

        return $this->rawTemplateHtmlFromModel($template);
    }

    /**
     * Writes Quill / composer HTML into the template's GrapesJS payload. Skipped when the message
     * already has deliveries (readonly body) or the HTML is empty.
     */
    private function persistTemplateHtmlFromMessageComposer(int $templateId, string $rawHtml, ?int $messageIdForDeliveryGate): void
    {
        if ($templateId <= 0)
        {
            return;
        }

        if ($messageIdForDeliveryGate !== null && $messageIdForDeliveryGate > 0
            && MessageDelivery::query()->where('message_id', $messageIdForDeliveryGate)->exists())
        {
            return;
        }

        $trimmed = trim($rawHtml);
        if ($trimmed === '')
        {
            return;
        }

        if ($messageIdForDeliveryGate !== null && $messageIdForDeliveryGate > 0
            && Schema::hasColumn('messages', 'mail_html'))
        {
            if (MessageDelivery::query()->where('message_id', $messageIdForDeliveryGate)->exists())
            {
                return;
            }

            $message = Message::query()->whereKey($messageIdForDeliveryGate)->first();
            if ($message instanceof Message)
            {
                $message->forceFill(['mail_html' => $trimmed])->save();
            }

            return;
        }

        $this->persistTemplateHtmlToTemplateModel($templateId, $trimmed);
    }

    private function persistTemplateHtmlToTemplateModel(int $templateId, string $rawHtml): bool
    {
        if ($templateId <= 0)
        {
            return false;
        }

        $trimmed = trim($rawHtml);
        if ($trimmed === '')
        {
            return false;
        }

        $template = Template::query()->whereKey($templateId)->first();
        if (! $template instanceof Template)
        {
            return false;
        }

        $gjsData = is_array($template->gjs_data) ? $template->gjs_data : [];
        $gjsData['html'] = $trimmed;

        $template->forceFill(['gjs_data' => $gjsData])->save();

        $template->refresh();
        GrapesJsHelper::fixTemplateStructure($template);

        return true;
    }

    private function replaceEmailVariables(string $htmlContent, $contact, $message = null): string
    {
        return MessageTemplateMergeFields::replace($htmlContent, $contact);
    }

    /**
     * Resend a specific delivery
     */
    public function resendDelivery(Request $request, $deliveryId)
    {
        try
        {
            $delivery = MessageDelivery::findOrFail($deliveryId);

            // Verify the delivery belongs to the current team
            if ($delivery->team_id !== auth()->user()->current_team_id)
            {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para reenviar esta entrega.',
                ], 403);
            }

            // Reset the SAME delivery record to resend
            $delivery->update([
                'status_id' => 1, // pending
                'sent_at' => now(), // Schedule to send immediately
                'delivered_at' => null, // Reset delivery status
                'opened_at' => null, // Reset tracking
                'clicked_at' => null,
                'complained_at' => null,
                'bounced_at' => null,
                'delivery_status' => null,
                'error_message' => null, // Clear previous errors
                'error_type' => null,
                'bounce_type' => null,
                'bounce_reason' => null,
            ]);

            Log::info('Delivery resend requested', [
                'delivery_id' => $delivery->id,
                'user_id' => auth()->id(),
            ]);

            app(MessageDeliveryDispatcher::class)->enqueue(
                delivery: $delivery,
                profile: MessageDeliverySendProfile::Message,
                withEnqueueJitter: false,
            );

            return response()->json([
                'success' => true,
                'message' => 'El correo ha sido reenviado exitosamente.',
            ]);
        } catch (\Exception $e)
        {
            Log::error('Failed to resend delivery', [
                'delivery_id' => $deliveryId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al reenviar el correo: '.$e->getMessage(),
            ], 500);
        }
    }
}
