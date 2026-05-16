<?php

namespace App\Jobs;

use App\Models\Email;
use App\Services\Mail\MailboxSpamClassificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ClassifyEmailSpamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $emailId,
    ) {}

    public function handle(MailboxSpamClassificationService $service): void
    {
        $email = Email::query()->with('team')->find($this->emailId);
        if (! $email)
        {
            return;
        }

        $service->classifyAndApply($email);
    }
}
