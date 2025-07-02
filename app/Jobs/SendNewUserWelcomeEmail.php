<?php

namespace App\Jobs;

use App\Mail\NewUserNotification;
use App\Models\User;
use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNewUserWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $team;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job should run.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, Team $team = null)
    {
        $this->user = $user;
        $this->team = $team;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            // Verify user still exists
            if (!$this->user) {
                Log::warning("User not found when trying to send welcome email");
                return;
            }

            // Verify email is valid
            if (!filter_var($this->user->email, FILTER_VALIDATE_EMAIL)) {
                Log::error("Invalid email address for user {$this->user->id}: {$this->user->email}");
                return;
            }

            // Refresh user from database to ensure it still exists
            $user = \App\Models\User::find($this->user->id);
            if (!$user) {
                Log::warning("User {$this->user->id} no longer exists in database");
                return;
            }

            // Refresh team from database if provided
            $team = null;
            if ($this->team) {
                $team = \App\Models\Team::find($this->team->id);
                if (!$team) {
                    Log::warning("Team {$this->team->id} no longer exists, sending email without team info");
                }
            }

            Log::info("Sending welcome email to user: {$user->email}");

            Mail::to($user->email)->send(new NewUserNotification($user, $team));

            Log::info("Welcome email sent successfully to: {$user->email}");

        } catch (\Exception $e) {
            Log::error("Failed to send welcome email to user {$this->user->id}: " . $e->getMessage());
            Log::error("Exception details: " . $e->getTraceAsString());
            
            // Re-throw the exception to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        Log::error("Welcome email job failed permanently for user {$this->user->email}: " . $exception->getMessage());
        
        // Optionally, you could notify administrators about the failure
        // or store the failure in a dedicated table for manual retry
    }
} 