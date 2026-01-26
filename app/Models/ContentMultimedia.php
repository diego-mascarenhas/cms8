<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentMultimedia extends Model
{
    use HasFactory;

    protected $table = 'content_multimedia';

    protected $fillable = [
        'content_id',
        'multimedia_id',
        'language',
        'type',
        'order',
    ];

    protected $casts = [
        'type' => 'integer',
        'order' => 'integer',
    ];

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'content_id');
    }

    public function multimedia(): BelongsTo
    {
        return $this->belongsTo(Multimedia::class, 'multimedia_id');
    }
}
