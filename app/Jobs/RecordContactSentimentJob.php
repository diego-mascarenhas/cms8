<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Services\ContactSentimentAnalysisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordContactSentimentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $contactId,
        public string $text,
        public string $channel,
    ) {}

    public function handle(ContactSentimentAnalysisService $service): void
    {
        $contact = Contact::withoutGlobalScopes()->find($this->contactId);

        if (! $contact)
        {
            return;
        }

        $service->recordForContact($contact, $this->text, $this->channel);
    }
}
