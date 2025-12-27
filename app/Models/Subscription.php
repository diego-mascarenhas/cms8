<?php

namespace App\Models;

use Laravel\Cashier\Subscription as CashierSubscription;

class Subscription extends CashierSubscription
{
    /**
     * Get the model related to the subscription (the owner).
     * Override to ALWAYS use Team instead of User.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        // ALWAYS use Team since stripe_id is stored there
        return $this->belongsTo(Team::class, 'team_id')->withDefault();
    }

    /**
     * Get the team that owns the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * Get the user associated with the subscription (for reference only).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
