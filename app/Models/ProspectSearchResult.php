<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectSearchResult extends Model
{
    protected $table = 'person_search_results';

    protected $fillable = [
        'person_search_id',
        'person_id',
        'source',
        'external_id',
    ];

    public function search(): BelongsTo
    {
        return $this->belongsTo(ProspectSearch::class, 'person_search_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
