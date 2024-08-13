<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Communication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type_id',
        'reference',
        'data',
        'sent',
        'received',
        'link',
        'status'
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function type()
    {
        return $this->belongsTo(CommunicationType::class);
    }

    public function getStatusLabelAttribute()
    {
        switch ($this->status)
        {
            case 1:
                return '<span class="badge rounded-pill bg-label-primary">To Send</span>';
            case 2:
                return '<span class="badge rounded-pill bg-label-info">Sent</span>';
            case 3:
                return '<span class="badge rounded-pill bg-label-success">Received</span>';
            case 4:
                return '<span class="badge rounded-pill bg-label-danger">Error</span>';
            default:
                return '<span class="badge rounded-pill bg-label-secondary">Unknown</span>';
        }
    }

}