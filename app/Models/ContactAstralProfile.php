<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactAstralProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'birth_date',
        'birth_time',
        'birth_city',
        'birth_latitude',
        'birth_longitude',
        'birth_timezone',
        'zodiac_sign',
        'zodiac_symbol',
        'zodiac_element',
        'north_node_sign',
        'ascendant_sign',
        'human_design_data',
        'interpretation',
        'is_complete',
        'generated_at',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'birth_latitude' => 'decimal:7',
        'birth_longitude' => 'decimal:7',
        'human_design_data' => 'array',
        'is_complete' => 'boolean',
        'generated_at' => 'datetime',
    ];

    /**
     * Get the contact that owns the astral profile
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Check if profile has complete birth data for accurate calculations
     */
    public function hasCompleteData(): bool
    {
        return ! empty($this->birth_time) && ! empty($this->birth_city);
    }

    /**
     * Mark profile as complete or incomplete based on available data
     */
    public function updateCompletenessStatus(): void
    {
        $this->is_complete = $this->hasCompleteData();
        $this->save();
    }
}
