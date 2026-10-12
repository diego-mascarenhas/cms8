<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Services\ApolloService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ApplyApolloPhoneRevealJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 20;

    public function __construct(
        public int $contactId,
        public string $requestId,
    ) {
        $this->onQueue('default');
    }

    public function handle(ApolloService $apollo): void
    {
        $result = $apollo->pollPhoneReveal($this->requestId);
        if ($result['status'] === 'pending')
        {
            $this->release($result['retry_after']);

            return;
        }

        if ($result['status'] !== 'ready' || $result['phone'] === null)
        {
            return;
        }

        $digits = preg_replace('/\D/', '', $result['phone']);
        if (! is_string($digits) || $digits === '')
        {
            return;
        }

        $contact = Contact::withoutGlobalScopes()->find($this->contactId);
        if ($contact === null || $contact->phone)
        {
            return;
        }

        $contact->phone = (int) $digits;
        $contact->save();
    }
}
