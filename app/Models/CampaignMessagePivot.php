<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CampaignMessagePivot extends Pivot
{
    protected $table = 'campaign_message';

    public $incrementing = true;

    protected $casts = [
        'conditions' => 'array',
    ];
}
