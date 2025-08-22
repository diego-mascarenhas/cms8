<?php

namespace App\Http\Controllers;

use App\DataTables\MessageDataTable;
use App\Jobs\SendMessageCampaignJob;
use App\Mail\MySendGridMail;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\MessageDeliveryLink;
use App\Models\MessageDeliveryStat;
use App\Models\MessageType;
use App\Models\Template;
use App\Models\User;
use App\Traits\ConfiguresTeamMail;
use App\Helpers\DnsHelper;
use Illuminate\Http\Request;
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
	public function create()
	{
		$data = new stdClass;
		$data->types = MessageType::getOptions();
		$data->templates = Template::getOptions();

		return view('message.form', compact('data'));
	}

	/**
	 * Store a newly created resource in storage.
	 */
	public function store(Request $request)
	{
		$data = $request->except(['id', '_token']);

		$request->validate([
			'name' => 'required|string|min:3|max:25',
			'text' => 'required|string|min:3|max:255',
		]);

		$templateId = $data['template_id'] ?? null;

		// Set status_id based on checkbox presence
		$status_id = $request->has('status_id') ? 1 : 0; // 1 = active, 0 = inactive

		Message::updateOrCreate(
			['id' => $request->id],
			[
				'name' => $data['name'],
				'type_id' => $data['type_id'],
				'category_id' => $data['category_id'],
				'template_id' => $templateId,
				'text' => $data['text'],
				'status_id' => $status_id,
			],
		);

		return redirect()->route('message-list')->with('success', 'Record saved successfully.');
	}

	/**
	 * Display the specified resource.
	 */
	public function show(string $id)
	{
		// Obtener el mensaje
		$message = Message::findOrFail($id);

		// Obtener configuración de correo saliente del team
		$team = auth()->user()->currentTeam;
		$emailConfig = $team->getOutgoingEmailConfig();

		// Contar contactos que coinciden con la categoría del mensaje
		$contactsInCategory = 0;
		if ($message->category)
		{
			$contactsInCategory = $message->category->contacts()->count();
		}

		// Obtener estadísticas reales (ejemplo: sumarización de deliveries)
		$stats = [
			'subscribers' => MessageDelivery::where('message_id', $message->id)->count(),
			'remaining' => 0, // Puedes calcularlo según tu lógica
			'failed' => MessageDelivery::where('message_id', $message->id)->where('status_id', 0)->count(),
			'sent' => MessageDelivery::where('message_id', $message->id)->whereNotNull('sent_at')->count(),
			'rejected' => 0, // Ajusta según tu lógica
			'delivered' => MessageDelivery::where('message_id', $message->id)->whereNotNull('delivered_at')->count(),
			'opened' => 0, // Si tienes tracking de aperturas
			'unsubscribed' => 0, // Si tienes tracking de desuscriptos
			'clicks' => 0, // Si tienes tracking de clicks
			'unique_opens' => 0, // Si tienes tracking de aperturas únicas
			'ratio' => 0, // Puedes calcular el ratio real
		];

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

		// Obtener links de conversión reales con relaciones
		$links = MessageDeliveryLink::whereIn('message_delivery_id', $deliveries->pluck('id'))
			->with(['messageDelivery.contact'])
			->orderBy('created_at', 'desc')
			->get();

		// Verificar configuración DNS para el dominio del remitente
		$dnsStatus = null;
		$mailbabyUser = null;

		if (!empty($emailConfig['from_address'])) {
			// Obtener el usuario de MailBaby desde la configuración
			$mailbabyUser = config('services.mailbaby.enabled') ? env('MAIL_USERNAME') : null;

			// Verificar configuración DNS
			$dnsStatus = DnsHelper::checkEmailDomainConfiguration(
				$emailConfig['from_address'],
				$mailbabyUser
			);
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
			'mailbabyUser' => $mailbabyUser,
		]);
	}

	/**
	 * Show the form for editing the specified resource.
	 */
	public function edit(string $id)
	{
		$data = Message::find($id);
		$data->types = MessageType::getOptions();
		$data->templates = Template::getOptions();

		if (! $data)
		{
			return redirect()->route('message-list')->with('error', 'Message not found.');
		}

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

		return response()->json(['success' => 'The record has been deleted.'], 200);
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

	public function sendSendGridMessage()
	{
		$data = [
			'to' => env('MAILBOX_USERNAME'),
			'dynamic_template_data' => [
				'name' => env('APP_NAME', 'Laravel'),
				'message' => env('APP_NAME', 'Laravel').' SendGrid Message testing...',
				'unsubscribe_url' => route('unsubscribe', ['email' => env('MAILBOX_USERNAME')]),
			],
		];

		Mail::send(new MySendGridMail($data));
	}

	public function unsubscribe($email)
	{
		$user = User::where('email', $email)->first();

		if ($user)
		{
			$user->subscribed = false;
			$user->save();
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

			// Update message status to active
			$message->update(['status_id' => 1]);

			// Create deliveries if they don't exist and schedule them with random intervals
			$this->populateMessageDeliveries($message);

			// Dispatch jobs for pending deliveries
			$pendingDeliveries = MessageDelivery::where('message_id', $message->id)
				->whereNull('delivered_at') // Not delivered yet
				->where('status_id', 1) // 1 = pending
				->get();

			foreach ($pendingDeliveries as $delivery)
			{
				// Calculate delay based on the scheduled sent_at time
				$delaySeconds = max(0, $delivery->sent_at->diffInSeconds(now()));

				SendMessageCampaignJob::dispatch($delivery)
					->delay($delaySeconds); // Delay based on scheduled time
			}

			return response()->json([
				'success' => true,
				'message' => 'Campaign started successfully. '.$pendingDeliveries->count().' emails queued for sending.',
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
	 * Populate message deliveries for a campaign with scheduled send times
	 */
	private function populateMessageDeliveries(Message $message)
	{
		// Get contacts from the message's category
		$contacts = collect();

		if ($message->category)
		{
			$contacts = $message->category->contacts()->where('status_id', 1)->get();
		} else
		{
			// If no category, get all active contacts from the team
			$contacts = \App\Models\Contact::where('team_id', $message->team_id)
				->where('status_id', 1)
				->whereNotNull('email')
				->get();
		}

		$contactIndex = 0;
		foreach ($contacts as $contact)
		{
			// Check if delivery already exists
			$existingDelivery = MessageDelivery::where('message_id', $message->id)
				->where('contact_id', $contact->id)
				->first();

			if (! $existingDelivery)
			{
				// Schedule with configurable intervals from .env
				$baseMinutes = config('services.email.delay.base_minutes', 5);
				$maxRandomSeconds = config('services.email.delay.random_seconds', 120);

				$baseDelayMinutes = $contactIndex * $baseMinutes; // Configurable minutes between each
				$randomDelaySeconds = rand(0, $maxRandomSeconds); // Configurable random seconds
				$scheduledTime = now()->addMinutes($baseDelayMinutes)->addSeconds($randomDelaySeconds);

				MessageDelivery::create([
					'team_id' => $message->team_id,
					'message_id' => $message->id,
					'contact_id' => $contact->id,
					'status_id' => 1, // 1 = pending
					'sent_at' => $scheduledTime, // Schedule the send time
				]);

				$contactIndex++;
			}
		}
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
				case 'mailgun':
					if (config('services.mailgun.secret'))
					{
						Mail::mailer('mailgun')->to($user->email)->send(new \App\Mail\TestMessageMail($message, $testContact, $htmlContent));
					} else
					{
						Log::warning('TEST SEND: Mailgun not configured, using default SMTP');
						Mail::to($user->email)->send(new \App\Mail\TestMessageMail($message, $testContact, $htmlContent));
					}
					break;
				case 'mailbaby':
					Log::warning('TEST SEND: MailBaby API not supported for test emails, using SMTP');
					Mail::to($user->email)->send(new \App\Mail\TestMessageMail($message, $testContact, $htmlContent));
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
				$htmlContent = str_replace('{{name}}', $sampleContact->name ?? 'John', $htmlContent);
				$htmlContent = str_replace('{{contact_name}}', ($sampleContact->name ?? 'John').' '.($sampleContact->surname ?? 'Doe'), $htmlContent);
				$htmlContent = str_replace('{{email}}', $sampleContact->email ?? 'john.doe@example.com', $htmlContent);
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
			strpos($errorMessage, '550') !== false) {
			return "No se pudo enviar el email de prueba.\nPor favor, contacte con soporte técnico para autorizar la salida de emails desde su dominio.";
		}

		// Check for authentication errors
		if (strpos($errorMessage, '535') !== false ||
			strpos($errorMessage, 'authentication') !== false ||
			strpos($errorMessage, 'login') !== false) {
			return 'Error de autenticación en el servidor de correo. Verifique las credenciales de configuración.';
		}

		// Check for connection errors
		if (strpos($errorMessage, 'connection') !== false ||
			strpos($errorMessage, 'timeout') !== false ||
			strpos($errorMessage, 'refused') !== false) {
			return 'No se pudo conectar al servidor de correo. Verifique la configuración de conexión.';
		}

		// Check for quota exceeded
		if (strpos($errorMessage, 'quota') !== false ||
			strpos($errorMessage, 'limit') !== false ||
			strpos($errorMessage, 'exceeded') !== false) {
			return 'Se ha alcanzado el límite de envío de emails. Contacte con soporte técnico.';
		}

		// Generic error message for unknown errors
		return 'No se pudo enviar el email de prueba. Por favor, contacte con soporte técnico si el problema persiste.';
	}
}
