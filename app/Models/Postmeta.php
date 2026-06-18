<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WordPress-equivalent `wp_postmeta` row. The `id` column maps to WordPress' `meta_id`.
 */
class Postmeta extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'postmeta';

    protected $fillable = [
        'team_id',
        'post_id',
        'meta_key',
        'meta_value',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }
}
