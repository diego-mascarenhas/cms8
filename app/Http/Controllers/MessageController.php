<?php

namespace App\Http\Controllers;

use App\DataTables\MessageDataTable;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\MessageDeliveryLink;
use App\Models\MessageDeliveryStat;
use App\Models\MessageType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use stdClass;
use Twilio\Rest\Client;

class MessageController extends Controller
{
    public function index(MessageDataTable $dataTable)
    {
        return $dataTable->render('message.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data = new stdClass;
        $data->types = MessageType::getOptions();
        $data->templates = \App\Models\Template::getOptions();
        $data->contactStatuses = \App\Models\ContactStatus::getOptions();

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

        // Set status_id based on checkbox presence
        $status_id = $request->has('status_id') ? 1 : 0; // 1 = active, 0 = inactive

        // Set boolean fields based on checkbox presence
        $show_unsubscribe = $request->has('show_unsubscribe') ? 1 : 0;
        $enable_open_tracking = $request->has('enable_open_tracking') ? 1 : 0;
        $enable_click_tracking = $request->has('enable_click_tracking') ? 1 : 0;

        Message::updateOrCreate(
            ['id' => $request->id],
            [
                'name' => $data['name'],
                'type_id' => $data['type_id'],
                'category_id' => $data['category_id'] ?: null, // Convert empty string to null
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
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Obtener el mensaje con relaciones necesarias
        $message = Message::with('category')->findOrFail($id);

        // Obtener configuración de correo saliente del team con settings cargados
        $team = auth()->user()->currentTeam->load('settings');
        $emailConfig = $team->getOutgoingEmailConfig();

        // Contar contactos que coinciden con la categoría del mensaje
        $contactsInCategory = 0;
        if ($message->category)
        {
            $contactsInCategory = $message->category->contacts()->count();
        }

        // Obtener estadísticas reales calculadas desde la base de datos (optimizado con una sola query)
        $deliveryStats = MessageDelivery::where('message_id', $message->id)
            ->selectRaw('
                COUNT(*) as subscribers,
                SUM(CASE WHEN status_id = 0 THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN sent_at IS NOT NULL THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicks
            ')
            ->first();

        $stats = [
            'subscribers' => $deliveryStats->subscribers ?? 0,
            'remaining' => 0, // Puedes calcularlo según tu lógica
            'failed' => $deliveryStats->failed ?? 0,
            'sent' => $deliveryStats->sent ?? 0,
            'rejected' => 0, // Ajusta según tu lógica
            'delivered' => $deliveryStats->delivered ?? 0,
            'opened' => $deliveryStats->opened ?? 0,
            'unsubscribed' => 0, // Si tienes tracking de desuscriptos
            'clicks' => $deliveryStats->clicks ?? 0,
            'unique_opens' => $deliveryStats->opened ?? 0, // Same as opened for now
            'ratio' => 0, // Se calculará después
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

        // Obtener entregas reales
        $deliveries = MessageDelivery::where('message_id', $message->id)->with('contact')->get();

        // Obtener links de conversión agrupados por URL única
        $links = MessageDeliveryLink::whereIn('message_delivery_id', $deliveries->pluck('id'))
            ->where('click_count', '>', 0) // Only count links that were actually clicked
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

        // Verificar configuración DNS para el dominio del remitente
        $dnsStatus = null;
        $apiUser = null;

        if (! empty($emailConfig['from_address']))
        {
            // Obtener configuración de API de email
            $apiUser = config('humano-mailer.providers.api.enabled') ? env('MAIL_USERNAME') : null;

            // Verificar configuración DNS
            if (class_exists(\App\Helpers\DnsHelper::class))
            {
                $dnsStatus = \App\Helpers\DnsHelper::checkEmailDomainConfiguration(
                    $emailConfig['from_address'],
                    $apiUser,
                );
            }
        }

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
        $data = Message::find($id);

        if (! $data)
        {
            return redirect()->route('message.index')->with('error', 'Message not found.');
        }

        $data->types = MessageType::getOptions();
        $data->templates = \App\Models\Template::getOptions();
        $data->contactStatuses = \App\Models\ContactStatus::getOptions();

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
        $model = Message::findOrFail($id);

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
        $contact = \App\Models\Contact::where('email', $email)->first();

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
            $message = Message::findOrFail($id);

            // Simply activate the message - the scheduler will handle delivery creation
            $message->update([
                'status_id' => 1, // Active
                'started_at' => now(), // Mark when campaign started
            ]);

            // Count potential contacts for this campaign
            $contactsCount = $this->getContactsForMessage($message)->count();

            return response()->json([
                'success' => true,
                'message' => "Campaign activated successfully. {$contactsCount} contacts will be processed by the scheduler.",
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error starting campaign: '.$e->getMessage(),
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
            $message = Message::findOrFail($id);

            // Update message status to inactive/paused
            $message->update(['status_id' => 0]);

            return response()->json([
                'success' => true,
                'message' => 'Campaign paused successfully',
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error pausing campaign: '.$e->getMessage(),
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
            $message = Message::findOrFail($id);
            $link = base64_decode($encodedLink);

            // Get all deliveries for this message
            $deliveries = MessageDelivery::where('message_id', $message->id)->get();

            // Get contact details for this specific link - only those who actually clicked
            $linkDetails = MessageDeliveryLink::whereIn('message_delivery_id', $deliveries->pluck('id'))
                ->where('link', $link)
                ->where('click_count', '>', 0) // Only contacts who actually clicked
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
                    $uniqueClicks++; // Count unique contacts
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
                'message' => 'Error loading link details: '.$e->getMessage(),
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
            $message = Message::findOrFail($id);
            $user = auth()->user();
            $team = $user->currentTeam;

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
            if (trait_exists(\App\Traits\ConfiguresTeamMail::class))
            {
                $this->configureMailForTeam($team);
            }

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
                'message' => 'Test email sent successfully',
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
     * Preview a message
     */
    public function preview($id)
    {
        try
        {
            $message = Message::with('template')->findOrFail($id);

            // Get a sample contact for variable replacement
            $sampleContact = null;
            if ($message->category)
            {
                $sampleContact = $message->category->contacts()->first();
            }

            if (! $sampleContact)
            {
                // Create a sample contact for preview
                $sampleContact = (object) [
                    'name' => 'John',
                    'surname' => 'Doe',
                    'email' => 'john.doe@example.com',
                ];
            }

            // Get template HTML
            $htmlContent = '';
            if ($message->template && $message->template->gjs_data)
            {
                $gjsData = is_array($message->template->gjs_data)
                    ? $message->template->gjs_data
                    : json_decode($message->template->gjs_data, true);

                $htmlContent = $gjsData['html'] ?? '';

                // Replace variables
                $htmlContent = $this->replaceEmailVariables($htmlContent, $sampleContact, $message);
            } else
            {
                $htmlContent = '<p>'.$message->text.'</p>';
            }

            // Add advertising footer if team is using system SMTP
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

            return view('message.preview', [
                'message' => $message,
                'htmlContent' => $htmlContent,
                'sampleContact' => $sampleContact,
            ]);
        } catch (\Exception $e)
        {
            return view('message.preview', [
                'message' => null,
                'htmlContent' => '<p>Error loading preview: '.$e->getMessage().'</p>',
                'sampleContact' => null,
            ]);
        }
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
     * Configure mail for team (if trait is available)
     */
    private function configureMailForTeam($team)
    {
        if (trait_exists(\App\Traits\ConfiguresTeamMail::class))
        {
            // Use the trait if available in the host app
            $trait = new class
            {
                use \App\Traits\ConfiguresTeamMail;

                public function configure($team)
                {
                    $this->configureMailForTeam($team);
                }
            };
            $trait->configure($team);
        }
    }
}
