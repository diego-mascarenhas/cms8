<?php

namespace App\Models;

use App\Enums\AutomationReplyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationTransition extends Model
{
    protected $fillable = [
        'automation_id',
        'from_step_id',
        'to_step_id',
        'to_automation_id',
        'reply_type',
        'match_value',
        'label',
        'sort_order',
        'drawflow_output',
    ];

    protected $casts = [
        'reply_type' => AutomationReplyType::class,
        'sort_order' => 'integer',
    ];

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function fromStep(): BelongsTo
    {
        return $this->belongsTo(AutomationStep::class, 'from_step_id');
    }

    public function toStep(): BelongsTo
    {
        return $this->belongsTo(AutomationStep::class, 'to_step_id');
    }

    public function toAutomation(): BelongsTo
    {
        return $this->belongsTo(Automation::class, 'to_automation_id');
    }

    public function exitsToAutomation(): bool
    {
        return $this->to_automation_id !== null;
    }
}
