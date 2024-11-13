<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\PHPIMAP\ClientManager;
use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Support\Facades\Log;

class GetEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:get {--debug : Show debug information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get and store incoming emails in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $debug = $this->option('debug');

        if ($debug)
        {
            $this->info('Getting emails...');
        }

        try
        {
            // IMAP configuration
            $config = [
                'host' => env('MAILBOX_HOST'),
                'port' => env('MAILBOX_PORT', 993),
                'encryption' => env('MAILBOX_ENCRYPTION', 'ssl'),
                'validate_cert' => env('MAILBOX_VALIDATE_CERT', true),
                'username' => env('MAILBOX_USERNAME'),
                'password' => env('MAILBOX_PASSWORD'),
                'protocol' => 'imap'
            ];

            $cm = new ClientManager();
            $client = $cm->make($config);

            $client->connect();

            $folder = $client->getFolder('INBOX');
            $messages = $folder->messages()->all()->get();

            $processedCount = 0;
            $skippedCount = 0;

            InboundEmail::unsetEventDispatcher();

            foreach ($messages as $message)
            {
                dd($message);
                // Get the complete Message-ID
                $messageId = $message->getMessageId();

                if ($debug)
                {
                    $this->line("\nDEBUG INFO:");
                    $this->line("Complete Message-ID: " . $messageId);
                    $this->line("From: " . $message->getFrom()[0]->mail);
                    $this->line("Subject: " . $message->getSubject());
                }

                // Check if email already exists
                $exists = InboundEmail::where('message_id', $messageId)->exists();

                if ($exists)
                {
                    if ($debug)
                    {
                        $this->line("<fg=yellow>Skipping duplicate email with Message-ID: " . $messageId . "</>");
                    }
                    $skippedCount++;
                    continue;
                }

                try
                {
                    // // Modify raw message to include complete Message-ID
                    // $rawBody = $message->getRawBody();
                    // $completeMessageId = "Message-ID: " . $messageId . "\r\n";

                    // // Insert complete Message-ID at the start of headers
                    // $modifiedRawBody = preg_replace('/^/', $completeMessageId, $rawBody);

                    // if ($debug)
                    // {
                    //     $this->line("\nModified headers:");
                    //     $this->line(substr($modifiedRawBody, 0, 500)); // Show first 500 chars
                    // }

                    // $inboundEmail = InboundEmail::fromMessage($modifiedRawBody);
                    // $inboundEmail->save();

                    $inboundEmail = new InboundEmail();

                    $inboundEmail->forceFill([
                        'message_id' => $messageId,
                        'message' => $messageId . "\r\n" . $message->getRawBody()
                    ]);

                    $inboundEmail->save();

                    if ($debug)
                    {
                        $this->line("Saved with message_id: " . $inboundEmail->message_id);
                        $this->info("Email saved with ID: " . $inboundEmail->id);

                        $processedCount++;
                    }
                }
                catch (\Exception $e)
                {
                    $this->error("Error saving email: " . $e->getMessage());

                    Log::error('Error saving email', [
                        'error' => $e->getMessage(),
                        'message_id' => $messageId,
                        'subject' => $message->getSubject()
                    ]);
                }
            }

            // Show summary
            if ($debug)
            {
                $this->line("\n=====================================");
                $this->info("Process completed:");
                $this->line("- New emails processed: " . $processedCount);
                $this->line("- Duplicate emails skipped: " . $skippedCount);
                $this->line("=====================================");
            }

        }
        catch (\Exception $e)
        {
            $this->error('Error: ' . $e->getMessage());
            Log::error('Error in emails:get command', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
