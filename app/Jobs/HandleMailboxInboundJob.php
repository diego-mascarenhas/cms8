<?php

namespace App\Jobs;

use App\Models\Email;
use App\Services\Mail\MailboxInboundActionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class HandleMailboxInboundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $emailId,
    ) {}

    public function handle(MailboxInboundActionService $service): void
    {
        $email = Email::query()->with('mailbox')->find($this->emailId);
        if ($email === null)
        {
            return;
        }

        $service->handle($email);
    }
}
