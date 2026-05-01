<?php

namespace App\Http\Controllers;

use App\DataTables\MessageDataTable;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\MessageDeliveryLink;
use App\Models\MessageDeliveryStat;
use App\Models\MessageType;
use App\Models\Template;
use App\Traits\ConfiguresTeamMail;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
        $legacyForm = $request->boolean('legacy_form');

        if (! $legacyForm && (! $request->filled('template_id') || $request->integer('template_id') <= 0))
        {
            return redirect()->route('campaigns.templates.select', [
                'type' => 'messages',
                'title' => $request->string('title')->toString(),
            ]);
        }

        $data = new stdClass;
        $data->types = MessageType::getOptions();
        $data->templates = Template::getOptions();
        $data->contactStatuses = \App\Models\ContactStatus::getOptions();
        $data->useLegacyTemplatePicker = $legacyForm;

        if (! $legacyForm && $request->integer('template_id') > 0)
        {
            $template = Template::query()->whereKey($request->integer('template_id'))->first();

            if (! $template)
            {
                return redirect()
                    ->route('campaigns.templates.select', ['type' => 'messages', 'title' => ''])
                    ->with('error', __('La plantilla seleccionada no está disponible.'));
            }

            $data->template_id = $template->id;
            $data->template = $template;
            $data->emailTemplatePreviewHtml = $this->iframePreviewHtmlForTemplate($template);
            $data->templateGrapesEditorUrl = route('template.editor', $template->getHashedId());
            $data->name = old('name', $request->string('name')->toString());
            $data->type_id = old('type_id', 1);
            $data->text = old('text', __('Boletín por correo'));
        }

        return view('message.form', compact('data'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->except(['id', '_token']);

        $request->validate([
            'name' => 'required|string|min:3|max:50',
            'text' => 'required|string|min:3|max:255',
        ]);

        $templateId = $data['template_id'] ?? null;

        $status_id = $request->boolean('status_id') ? 1 : 0;

        // Set boolean fields based on checkbox presence
        $show_unsubscribe = $request->has('show_unsubscribe') ? 1 : 0;
        $enable_open_tracking = $request->has('enable_open_tracking') ? 1 : 0;
        $enable_click_tracking = $request->has('enable_click_tracking') ? 1 : 0;

        Message::updateOrCreate(
            ['id' => $request->id],
            [
                'name' => $data['name'],
                'type_id' => $data['type_id'],
                'category_id' => $data['category_id'] ?: null,  // Convert empty string to null
                'contact_status_id' => $data['contact_status_id'] ?? null,
                'template_id' => $templateId,
                'text' => $data['text'],
                'status_id' => $status_id,
                'show_unsubscribe' => $show_unsubscribe,
                'enable_open_tracking' => $enable_open_tracking,
                'enable_click_tracking' => $enable_click_tracking,
                'min_hours_between_emails' => $data['min_hours_between_emails'] ?? 48,
            ],
        );

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
        $message = Message::with(['category', 'deliveries', 'team.settings', 'template'])
            ->withExists('campaigns')
            ->findOrFail($id);

        // Obtener configuración de correo saliente del team con settings cargados
        $team = auth()->user()->currentTeam;
        if ($team && ! $team->relationLoaded('settings'))
        {
            $team->load('settings');
        }
        $emailConfig = $team->getOutgoingEmailConfig();

        // Contar contactos que coinciden con la categoría y estado de contacto del mensaje
        $contactsInCategory = 0;
        if ($message->category)
        {
            $query = $message->category->contacts();

            // Apply contact status filter if specified in the message
            if ($message->contact_status_id)
            {
                $query->where('status_id', $message->contact_status_id);
            }

            $contactsInCategory = $query->count();
        } elseif ($message->contact_status_id)
        {
            // If no category but has contact status filter, count all team contacts with that status
            $contactsInCategory = \App\Models\Contact::where('team_id', $message->team_id)
                ->where('status_id', $message->contact_status_id)
                ->whereNotNull('email')
                ->count();
        }

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

        $apiUser = config('humano-mailer.providers.api.enabled') ? env('MAIL_USERNAME') : null;

        return view('message.show', [
            'message' => $message,
            'stats' => $stats,
            'stats_db' => $stats_db,
            'deliveries' => $deliveries,
            'links' => $links,
            'emailConfig' => $emailConfig,
            'contactsInCategory' => $contactsInCategory,
            'dnsStatus' => $dnsStatus,
            'apiUser' => $apiUser,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = Message::with(['deliveries', 'team.settings', 'template'])->find($id);

        if (! $data)
        {
            return redirect()->route('message.index')->with('error', 'Message not found.');
        }

        $data->types = MessageType::getOptions();
        $data->templates = Template::getOptions();
        $data->contactStatuses = \App\Models\ContactStatus::getOptions();
        $data->useLegacyTemplatePicker = false;

        if ($data->template_id && $data->template && (int) $data->type_id === 1)
        {
            $data->emailTemplatePreviewHtml = $this->iframePreviewHtmlForTemplate($data->template);
            $data->templateGrapesEditorUrl = route('template.editor', $data->template->getHashedId());
        }

        // Check if message has any deliveries created
        $data->hasDeliveries = MessageDelivery::where('message_id', $data->id)->exists();

        return view('message.form', compact('data'));
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
    public function startCampaign(Request $request, $id)
    {
        try
        {
            $message = Message::with(['deliveries', 'team.settings'])->findOrFail($id);

            // Validate email sender configuration
            $team = auth()->user()->currentTeam;
            if ($team && ! $team->relationLoaded('settings'))
            {
                $team->load('settings');
            }
            $emailConfig = $team->getOutgoingEmailConfig();

            if (empty($emailConfig['from_name']) || empty($emailConfig['from_address']))
            {
                return response()->json([
                    'success' => false,
                    'message' => 'El remitente de correo no está configurado. Por favor configúralo en Ajustes del Equipo.',
                ], 400);
            }

            // Activate the message
            $updateData = ['status_id' => 1];

            // Only update started_at if it's the first time starting or if it was never started
            if (! $message->started_at)
            {
                $updateData['started_at'] = now();
            }

            $message->update($updateData);

            // Count potential contacts for this campaign
            $contactsCount = $this->getContactsForMessage($message)->count();

            // Check if there are pending deliveries to send
            $pendingDeliveries = \App\Models\MessageDelivery::where('message_id', $message->id)
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

            return response()->json([
                'success' => true,
                'message' => $responseMessage,
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar campaña: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get contacts for a message based on its category
     */
    private function getContactsForMessage(Message $message)
    {
        $query = null;

        if ($message->category)
        {
            $query = $message->category->contacts();

            // Filter by contact status - use message's contact_status_id or default to active (1)
            $statusId = $message->contact_status_id ?: 1;
            $query->where('status_id', $statusId);
        } else
        {
            // If no category, get all contacts from the team
            $query = \App\Models\Contact::where('team_id', $message->team_id)
                ->whereNotNull('email');

            // Filter by contact status - use message's contact_status_id or default to active (1)
            $statusId = $message->contact_status_id ?: 1;
            $query->where('status_id', $statusId);
        }

        // Exclude test/demo email addresses
        $testDomains = [
            '@example.org',
            '@example.net',
            '@example.com',
            '@demo.com',
            '@test.com',
            '@localhost',
            '@testing.com',
            '@dummy.com',
            '@fake.com',
        ];

        foreach ($testDomains as $domain)
        {
            $query->where('email', 'not like', '%'.$domain);
        }

        return $query;
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
                // Add small delay (3 seconds) between each to avoid spam
                $delivery->update([
                    'sent_at' => $baseTime->copy()->addSeconds($index * 3),
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
            foreach ($deliveries as $delivery)
            {
                // Dispatch immediately without delay
                \App\Jobs\SendMessageCampaignJob::dispatch($delivery)->onQueue('mailer');
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
     * Send a test email to the current user
     */
    public function testSend(Request $request, $id)
    {
        try
        {
            $message = Message::with(['deliveries', 'team.settings'])->findOrFail($id);
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

            // Get email config (will use system defaults if not configured)
            $emailConfig = $team->getOutgoingEmailConfig();

            Log::info('🔍 TEST SEND: Email config retrieved', [
                'smtp_host' => $emailConfig['host'],
                'smtp_port' => $emailConfig['port'],
                'smtp_username' => $emailConfig['username'],
                'from_address' => $emailConfig['from_address'],
                'from_name' => $emailConfig['from_name'],
                'password_configured' => ! empty($emailConfig['password']),
            ]);

            // ✨ IMPORTANTE: Configurar SMTP igual que en el Job
            $this->configureMailForTeam($team);

            Log::info('✅ TEST SEND: SMTP configured, ready to send', [
                'after_config_host' => config('mail.mailers.smtp.host'),
                'after_config_username' => config('mail.mailers.smtp.username'),
                'after_config_from_address' => config('mail.from.address'),
                'after_config_from_name' => config('mail.from.name'),
            ]);

            // Create test contact data
            $testContact = new stdClass;
            $testContact->name = $user->name;
            $testContact->surname = '';
            $testContact->email = $user->email;
            $testContact->id = 'test';

            // Get HTML content for the test (simplified without tracking)
            $htmlContent = $this->getTestHtmlForContact($message, $testContact);

            // Send test email using configured provider
            $emailProvider = config('services.email.provider', 'smtp');

            Log::info('🔧 TEST SEND: Using email provider', [
                'email_provider' => $emailProvider,
                'user_email' => $user->email,
            ]);

            switch ($emailProvider)
            {
                case 'api':
                    if (config('humano-mailer.providers.api.enabled'))
                    {
                        // Use configured email API (MailBaby, Mailgun, etc.)
                        Mail::to($user->email)->send(new \App\Mail\TestMessageMail($message, $testContact, $htmlContent));
                    } else
                    {
                        Log::warning('TEST SEND: Email API not configured, using default SMTP');
                        Mail::to($user->email)->send(new \App\Mail\TestMessageMail($message, $testContact, $htmlContent));
                    }
                    break;
                case 'smtp':
                default:
                    Mail::to($user->email)->send(new \App\Mail\TestMessageMail($message, $testContact, $htmlContent));
                    break;
            }

            Log::info('✅ TEST SEND: Email sent successfully', [
                'message_id' => $message->id,
                'user_email' => $user->email,
                'smtp_host_used' => config('mail.mailers.smtp.host'),
                'from_address_used' => config('mail.from.address'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Correo de prueba enviado exitosamente',
                'email' => $user->email,
            ]);
        } catch (\Exception $e)
        {
            // Log detailed error for debugging
            Log::error('❌ TEST SEND: Failed to send test email', [
                'message_id' => $id,
                'user_email' => $user->email ?? 'unknown',
                'team_id' => $team->id ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'exception_class' => get_class($e),
                'smtp_host_at_error' => config('mail.mailers.smtp.host'),
                'smtp_username_at_error' => config('mail.mailers.smtp.username'),
                'trace' => $e->getTraceAsString(),
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
     * Generate HTML content for test send (without tracking)
     */
    private function getTestHtmlForContact($message, $testContact)
    {
        $templateHtml = $message && $message->template && isset($message->template->gjs_data['html'])
            ? $message->template->gjs_data['html']
            : '';

        // Replace variables
        $html = str_replace('{{name}}', $testContact->name ?? '', $templateHtml);
        $html = str_replace('{{contact_name}}', $testContact->name ?? '', $html);
        $html = str_replace('{{email}}', $testContact->email ?? '', $html);

        return $html;
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
        if ($message->category)
        {
            $contact = $message->category->contacts()->first();
            if ($contact !== null)
            {
                return $contact;
            }
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

        $htmlContent = '';
        if ($message->template && $message->template->gjs_data)
        {
            $gjsData = is_array($message->template->gjs_data)
                ? $message->template->gjs_data
                : json_decode($message->template->gjs_data, true);

            $htmlContent = $gjsData['html'] ?? '';

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
     * Replace email template variables with actual values
     */
    private function iframePreviewHtmlForTemplate(Template $template): string
    {
        $htmlContent = '';
        if ($template->gjs_data && isset($template->gjs_data['html']))
        {
            $htmlContent = $template->gjs_data['html'];
        }

        $sampleContact = (object) [
            'name' => 'John',
            'surname' => 'Doe',
            'email' => 'john.doe@example.com',
        ];

        return $this->replaceEmailVariables($htmlContent, $sampleContact, null);
    }

    private function replaceEmailVariables(string $htmlContent, $contact, $message = null): string
    {
        // Basic contact variables
        $htmlContent = str_replace('{{name}}', $contact->name ?? 'John', $htmlContent);
        $htmlContent = str_replace('{{contact_name}}', ($contact->name ?? 'John').' '.($contact->surname ?? 'Doe'), $htmlContent);
        $htmlContent = str_replace('{{email}}', $contact->email ?? 'john.doe@example.com', $htmlContent);

        // Note: {{date}} and {{header}} variables have been removed from templates
        // They are now hardcoded in the template content

        return $htmlContent;
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

            Log::info('📧 Delivery resend requested', [
                'delivery_id' => $delivery->id,
                'contact_email' => $delivery->contact->email ?? 'unknown',
                'user_id' => auth()->id(),
            ]);

            // Dispatch the job to send immediately
            \App\Jobs\SendMessageCampaignJob::dispatch($delivery);

            return response()->json([
                'success' => true,
                'message' => 'El correo ha sido reenviado exitosamente.',
            ]);
        } catch (\Exception $e)
        {
            Log::error('❌ Failed to resend delivery', [
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
