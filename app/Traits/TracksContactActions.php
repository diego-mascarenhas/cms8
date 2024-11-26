<?php

namespace App\Traits;

use App\Models\UserContactAction;
use Illuminate\Support\Facades\Auth;

trait TracksContactActions
{
    protected function startActionTracking($contactId, $action)
    {
        $tracking = UserContactAction::create([
            'user_id' => Auth::id(),
            'contact_id' => $contactId,
            'action' => $action,
            'start_time' => now(),
        ]);

        return $tracking->id;
    }

    protected function endActionTracking($trackingId)
    {
        $tracking = UserContactAction::findOrFail($trackingId);
        $tracking->end_time = now();
        $tracking->duration_seconds = $tracking->end_time->diffInSeconds($tracking->start_time);
        $tracking->save();

        return $tracking;
    }
}