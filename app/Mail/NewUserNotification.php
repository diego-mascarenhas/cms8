<?php

namespace App\Mail;

use App\Helpers\Helpers;
use App\Models\Team;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class NewUserNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    public $team;

    public $resetUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, $team = null)
    {
        $this->user = $user;
        $this->team = $team;

        try
        {
            // Generate password reset token and URL
            $token = Password::createToken($user);
            $this->resetUrl = url(route('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ], false));
        } catch (\Throwable $e)
        {
            Log::error("Failed to generate password reset token for user {$user->id}: ".$e->getMessage());

            throw $e;
        }
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $appName = (string) config('app.name');
        $brand = $this->resolvePublicBrandLabel();
        $displayName = trim((string) $this->user->name);
        if ($displayName === '')
        {
            $displayName = (string) $this->user->email;
        }
        $showBrandLine = $brand !== $appName;
        $logoUrl = url(Helpers::logoAsset('light'));

        return $this->subject('¡Hola, '.$displayName.'! · Activa tu acceso')
            ->view('emails.new-user-notification')
            ->with([
                'user' => $this->user,
                'team' => $this->team,
                'resetUrl' => $this->resetUrl,
                'brand' => $brand,
                'displayName' => $displayName,
                'showBrandLine' => $showBrandLine,
                'appName' => $appName,
                'logoUrl' => $logoUrl,
            ]);
    }

    /**
     * Public-facing brand line: hide internal demo workspaces (names starting with "Demo" + dash, or exactly "Demo").
     */
    private function resolvePublicBrandLabel(): string
    {
        $appName = (string) config('app.name');
        if (! $this->team instanceof Team)
        {
            return $appName;
        }

        $rawTeamName = trim((string) $this->team->name);
        if ($rawTeamName === '')
        {
            return $appName;
        }

        if ($this->teamNameShouldUseAppBrandInstead($rawTeamName))
        {
            return $appName;
        }

        return $rawTeamName;
    }

    /**
     * True for names like "Demo", "Demo — ACME", "Demo – ACME", "Demo-Team" (internal demo), not "Democracia".
     */
    private function teamNameShouldUseAppBrandInstead(string $rawTeamName): bool
    {
        if (strcasecmp($rawTeamName, 'demo') === 0)
        {
            return true;
        }

        return (bool) preg_match('/^demo(?=\s|\p{Pd}|$)/iu', $rawTeamName);
    }
}
