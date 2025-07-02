<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
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
        
        try {
            // Generate password reset token and URL
            $token = Password::createToken($user);
            $this->resetUrl = url(route('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ], false));
        } catch (\Exception $e) {
            \Log::error("Failed to generate password reset token for user {$user->id}: " . $e->getMessage());
            // Fallback URL to password reset request page
            $this->resetUrl = url(route('password.request'));
        }
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Bienvenido a ' . ($this->team ? $this->team->name : config('app.name')) . ' - Configura tu contraseña')
            ->view('emails.new-user-notification')
            ->with([
                'user' => $this->user,
                'team' => $this->team,
                'resetUrl' => $this->resetUrl,
            ]);
    }
} 