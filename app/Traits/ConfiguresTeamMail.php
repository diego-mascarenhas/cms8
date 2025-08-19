<?php

namespace App\Traits;

use App\Models\Team;
use Illuminate\Support\Facades\Config;

trait ConfiguresTeamMail
{
    /**
     * Configure mail settings for a specific team.
     * If team has custom SMTP, use it. Otherwise, use system SMTP with advertising.
     */
    protected function configureMailForTeam(Team $team)
    {
        \Log::info('🔧 ConfiguresTeamMail: Starting SMTP configuration', [
            'team_id' => $team->id,
            'team_name' => $team->name,
            'has_custom_smtp' => $team->hasOutgoingEmailConfig(),
            'current_env_host' => env('MAIL_HOST'),
            'current_env_username' => env('MAIL_USERNAME'),
        ]);

        // Check if team has its own email configuration
        if ($team->hasOutgoingEmailConfig()) {
            // Use team's custom SMTP configuration
            $config = $team->getOutgoingEmailConfig();

            \Log::info('✅ Using TEAM custom SMTP configuration', [
                'team_id' => $team->id,
                'smtp_host' => $config['host'],
                'smtp_port' => $config['port'],
                'smtp_username' => $config['username'],
                'smtp_encryption' => $config['encryption'],
                'from_address' => $config['from_address'],
                'from_name' => $config['from_name'],
                'password_configured' => ! empty($config['password']),
            ]);

            Config::set('mail.mailers.smtp.host', $config['host']);
            Config::set('mail.mailers.smtp.port', $config['port']);
            Config::set('mail.mailers.smtp.username', $config['username']);
            Config::set('mail.mailers.smtp.password', $config['password']);
            Config::set('mail.mailers.smtp.encryption', $config['encryption']);
            Config::set('mail.from.address', $config['from_address']);
            Config::set('mail.from.name', $config['from_name']);

            // No advertising footer for teams with custom SMTP
            Config::set('app.mail_advertising_footer', '');

            \Log::info('✅ Team SMTP configuration applied successfully', [
                'team_id' => $team->id,
                'final_host' => config('mail.mailers.smtp.host'),
                'final_port' => config('mail.mailers.smtp.port'),
                'final_username' => config('mail.mailers.smtp.username'),
                'final_from_address' => config('mail.from.address'),
            ]);

        } else {
            // Use system SMTP configuration with advertising footer
            \Log::info('📧 Using SYSTEM SMTP with advertising footer', [
                'team_id' => $team->id,
                'system_host' => env('MAIL_HOST'),
                'system_username' => env('MAIL_USERNAME'),
                'system_from_address' => env('MAIL_FROM_ADDRESS'),
            ]);

            // The system configuration is already loaded from .env
            // Just ensure we have the advertising footer
            $advertisingFooter = $team->getAdvertisingFooter();
            Config::set('app.mail_advertising_footer', $advertisingFooter);

            // However, if team has from_name/from_address settings, use those
            $fromName = $team->getSetting('mail_from_name');
            $fromAddress = $team->getSetting('mail_from_address');

            if ($fromName) {
                Config::set('mail.from.name', $fromName);
                \Log::info('📝 Custom from_name applied', ['from_name' => $fromName]);
            }
            if ($fromAddress) {
                Config::set('mail.from.address', $fromAddress);
                \Log::info('📝 Custom from_address applied', ['from_address' => $fromAddress]);
            }

            \Log::info('✅ System SMTP configuration confirmed', [
                'team_id' => $team->id,
                'final_host' => config('mail.mailers.smtp.host'),
                'final_username' => config('mail.mailers.smtp.username'),
                'final_from_address' => config('mail.from.address'),
                'advertising_footer_length' => strlen($advertisingFooter),
            ]);
        }
    }

    /**
     * Get the appropriate "from" address for a team.
     * Uses team setting if available, otherwise system default.
     */
    protected function getFromAddressForTeam(Team $team)
    {
        if ($team->hasOutgoingEmailConfig()) {
            $config = $team->getOutgoingEmailConfig();

            return $config['from_address'];
        }

        return config('mail.from.address');
    }

    /**
     * Get the appropriate "from" name for a team.
     * Uses team setting if available, otherwise system default.
     */
    protected function getFromNameForTeam(Team $team)
    {
        if ($team->hasOutgoingEmailConfig()) {
            $config = $team->getOutgoingEmailConfig();

            return $config['from_name'];
        }

        return config('mail.from.name');
    }

    /**
     * Check if team should show advertising footer.
     */
    protected function shouldShowAdvertisingForTeam(Team $team)
    {
        return $team->isUsingSystemSmtp();
    }
}
