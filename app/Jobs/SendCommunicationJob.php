<?php

namespace App\Jobs;

use App\Exceptions\WhatsAppSessionWindowClosedException;
use App\Models\Communication;
use App\Services\Communications\CommunicationSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendCommunicationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 120, 300];

    public function __construct(public int $communicationId)
    {
        $this->onQueue('communications');
    }

    public function handle(CommunicationSender $sender): void
    {
        $communication = Communication::withoutGlobalScopes()
            ->with('team')
            ->find($this->communicationId);

        if (! $communication)
        {
            Log::warning('SendCommunicationJob: communication not found', [
                'communication_id' => $this->communicationId,
            ]);

            return;
        }

        try
        {
            $sender->send($communication);
            $communication->markSent();
        } catch (WhatsAppSessionWindowClosedException $exception)
        {
            $communication->markFailed($exception->getMessage());
        } catch (\Throwable $exception)
        {
            $communication->forceFill([
                'error_message' => $exception->getMessage(),
                'metadata' => $communication->withEvent('attempt_failed', $exception->getMessage()),
            ])->save();

            throw $exception;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $communication = Communication::withoutGlobalScopes()->find($this->communicationId);
        if (! $communication)
        {
            return;
        }

        $communication->markFailed($exception->getMessage());
    }
}
