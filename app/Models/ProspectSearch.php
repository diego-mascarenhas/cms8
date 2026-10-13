<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProspectSearch extends Model
{
    protected $table = 'person_searches';

    protected $fillable = [
        'team_id',
        'user_id',
        'filters',
        'page',
        'per_page',
    ];

    protected $casts = [
        'filters' => 'array',
        'page' => 'integer',
        'per_page' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ProspectSearchResult::class, 'person_search_id');
    }
}
