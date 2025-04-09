<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
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
		foreach ($contacts as $contact) {
			$lastMessage = Conversation::where('from', $contact->from)
				->where('channel', 'whatsapp')
				->latest()
				->first();
				
			$contact->last_message = $lastMessage->body;
			$contact->last_message_time = $lastMessage->created_at->diffForHumans();
			
			// Get user information if available
			$userData = $this->getUserByPhone($contact->from);
			if ($userData) {
				$contact->user_name = $userData->name;
				$contact->user_photo = $userData->profile_photo_path;
				$contact->user_id = $userData->id;
			}
		}
		
		// If a contact is selected, get their messages
		$selectedPhone = request('phone');
		$messages = collect();
		$selectedUser = null;
		
		if ($selectedPhone) {
			// Get all messages for this conversation
			$messages = Conversation::where('channel', 'whatsapp')
				->where(function($query) use ($selectedPhone) {
					$query->where('from', $selectedPhone)
						  ->orWhere('to', $selectedPhone);
				})
				->orderBy('created_at')
				->get();
			
			// Get user information for the header
			$selectedUser = $this->getUserByPhone($selectedPhone);
		}

		return view('chat.index', compact('contacts', 'messages', 'selectedPhone', 'selectedUser'));
	}
	
	/**
	 * Get user by phone number
	 * This handles extracting the digits from WhatsApp format and finding the user
	 */
	private function getUserByPhone($phoneNumber)
	{
		// Debug the original phone number
		\Log::info('Original phone number: ' . $phoneNumber);
		
		// Clean the phone number (remove whatsapp: prefix, plus sign, and any non-digits)
		$cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
		\Log::info('Cleaned phone number: ' . $cleanNumber);
		
		// First try with exactly this number (for format like "34722372858")
		$user = User::where('phone', (int)$cleanNumber)->first();
		
		if (!$user && strlen($cleanNumber) > 9) {
			// If not found and number is long enough, try without country code
			$withoutCountryCode = substr($cleanNumber, -9);
			\Log::info('Trying without country code: ' . $withoutCountryCode);
			$user = User::where('phone', (int)$withoutCountryCode)->first();
		}
		
		if ($user) {
			\Log::info('User found: ' . $user->name);
			
			// Ensure we use the profile_photo_path directly
			$user->user_photo = $user->profile_photo_path;
		} else {
			\Log::info('No user found for this phone number');
		}
		
		return $user;
	}
	
	public function getMessages(Request $request, $phone)
	{
		$messages = Conversation::where('channel', 'whatsapp')
			->where(function($query) use ($phone) {
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
			'message' => 'required|string'
		]);
		
		$twilioService = app(\App\Services\TwilioService::class);
		
		try {
			// Send message
			$result = $twilioService->sendWhatsApp($request->to, $request->message);
			
			return response()->json(['success' => true, 'message' => 'Message sent']);
		} catch (\Exception $e) {
			// If it fails because it's outside the 24-hour window, try sending with template
			if (strpos($e->getMessage(), '63016') !== false) {
				return $this->sendWithTemplate($request);
			}
			
			return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
		}
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
		
		$twilioService = app(\App\Services\TwilioService::class);
		
		try {
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
		} catch (\Exception $e) {
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
