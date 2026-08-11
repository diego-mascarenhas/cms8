<?php

namespace App\Jobs;

use App\Helpers\Helpers;
use App\Models\TaskCommunication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTaskCommunication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $communication;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(TaskCommunication $communication)
    {
        $this->communication = $communication;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try
        {
            $task = $this->communication->task()->with(['project.enterprise', 'responsible'])->first();

            if (! $task)
            {
                Log::error('Task not found for communication', ['communication_id' => $this->communication->id]);

                return;
            }

            $recipients = $this->communication->recipients;
            $this->communication->load('user');

            $logoUrl = url(Helpers::logoAsset('light'));
            $appName = config('app.name');

            // Send email to responsible when selected
            if (in_array('responsible', $recipients) && $task->responsible && $task->responsible->email)
            {
                $taskUrl = route('task.index', [
                    'view' => 'kanban',
                    'project_id' => $task->project?->id,
                ]);
                $senderName = $this->communication->user ? $this->communication->user->name : config('app.name');

                Mail::send('emails.task-communication-responsible', [
                    'task' => $task,
                    'body' => $this->communication->message,
                    'taskUrl' => $taskUrl,
                    'senderName' => $senderName,
                    'logoUrl' => $logoUrl,
                    'appName' => $appName,
                ], function ($mail) use ($task)
                {
                    $mail
                        ->to($task->responsible->email)
                        ->subject($this->communication->subject.' - '.$task->title);
                });

                Log::info('Task communication email sent to responsible', [
                    'communication_id' => $this->communication->id,
                    'task_id' => $task->id,
                    'responsible_email' => $task->responsible->email,
                ]);
            }

            // Send email to client if selected
            if (in_array('client', $recipients))
            {
                if ($task->project && $task->project->enterprise && $task->project->enterprise->email)
                {
                    $responseUrl = route('task.communication.respond', ['token' => $this->communication->response_token]);

                    Mail::send('emails.task-communication', [
                        'task' => $task,
                        'body' => $this->communication->message,
                        'responseUrl' => $responseUrl,
                        'enterprise' => $task->project->enterprise,
                        'logoUrl' => $logoUrl,
                        'appName' => $appName,
                    ], function ($mail) use ($task)
                    {
                        $mail
                            ->to($task->project->enterprise->email)
                            ->subject($this->communication->subject.' - Tarea: '.$task->title);
                    });

                    Log::info('Task communication email sent to client', [
                        'communication_id' => $this->communication->id,
                        'task_id' => $task->id,
                        'client_email' => $task->project->enterprise->email,
                    ]);
                } else
                {
                    Log::warning('Client email not available for task communication', [
                        'communication_id' => $this->communication->id,
                        'task_id' => $task->id,
                    ]);
                }
            }
        } catch (\Exception $e)
        {
            Log::error('Error sending task communication in job', [
                'communication_id' => $this->communication->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;  // Re-throw to trigger retry logic
        }
    }
}
