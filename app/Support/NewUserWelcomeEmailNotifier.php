<?php

namespace App\Support;

use App\Jobs\SendNewUserWelcomeEmail;
use App\Models\Team;
use App\Models\User;

class NewUserWelcomeEmailNotifier
{
    /**
     * Queue the standard welcome email with password setup link when the address
     * is a real inbox (synthetic WhatsApp users use @chat.placeholder).
     */
    public static function queue(User $user, ?Team $team = null): void
    {
        if (self::isPlaceholderInboxEmail($user->email))
        {
            return;
        }

        SendNewUserWelcomeEmail::dispatch($user, $team);
    }

    public static function isPlaceholderInboxEmail(?string $email): bool
    {
        if ($email === null || $email === '')
        {
            return true;
        }

        return str_ends_with(strtolower($email), '@chat.placeholder');
    }
}
