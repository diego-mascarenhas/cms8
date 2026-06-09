<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Team;
use App\Services\HumanoToWebDavContactPusher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PushContactToWebDavJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $contactId) {}

    public function handle(HumanoToWebDavContactPusher $pusher): void
    {
        $contact = Contact::withTrashed()->find($this->contactId);

        if ($contact === null)
        {
            return;
        }

        $team = Team::query()->find($contact->team_id);

        if ($team === null || ! $team->webdavContactsOutboundSyncEnabled())
        {
            return;
        }

        try
        {
            $pusher->sync($contact);
        } catch (\Throwable $exception)
        {
            Log::warning('PushContactToWebDavJob failed (non-fatal).', [
                'contact_id' => $this->contactId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
