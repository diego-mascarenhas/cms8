<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactProject extends Pivot
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'contact_project';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'contact_id',
        'project_id',
        'message_sent',
        'status',
        'sent_at',
        'viewed_at',
        'responded_at',
        'response_message',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'responded_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the contact that owns the pivot.
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the project that owns the pivot.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
