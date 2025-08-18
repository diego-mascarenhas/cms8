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
        // Check if team has its own email configuration
        if ($team->hasOutgoingEmailConfig()) {
            // Use team's custom SMTP configuration
            $config = $team->getOutgoingEmailConfig();

            Config::set('mail.mailers.smtp.host', $config['host']);
            Config::set('mail.mailers.smtp.port', $config['port']);
            Config::set('mail.mailers.smtp.username', $config['username']);
            Config::set('mail.mailers.smtp.password', $config['password']);
            Config::set('mail.mailers.smtp.encryption', $config['encryption']);
            Config::set('mail.from.address', $config['from_address']);
            Config::set('mail.from.name', $config['from_name']);

            // No advertising footer for teams with custom SMTP
            Config::set('app.mail_advertising_footer', '');
        } else {
            // Use system SMTP configuration with advertising footer
            // The system configuration is already loaded from .env
            // Just ensure we have the advertising footer
            $advertisingFooter = $team->getAdvertisingFooter();
            Config::set('app.mail_advertising_footer', $advertisingFooter);
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
