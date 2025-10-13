<?php

namespace App\Http\Controllers;

use Webklex\PHPIMAP\ClientManager;

class EmailController extends Controller
{
    public function fetchEmails()
    {
        $cm = new ClientManager;
        // $client = $cm->account('default');

        $client = $cm->make([
            'host' => env('IMAP_HOST'),
            'port' => env('IMAP_PORT'),
            'protocol' => env('IMAP_PROTOCOL', 'imap'),
            'encryption' => env('IMAP_ENCRYPTION'),
            'validate_cert' => env('IMAP_VALIDATE_CERT'),
            'username' => env('IMAP_USERNAME'),
            'password' => env('IMAP_PASSWORD'),
            'timeout' => env('IMAP_TIMEOUT', 60),
        ]);

        // $client->connect();
        try
        {
            $client->connect();
        } catch (\Exception $e)
        {
            return response()->json(['error' => 'Error connecting to the email server: '.$e->getMessage()], 500);
        }

        $folders = $client->getFolders();

        $emailData = [];

        foreach ($folders as $folder)
        {
            $messages = $folder->messages()->all()->get();

            foreach ($messages as $message)
            {
                $emailData[] = [
                    'subject' => $message->getSubject(),
                    'attachments' => $message->getAttachments()->count(),
                    'body' => $message->getHTMLBody(),
                ];

                // if ($message->move('INBOX.read') == true) {
                //	 echo 'Message has been moved';
                // } else {
                //	 echo 'Message could not be moved';
                // }
            }
        }

        return response()->json($emailData);
    }
}
