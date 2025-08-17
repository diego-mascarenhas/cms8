<?php

namespace App\Console\Commands;

use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;

class TestEmailSending extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test-send {team_id?} {--to=} {--subject=Test Email} {--message=This is a test email from Humano.App}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email sending using team configuration or global .env settings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $teamId = $this->argument('team_id');
        $to = $this->option('to');
        $subject = $this->option('subject');
        $message = $this->option('message');

        if (!$to) {
            $to = $this->ask('Enter recipient email address');
        }

        if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('Please provide a valid email address.');
            return;
        }

        $this->info("Testing email sending to: {$to}");

        if ($teamId) {
            $team = Team::find($teamId);
            if (!$team) {
                $this->error("Team with ID {$teamId} not found.");
                return;
            }

            $this->info("Using team configuration: {$team->name} (ID: {$team->id})");
            $this->configureMailForTeam($team);
        } else {
            $this->info("Using global .env configuration");
        }

        try {
            // Show current configuration
            $this->showMailConfiguration();

            // Send test email
            Mail::raw($message, function ($mail) use ($to, $subject) {
                $mail->to($to)
                     ->subject($subject);
            });

            $this->info("✅ Email sent successfully to {$to}!");

        } catch (\Exception $e) {
            $this->error("❌ Failed to send email: " . $e->getMessage());
        }
    }

    /**
     * Configure mail settings for a specific team
     */
    private function configureMailForTeam(Team $team)
    {
        // Get team email configuration with fallback to .env
        $config = $team->getOutgoingEmailConfig();

        // Configure mail settings
        Config::set('mail.mailers.smtp.host', $config['host']);
        Config::set('mail.mailers.smtp.port', $config['port']);
        Config::set('mail.mailers.smtp.username', $config['username']);
        Config::set('mail.mailers.smtp.password', $config['password']);
        Config::set('mail.mailers.smtp.encryption', $config['encryption']);
        Config::set('mail.from.address', $config['from_address']);
        Config::set('mail.from.name', $config['from_name']);
    }

    /**
     * Show current mail configuration
     */
    private function showMailConfiguration()
    {
        $this->line("\nCurrent Mail Configuration:");
        $this->line("Host: " . config('mail.mailers.smtp.host'));
        $this->line("Port: " . config('mail.mailers.smtp.port'));
        $this->line("Username: " . config('mail.mailers.smtp.username'));
        $this->line("Encryption: " . config('mail.mailers.smtp.encryption'));
        $this->line("From Address: " . config('mail.from.address'));
        $this->line("From Name: " . config('mail.from.name'));
        $this->line("");
    }
}
