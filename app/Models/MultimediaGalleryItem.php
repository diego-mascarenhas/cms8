<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Tags\Tag;

class MultimediaGalleryItem extends Model
{
    protected $table = 'multimedia_gallery_items';

    protected $fillable = [
        'multimedia_id',
        'tag_id',
        'order',
    ];

    public function multimedia(): BelongsTo
    {
        return $this->belongsTo(Multimedia::class, 'multimedia_id');
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'tag_id');
    }
}
