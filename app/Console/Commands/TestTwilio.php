<?php

namespace App\Console\Commands;

use App\Services\TwilioService;
use Illuminate\Console\Command;

class TestTwilio extends Command
{
    protected $signature = 'twilio:test {--type=sms} {--to=} {--message=}';
    protected $description = 'Test Twilio messaging functionality';

    public function handle(TwilioService $twilioService)
    {
        $type = $this->option('type');
        $to = $this->option('to');
        $message = $this->option('message') ?? 'Test message from Laravel';

        if (!$to) {
            $this->error('Please provide a phone number with --to option');
            return 1;
        }

        try {
            if ($type === 'sms') {
                $result = $twilioService->sendSms($to, $message);
            } else {
                $result = $twilioService->sendWhatsApp($to, $message);
            }

            $this->info('Message sent successfully!');
            $this->info('Message SID: ' . $result->sid);
            return 0;
        } catch (\Exception $e) {
            $this->error('Error sending message: ' . $e->getMessage());
            return 1;
        }
    }
} 