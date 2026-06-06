<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Team;
use App\Services\HumanoToGoogleContactPusher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PushContactToGoogleJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $contactId) {}

    public function handle(HumanoToGoogleContactPusher $pusher): void
    {
        if ((string) config('services.google.client_id') === '')
        {
            return;
        }

        $contact = Contact::withTrashed()->find($this->contactId);

        if ($contact === null)
        {
            return;
        }

        $team = Team::query()->find($contact->team_id);

        if ($team === null || ! $team->googleContactsOutboundSyncEnabled())
        {
            return;
        }

        try
        {
            $pusher->sync($contact);
        } catch (\Throwable $e)
        {
            Log::warning('PushContactToGoogleJob failed (non-fatal).', [
                'contact_id' => $this->contactId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
