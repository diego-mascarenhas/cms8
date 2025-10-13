<?php

namespace App\Console\Commands;

use App\Services\MailBabyService;
use Illuminate\Console\Command;

class TestMailBabyIntegration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailbaby:test {--to=} {--subject=Test Email from MailBaby} {--message=This is a test email sent via MailBaby API}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test MailBaby API integration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $mailBabyService = app(MailBabyService::class);

        // Check configuration
        if (! config('services.mailbaby.api_key'))
        {
            $this->error('❌ MailBaby API key not configured. Please set MAILBABY_API_KEY in your .env file.');

            return 1;
        }

        $this->info('🧪 Testing MailBaby Integration');
        $this->line('');

        // Test account info
        $this->info('1. Testing account information...');
        $accountInfo = $mailBabyService->getAccountInfo();

        if ($accountInfo)
        {
            $this->info('✅ Account Info Retrieved:');
            $this->line('   Username: '.($accountInfo['username'] ?? 'N/A'));
            $this->line('   Credits: '.($accountInfo['credits'] ?? 'N/A'));
            $this->line('   Status: '.($accountInfo['status'] ?? 'N/A'));
        } else
        {
            $this->error('❌ Failed to retrieve account information');

            return 1;
        }

        $this->line('');

        // Test email sending
        $to = $this->option('to');
        if (! $to)
        {
            $to = $this->ask('Enter recipient email address');
        }

        if (! $to || ! filter_var($to, FILTER_VALIDATE_EMAIL))
        {
            $this->error('❌ Please provide a valid email address.');

            return 1;
        }

        $this->info('2. Testing email sending...');
        $this->line('   To: '.$to);
        $this->line('   From: '.config('mail.from.address'));

        $emailData = [
            'to' => $to,
            'from' => config('mail.from.address'),
            'subject' => $this->option('subject'),
            'body' => '<html><body><h1>Test Email</h1><p>'.$this->option('message').'</p><p>Sent via MailBaby API at '.now().'</p></body></html>',
            'message_id' => 'test-'.time(),
        ];

        $result = $mailBabyService->sendEmail($emailData);

        if ($result['success'])
        {
            $this->info('✅ Email sent successfully!');
            $this->line('   Provider Message ID: '.($result['message_id'] ?? 'N/A'));

            // Test status check
            if (isset($result['message_id']))
            {
                $this->line('');
                $this->info('3. Testing status check...');
                sleep(2); // Wait a moment

                $status = $mailBabyService->getEmailStatus($result['message_id']);
                if ($status)
                {
                    $this->info('✅ Status Retrieved:');
                    $this->line('   Status: '.($status['status'] ?? 'N/A'));
                    $this->line('   Delivered: '.($status['delivered'] ?? 'N/A'));
                } else
                {
                    $this->warning('⚠️  Could not retrieve email status (this might be normal for new emails)');
                }
            }
        } else
        {
            $this->error('❌ Failed to send email:');
            $this->line('   Error: '.($result['error'] ?? 'Unknown error'));

            return 1;
        }

        $this->line('');
        $this->info('🎉 MailBaby integration test completed successfully!');
        $this->line('');
        $this->line('Next steps:');
        $this->line('1. Set MAILBABY_ENABLED=true in your .env to enable MailBaby for all emails');
        $this->line('2. Configure webhook URL in MailBaby dashboard: '.url('webhooks/mailbaby'));
        $this->line('3. Set MAILBABY_WEBHOOK_SECRET in your .env for webhook security');

        return 0;
    }
}
