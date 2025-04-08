<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
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
			
		// Get the last message from each contact
		foreach ($contacts as $contact) {
			$lastMessage = Conversation::where('from', $contact->from)
				->where('channel', 'whatsapp')
				->latest()
				->first();
				
			$contact->last_message = $lastMessage->body;
			$contact->last_message_time = $lastMessage->created_at->diffForHumans();
		}
		
		// If a contact is selected, get their messages
		$selectedPhone = request('phone');
		$messages = collect();
		
		if ($selectedPhone) {
			$messages = Conversation::where('channel', 'whatsapp')
				->where(function($query) use ($selectedPhone) {
					$query->where('from', $selectedPhone)
						  ->orWhere('to', $selectedPhone);
				})
				->orderBy('created_at')
				->get();
		}

		return view('chat.index', compact('contacts', 'messages', 'selectedPhone'));
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
			return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
		}
	}
}
