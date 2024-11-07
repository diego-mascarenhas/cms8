<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\PHPIMAP\ClientManager;

class ProcessEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process incoming emails and store them in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing emails...');

        try {
            $config = [
                'host'          => env('MAILBOX_HOST'),
                'port'          => env('MAILBOX_PORT', 993),
                'encryption'    => env('MAILBOX_ENCRYPTION', 'ssl'),
                'validate_cert' => env('MAILBOX_VALIDATE_CERT', true),
                'username'      => env('MAILBOX_USERNAME'),
                'password'      => env('MAILBOX_PASSWORD'),
                'protocol'      => 'imap'
            ];
            
            $cm = new ClientManager();
            $client = $cm->make($config);

            $client->connect();

            $folder = $client->getFolder('INBOX');
            $messages = $folder->messages()->all()->get();

            foreach($messages as $message) {
                $this->info($message->getFrom() . ': ' . $message->getSubject());
            }
            
            $this->info('Emails processed successfully.');
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
