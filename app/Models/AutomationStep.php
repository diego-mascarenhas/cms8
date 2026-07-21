<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationStep extends Model
{
    /** @use HasFactory<\Database\Factories\AutomationStepFactory> */
    use HasFactory;

    protected $fillable = [
        'automation_id',
        'key',
        'label',
        'prompt_key',
        'instruction',
        'is_entry',
        'position_x',
        'position_y',
        'settings',
    ];

    protected $casts = [
        'is_entry' => 'boolean',
        'position_x' => 'integer',
        'position_y' => 'integer',
        'settings' => 'array',
    ];

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(AutomationTransition::class, 'from_step_id')->orderBy('sort_order');
    }

    public function resolvedPromptKey(): ?string
    {
        $key = is_string($this->prompt_key) ? trim($this->prompt_key) : '';

        return $key !== '' ? $key : null;
    }
}
