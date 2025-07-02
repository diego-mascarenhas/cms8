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
            Log::info("Sending welcome email to user: {$this->user->email}");

            Mail::to($this->user->email)->send(new NewUserNotification($this->user, $this->team));

            Log::info("Welcome email sent successfully to: {$this->user->email}");

        } catch (\Exception $e) {
            Log::error("Failed to send welcome email to {$this->user->email}: " . $e->getMessage());
            
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