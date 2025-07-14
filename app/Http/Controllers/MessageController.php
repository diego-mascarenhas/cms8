<?php

namespace App\Http\Controllers;

use App\DataTables\MessageDataTable;
use App\Mail\MySendGridMail;
use App\Models\Message;
use App\Models\MessageType;
use App\Models\Template;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use stdClass;
use Twilio\Rest\Client;
use App\Helpers\TemplateImportHelper;
use App\Models\MessageDelivery;
use App\Models\MessageDeliveryLink;
use App\Models\MessageDeliveryStat;

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
		if (!$stats_db) {
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

		// Obtener links de conversión reales
		$links = MessageDeliveryLink::whereIn('message_delivery_id', $deliveries->pluck('id'))->get();

		return view('message.show', [
			'message' => $message,
			'stats' => $stats,
			'stats_db' => $stats_db,
			'deliveries' => $deliveries,
			'links' => $links,
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

		if (! $data) {
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

		try {
			$client = new Client($sid, $token);
			$client->messages->create($receiverNumber, [
				'from' => $fromNumber,
				'body' => $message,
			]);

			return response()->json(['status' => 'SMS Message Sent Successfully.']);
		} catch (\Twilio\Exceptions\RestException $e) {
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
		try {
			$client = new Client($sid, $token);

			$client->messages->create($receiverNumber, [
				'from' => $fromNumber,
				'body' => $message,
			]);

			return response()->json(['status' => 'WhatsApp Message Sent Successfully.']);
		} catch (\Twilio\Exceptions\RestException $e) {
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

		if ($user) {
			$user->subscribed = false;
			$user->save();
		}

		return view('message.unsubscribe', ['email' => $email]);
	}
}
