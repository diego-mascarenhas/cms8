<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Contact;
use Illuminate\Http\Request;
use App\Helpers\TextHelper;
use App\Services\TwilioService;
use App\Services\ClaudeService;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
	public function index()
	{
		// Get unique WhatsApp contacts (phone numbers)
		$contacts = Conversation::where('channel', 'whatsapp')
			->selectRaw('DISTINCT `from`, MAX(created_at) as last_message_at')
			->where('direction', 'inbound')
			->groupBy('from')
			->orderBy('last_message_at', 'desc')
			->get();

		// Get the last message from each contact and enrich with user data
		foreach ($contacts as $contact)
		{
			$lastMessage = Conversation::where('from', $contact->from)
				->where('channel', 'whatsapp')
				->latest()
				->first();

			$contact->last_message = $lastMessage->body;
			$contact->last_message_time = $lastMessage->created_at->diffForHumans();

			// Get user information if available
			$userData = $this->getUserByPhone($contact->from);
			if ($userData)
			{
				$contact->user_name = $userData->name;
				$contact->user_photo = $userData->profile_photo_path;
				$contact->user_id = $userData->id;
			}
		}

		// If a contact is selected, get their messages
		$selectedPhone = request('phone');
		$messages = collect();
		$selectedUser = null;

		if ($selectedPhone)
		{
			// Get all messages for this conversation
			$messages = Conversation::where('channel', 'whatsapp')
				->where(function ($query) use ($selectedPhone)
				{
					$query->where('from', $selectedPhone)
						->orWhere('to', $selectedPhone);
				})
				->orderBy('created_at')
				->get();

			// Get user information for the header
			$selectedUser = $this->getUserByPhone($selectedPhone);
		}

		$hasContact = false;
		if ($selectedUser && $selectedUser->id)
		{
			$hasContact = Contact::where('user_id', $selectedUser->id)->exists();
		}

		$userIds = $messages->pluck('user_id')->filter()->unique();
		$users = User::whereIn('id', $userIds)->get()->keyBy('id');

		foreach ($messages as $message)
		{
			$message->body = TextHelper::sanitizeAndLink($message->body);
		}

		return view('chat.index', compact('contacts', 'messages', 'selectedPhone', 'selectedUser', 'hasContact', 'users'));
	}

	/**
	 * Get user by phone number
	 * This handles extracting the digits from WhatsApp format and finding the user
	 */
	private function getUserByPhone($phoneNumber)
	{
		// Clean the phone number (remove whatsapp: prefix, plus sign, and any non-digits)
		$cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

		// Always try to get user directly by phone (full number)
		$user = User::where('phone', $cleanNumber)->first();
		if ($user)
		{
			$user->user_photo = $user->profile_photo_path;
			return $user;
		}

		// Try without country code if not found
		if (strlen($cleanNumber) > 9)
		{
			$withoutCountryCode = substr($cleanNumber, -9);
			$user = User::where('phone', $withoutCountryCode)->first();
			if ($user)
			{
				$user->user_photo = $user->profile_photo_path;
				return $user;
			}
		}

		// If no user found directly, try to find through contact relationship
		$contact = Contact::whereHas('sources', function ($query) use ($cleanNumber)
		{
			$query->where('source_id', 2) // Phone source
				->where('value', $cleanNumber);
		})->first();

		if ($contact && $contact->user)
		{
			$user = $contact->user;
			$user->user_photo = $user->profile_photo_path;
			return $user;
		}

		return null;
	}

	public function getMessages(Request $request, $phone)
	{
		$messages = Conversation::where('channel', 'whatsapp')
			->where(function ($query) use ($phone)
			{
				$query->where('from', $phone)
					->orWhere('to', $phone);
			})
			->orderBy('created_at')
			->get();

		return response()->json(['messages' => $messages]);
	}

	public function sendMessage(Request $request)
	{
		$request->validate([
			'to' => 'required|string',
			'message' => 'required|string',
			'use_ai' => 'boolean'
		]);

		$twilioService = app(TwilioService::class);

		try
		{
			// Check if AI assistance was requested
			if ($request->input('use_ai', false)) {
				// Get chat history for context
				$history = $this->getChatHistory($request->to, 10);
				
				// Process with Claude
				$claudeResponse = $this->processWithClaude($request->message, $history);
				
				// If Claude responded successfully, use its response
				if ($claudeResponse['success']) {
					$aiMessage = $claudeResponse['text'];
					
					// Send the AI message
					$result = $twilioService->sendWhatsApp($request->to, $aiMessage);
					
					return response()->json([
						'success' => true, 
						'message' => 'AI assistant message sent',
						'ai_used' => true,
						'ai_response' => $aiMessage
					]);
				}
				
				// If Claude failed, continue with original message
				Log::warning('Claude AI failed, sending original message: ' . $claudeResponse['message']);
			}
			
			// Send original message
			$result = $twilioService->sendWhatsApp($request->to, $request->message);

			return response()->json(['success' => true, 'message' => 'Message sent']);
		}
		catch (\Exception $e)
		{
			// If it fails because it's outside the 24-hour window, try sending with template
			if (strpos($e->getMessage(), '63016') !== false)
			{
				return $this->sendWithTemplate($request);
			}

			return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
		}
	}

	/**
	 * Process a message with Claude to get AI assistance
	 * 
	 * @param string $message The user message
	 * @param array $history Previous conversation history
	 * @return array Response from Claude
	 */
	private function processWithClaude($message, $history = [])
	{
		$claudeService = app(ClaudeService::class);
		return $claudeService->chat($message, $history);
	}
	
	/**
	 * Get recent chat history to provide context for the AI
	 * 
	 * @param string $phone The phone number
	 * @param int $limit Number of messages to retrieve
	 * @return array Conversation history
	 */
	private function getChatHistory($phone, $limit = 10)
	{
		return Conversation::where('channel', 'whatsapp')
			->where(function ($query) use ($phone) {
				$query->where('from', $phone)
					->orWhere('to', $phone);
			})
			->orderBy('created_at', 'desc')
			->limit($limit)
			->get()
			->sortBy('created_at')
			->values()
			->toArray();
	}

	/**
	 * Send a message using WhatsApp templates
	 * Used for first contact or when outside the 24-hour window
	 */
	public function sendWithTemplate(Request $request)
	{
		$request->validate([
			'to' => 'required|string',
			'message' => 'required|string',
			'template' => 'string|nullable'
		]);

		$twilioService = app(TwilioService::class);

		try
		{
			// Determine which template to use
			$defaultTemplate = config('services.twilio.default_template', 'customer_support');
			$templateName = $request->template ?? $defaultTemplate;

			// Adapt the message as a template parameter
			$parameters = ['message' => $request->message];

			// Send using template
			$result = $twilioService->sendWhatsAppTemplate(
				$request->to,
				$templateName,
				$parameters
			);

			return response()->json([
				'success' => true,
				'message' => 'Template message sent',
				'template_used' => $templateName
			]);
		}
		catch (\Exception $e)
		{
			return response()->json([
				'success' => false,
				'error' => $e->getMessage(),
				'tip' => 'Make sure you have approved templates in Twilio'
			], 500);
		}
	}

	/**
	 * Direct endpoint for sending messages with template
	 */
	public function sendTemplateMessage(Request $request)
	{
		return $this->sendWithTemplate($request);
	}
}
