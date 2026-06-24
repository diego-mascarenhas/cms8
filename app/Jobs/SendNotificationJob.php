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
    use ConfiguresTeamMail, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        try
        {
            $notification = $this->notification->fresh(['contact', 'user', 'team']);

            if (! $notification)
            {
                throw new \Exception('Notificación no encontrada');
            }

            if (! $notification->contact->email)
            {
                throw new \Exception('El contacto no tiene email configurado');
            }

            $this->configureMailForTeam($notification->team);

            Log::info('Sending notification email', [
                'notification_id' => $notification->id,
                'contact_email' => $notification->contact->email,
                'is_resend' => $this->isResend,
                'plain_text' => $notification->isPlainTextFormat(),
            ]);

            if ($notification->isPlainTextFormat())
            {
                $this->sendPlainTextNotification($notification);
            } else
            {
                Mail::to($notification->contact->email)
                    ->send(new NotificationMail($notification));
            }

            $sentData = [
                'email' => $notification->contact->email,
                'sent_at' => now()->toISOString(),
                'is_resend' => $this->isResend,
                'queue_processed_at' => now()->toISOString(),
            ];

            $notification->markAsSent($sentData);

            Log::info('Notification email sent successfully', [
                'notification_id' => $notification->id,
                'contact_email' => $notification->contact->email,
            ]);
        } catch (\Exception $e)
        {
            Log::error('Failed to send notification email', [
                'notification_id' => $this->notification->id,
                'error' => $e->getMessage(),
                'contact_email' => $this->notification->contact->email ?? 'N/A',
            ]);

            throw $e;
        }
    }

    private function sendPlainTextNotification(Notification $notification): void
    {
        Mail::raw(
            $notification->message,
            function ($message) use ($notification): void
            {
                $message->to($notification->contact->email)
                    ->subject($notification->subject);
            },
        );
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
