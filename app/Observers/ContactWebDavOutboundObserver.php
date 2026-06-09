<?php

namespace App\Observers;

use App\Jobs\PushContactToWebDavJob;
use App\Models\Contact;
use App\Models\Team;

class ContactWebDavOutboundObserver
{
    public function saved(Contact $contact): void
    {
        $this->dispatchWhenEnabled($contact);
    }

    public function deleted(Contact $contact): void
    {
        $this->dispatchWhenEnabled($contact);
    }

    public function restored(Contact $contact): void
    {
        $this->dispatchWhenEnabled($contact);
    }

    private function dispatchWhenEnabled(Contact $contact): void
    {
        $team = $contact->relationLoaded('team')
            ? $contact->team
            : Team::query()->find($contact->team_id);

        if ($team === null || ! $team->webdavContactsOutboundSyncEnabled())
        {
            return;
        }

        PushContactToWebDavJob::dispatch($contact->id);
    }
}
