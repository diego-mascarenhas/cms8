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

        try {
            $emails = $this->mailbox();
        } catch (\Exception $e) {
            Log::error('Error getting emails: ' . $e->getMessage());
            $emails = collect(); // Return empty collection on error
        }

        return view('mail.index', compact('sources', 'emails'));
    }

    public function mailbox()
    {
        try {
            // Get current team
            $team = auth()->user()->currentTeam;
            if (!$team) {
                Log::warning('No current team found for user');
                return collect();
            }

            // Get IMAP configuration from team settings with fallback to .env
            $imapHost = $team->getSetting('imap_host') ?: env('MAILBOX_HOST');
            $imapUsername = $team->getSetting('imap_username') ?: env('MAILBOX_USERNAME');
            $imapPassword = $team->getSetting('imap_password') ?: env('MAILBOX_PASSWORD');
            $imapPort = $team->getSetting('imap_port') ?: env('MAILBOX_PORT', 993);
            $imapEncryption = $team->getSetting('imap_encryption') ?: env('MAILBOX_ENCRYPTION', 'ssl');

            if (!$imapHost || !$imapUsername || !$imapPassword) {
                Log::warning('IMAP configuration incomplete (team and .env)', [
                    'team_id' => $team->id,
                    'has_host' => !empty($imapHost),
                    'has_username' => !empty($imapUsername),
                    'has_password' => !empty($imapPassword),
                ]);
                return collect();
            }

            // IMAP configuration from team settings with .env fallback
            $config = [
                'host' => $imapHost,
                'port' => $imapPort,
                'encryption' => $imapEncryption,
                'validate_cert' => env('MAILBOX_VALIDATE_CERT', true),
                'username' => $imapUsername,
                'password' => $imapPassword,
                'protocol' => 'imap',
            ];

            Log::info('Connecting to IMAP with team configuration', [
                'team_id' => $team->id,
                'host' => $config['host'],
                'port' => $config['port'],
                'encryption' => $config['encryption'],
            ]);

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
                    Log::error('Error processing email: '.$e->getMessage());
                }
            }

            Log::info('Successfully retrieved emails', [
                'team_id' => $team->id,
                'count' => $emailCollection->count(),
            ]);

            return $emailCollection;
        } catch (\Exception $e) {
            Log::error('Error in mailbox method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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

            Log::info('Email guardado correctamente en la base de datos con ID: '.$inboundEmail->id);

            return true;
        } catch (\Exception $e) {
            Log::error('Error al procesar el email: '.$e->getMessage());

            return false;
        }
    }
}
