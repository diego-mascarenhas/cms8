<?php

namespace App\Jobs;

use App\Mail\NotificationMail;
use App\Models\Notification;
use App\Traits\ConfiguresTeamMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ConfiguresTeamMail;

    /**
     * The notification instance.
     *
     * @var \App\Models\Notification
     */
    public $notification;

    /**
     * Indicates if this is a resend.
     *
     * @var bool
     */
    public $isResend;

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
    public function __construct(Notification $notification, bool $isResend = false)
    {
        $this->notification = $notification;
        $this->isResend = $isResend;
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $this->notification->load(['contact', 'user', 'team']);

            if (! $this->notification->contact->email) {
                throw new \Exception('El contacto no tiene email configurado');
            }

            // Configure mail for the team (custom SMTP or system with advertising)
            $this->configureMailForTeam($this->notification->team);

            Log::info('Sending notification email', [
                'notification_id' => $this->notification->id,
                'contact_email' => $this->notification->contact->email,
                'is_resend' => $this->isResend,
            ]);

            Mail::to($this->notification->contact->email)
                ->send(new NotificationMail($this->notification));

            $sentData = [
                'email' => $this->notification->contact->email,
                'sent_at' => now()->toISOString(),
                'is_resend' => $this->isResend,
                'queue_processed_at' => now()->toISOString(),
            ];

            $this->notification->markAsSent($sentData);

            Log::info('Notification email sent successfully', [
                'notification_id' => $this->notification->id,
                'contact_email' => $this->notification->contact->email,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send notification email', [
                'notification_id' => $this->notification->id,
                'error' => $e->getMessage(),
                'contact_email' => $this->notification->contact->email ?? 'N/A',
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        Log::error('Notification job failed permanently', [
            'notification_id' => $this->notification->id,
            'error' => $exception->getMessage(),
            'contact_email' => $this->notification->contact->email ?? 'N/A',
        ]);

        // Optionally, you could mark the notification as failed
        // or take other actions like notifying admins
    }
}
