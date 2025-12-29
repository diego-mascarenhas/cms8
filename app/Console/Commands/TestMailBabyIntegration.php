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
    protected $signature = 'mailbaby:test 
                            {--to= : Recipient email address}
                            {--from= : Sender email address (overrides config)}
                            {--subject=Test Email : Email subject}
                            {--message=This is a test email : Email message}
                            {--use-smtp : Use SMTP instead of API for testing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test MailBaby integration (API or SMTP)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $useSmtp = $this->option('use-smtp');

        if ($useSmtp)
        {
            return $this->testSmtp();
        }

        return $this->testMailBabyApi();
    }

    /**
     * Test MailBaby API integration
     */
    private function testMailBabyApi()
    {
        $mailBabyService = app(MailBabyService::class);

        // Check configuration
        if (! config('services.mailbaby.api_key'))
        {
            $this->error('❌ MailBaby API key not configured. Please set MAILBABY_API_KEY in your .env file.');
            $this->line('');
            $this->line('💡 Tip: Use --use-smtp flag to test SMTP instead');

            return 1;
        }

        $this->info('🧪 Testing MailBaby API Integration');
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

        // Use custom FROM if provided, otherwise use config
        $from = $this->option('from') ?: config('mail.from.address');
        $fromName = config('mail.from.name');

        $this->info('2. Testing email sending...');
        $this->line('   To: '.$to);
        $this->line('   From: '.$fromName.' <'.$from.'>');

        $emailData = [
            'to' => $to,
            'from' => $fromName.' <'.$from.'>',
            'subject' => $this->option('subject'),
            'body' => '<html><body><h1>Test Email (API)</h1><p>'.$this->option('message').'</p><p>Sent via MailBaby API at '.now().'</p></body></html>',
            'message_id' => 'test-'.time(),
        ];

        $result = $mailBabyService->sendEmail($emailData);

        if ($result['success'])
        {
            $this->info('✅ Email sent successfully via API!');
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
            $this->error('❌ Failed to send email via API:');
            $this->line('   Error: '.($result['error'] ?? 'Unknown error'));
            $this->line('');
            $this->line('💡 Tip: Use --use-smtp flag to test SMTP instead');

            return 1;
        }

        $this->line('');
        $this->info('🎉 MailBaby API test completed successfully!');
        $this->line('');
        $this->line('Next steps:');
        $this->line('1. Set MAILBABY_ENABLED=true in your .env to enable API for all emails');
        $this->line('2. Configure webhook URL in MailBaby dashboard: '.url('webhooks/mailbaby'));
        $this->line('3. Set MAILBABY_WEBHOOK_SECRET in your .env for webhook security');

        return 0;
    }

    /**
     * Test SMTP sending
     */
    private function testSmtp()
    {
        $this->info('🧪 Testing SMTP Integration');
        $this->line('');

        // Get recipient
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

        // Use custom FROM if provided, otherwise use config
        $from = $this->option('from') ?: config('mail.from.address');
        $fromName = config('mail.from.name');

        $this->info('1. Testing SMTP configuration...');
        $this->line('   SMTP Host: '.config('mail.host'));
        $this->line('   SMTP Port: '.config('mail.port'));
        $this->line('   SMTP User: '.config('mail.username'));
        $this->line('   Encryption: '.config('mail.encryption'));
        $this->line('');

        $this->info('2. Sending test email...');
        $this->line('   To: '.$to);
        $this->line('   From: '.$fromName.' <'.$from.'>');

        try
        {
            // Temporarily override FROM if custom one provided
            $originalFrom = config('mail.from.address');
            if ($this->option('from'))
            {
                config(['mail.from.address' => $this->option('from')]);
            }

            \Mail::raw(
                "This is a test email sent via SMTP.\n\n".
                'Subject: '.$this->option('subject')."\n".
                'Message: '.$this->option('message')."\n\n".
                'Sent at: '.now()."\n".
                'From: '.$fromName.' <'.$from.'>',
                function ($message) use ($to, $from, $fromName)
                {
                    $message->to($to)
                        ->from($from, $fromName)
                        ->subject($this->option('subject'));
                },
            );

            // Restore original FROM
            config(['mail.from.address' => $originalFrom]);

            $this->info('✅ Email sent successfully via SMTP!');
            $this->line('');
            $this->info('🎉 SMTP test completed successfully!');
            $this->line('');
            $this->line('Your SMTP configuration is working correctly.');

            return 0;
        } catch (\Exception $e)
        {
            $this->error('❌ Failed to send email via SMTP:');
            $this->line('   Error: '.$e->getMessage());
            $this->line('');
            $this->line('Please check your SMTP configuration in .env:');
            $this->line('- MAIL_HOST');
            $this->line('- MAIL_PORT');
            $this->line('- MAIL_USERNAME');
            $this->line('- MAIL_PASSWORD');
            $this->line('- MAIL_ENCRYPTION');

            return 1;
        }
    }
}
