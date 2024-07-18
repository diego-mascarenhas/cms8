<?php

namespace App\Http\Controllers;

use App\DataTables\MessageDataTable;
use App\Models\Category;
use App\Models\Message;
use App\Models\MessageType;
use App\Models\Template;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

use Twilio\Rest\Client;
use App\Mail\MySendGridMail;

use stdClass;

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
        $data = new stdClass();
        $data->categories = Category::categories();
        $data->types = MessageType::types();
        $data->templates = Template::templates();

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

        $data['status'] = $request->has('status') ? 1 : 0;

        Message::updateOrCreate(
            ['id' => $request->id],
            [
                'name' => $data['name'],
                'category_id' => $data['category_id'],
                'type_id' => $data['type_id'],
                'text' => $data['text'],
                'status' => $data['status'],
            ]
        );

        return redirect()->route('app-mkt-message-list')->with('success', 'Record saved successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = Message::find($id);
        $data->categories = Category::getOptions();
        $data->types = MessageType::getOptions();
        $data->templates = Template::getOptions();

        if (!$data)
        {
            return redirect()->route('app-mkt-message-list')->with('error', 'Message not found.');
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
        $receiverNumber = '+34722372858'; // +5491155687301 // +5491138738376
        $message = 'CMS8+ SMS Message testing...';

        $sid = env('TWILIO_SID');
        $token = env('TWILIO_TOKEN');
        $fromNumber = env('TWILIO_SMS_FROM');

        try
        {
            $client = new Client($sid, $token);
            $client->messages->create($receiverNumber, [
                'from' => $fromNumber,
                'body' => $message
            ]);

            return response()->json(['status' => 'SMS Message Sent Successfully.']);
        }
        catch (\Twilio\Exceptions\RestException $e)
        {
            return response()->json(['error' => 'Error: ' . $e->getMessage()], 400);
        }
    }

    public function sendWhatsAppMessage(Request $request)
    {
        $receiverNumber = 'whatsapp:' . '+34722372858';
        $message = 'CMS8+ WhatsApp Message testing...';

        $sid = env('TWILIO_SID');
        $token = env('TWILIO_TOKEN');
        $fromNumber = env('TWILIO_WHATSAPP_FROM');
        try
        {
            $client = new Client($sid, $token);

            $client->messages->create($receiverNumber, [
                'from' => $fromNumber,
                'body' => $message
            ]);

            return response()->json(['status' => 'WhatsApp Message Sent Successfully.']);
        }
        catch (\Twilio\Exceptions\RestException $e)
        {
            return response()->json(['error' => 'Error: ' . $e->getMessage()], 400);
        }
    }

    public function sendSendGridMessage()
    {
        $data = [
            'to' => 'diego@revisionalpha.es',
            'dynamic_template_data' => [
                'name' => 'Diego Mascarenhas',
                'message' => 'CMS8+ SendGrid Message testing...',
                'unsubscribe_url' => route('unsubscribe', ['email' => 'diego@revisionalpha.es']),
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

}
