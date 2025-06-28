<?php

namespace App\Http\Controllers;

use App\Models\Source;
use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\ClientManager;

class MailController extends Controller
{
    public function index()
    {
        $sources = Source::all();
        $emails = $this->mailbox();

        return view('mail.index', compact('sources', 'emails'));
    }

    public function mailbox()
    {
        try {
            // IMAP configuration
            $config = [
                'host' => env('MAILBOX_HOST'),
                'port' => env('MAILBOX_PORT', 993),
                'encryption' => env('MAILBOX_ENCRYPTION', 'ssl'),
                'validate_cert' => env('MAILBOX_VALIDATE_CERT', true),
                'username' => env('MAILBOX_USERNAME'),
                'password' => env('MAILBOX_PASSWORD'),
                'protocol' => 'imap',
            ];

            $cm = new ClientManager;
            $client = $cm->make($config);

            $client->connect();

            $folder = $client->getFolder('INBOX');
            $messages = $folder->messages()->all()->get();

            $emailCollection = collect();

            foreach ($messages as $message) {
                try {
                    $emailCollection->push([
                        'message_id' => $message->getMessageId(),
                        'subject' => $message->getSubject(),
                        'from' => $message->getFrom(),
                        'date' => $message->getDate(),
                        'body' => $message->getRawBody(),
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error processing email: ' . $e->getMessage());
                }
            }

            return $emailCollection;
        } catch (\Exception $e) {
            Log::error('Error in mailbox method', [
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    public function handleIncomingEmail(InboundEmail $email)
    {
        try {
            Log::info('Correo recibido:', [
                'asunto' => $email->subject(),
                'de' => $email->from(),
                'contenido' => $email->text(),
            ]);

            $inboundEmail = InboundEmail::fromMessage($email->message);
            $inboundEmail->save();

            Log::info('Email guardado correctamente en la base de datos con ID: ' . $inboundEmail->id);

            return true;
        } catch (\Exception $e) {
            Log::error('Error al procesar el email: ' . $e->getMessage());

            return false;
        }
    }
}
