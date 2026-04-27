<?php

namespace App\Observers;

use App\Jobs\PushContactToGoogleJob;
use App\Models\Contact;

class ContactGoogleOutboundObserver
{
    public function saved(Contact $contact): void
    {
        PushContactToGoogleJob::dispatch($contact->id);
    }

    public function deleted(Contact $contact): void
    {
        PushContactToGoogleJob::dispatch($contact->id);
    }

    public function restored(Contact $contact): void
    {
        PushContactToGoogleJob::dispatch($contact->id);
    }
}
