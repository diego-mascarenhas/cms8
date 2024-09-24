<?php

namespace App\Traits;

use App\Models\UserEnterpriseAction;
use Illuminate\Support\Facades\Auth;

trait TracksEnterpriseActions
{
    protected function startActionTracking($enterpriseId, $action)
    {
        $tracking = UserEnterpriseAction::create([
            'user_id' => Auth::id(),
            'enterprise_id' => $enterpriseId,
            'action' => $action,
            'start_time' => now(),
        ]);

        return $tracking->id;
    }

    protected function endActionTracking($trackingId)
    {
        $tracking = UserEnterpriseAction::findOrFail($trackingId);
        $tracking->end_time = now();
        $tracking->duration_seconds = $tracking->end_time->diffInSeconds($tracking->start_time);
        $tracking->save();

        return $tracking;
    }
}