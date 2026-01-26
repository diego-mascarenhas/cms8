<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentFieldConfig extends Model
{
    use HasFactory;

    protected $table = 'content_field_configs';

    protected $fillable = [
        'team_id',
        'section_category_id',
        'field_key',
        'field_type',
        'field_label',
        'field_options',
        'is_active',
        'order',
        'required',
    ];

    protected $casts = [
        'field_options' => 'array',
        'is_active' => 'boolean',
        'required' => 'boolean',
        'order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check())
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function sectionCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'section_category_id');
    }

    /**
     * Scope to get only active field configs
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by order field
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }
}
