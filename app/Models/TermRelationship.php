<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * WordPress-equivalent `wp_term_relationships` pivot row. Exposed as a model for
 * import tooling; day-to-day usage goes through the {@see Post::termTaxonomies()} relation.
 */
class TermRelationship extends Model
{
    public $timestamps = false;

    protected $table = 'term_relationships';

    protected $fillable = [
        'team_id',
        'object_id',
        'term_taxonomy_id',
        'term_order',
    ];

    protected $casts = [
        'term_order' => 'integer',
    ];
}
