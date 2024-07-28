<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'enterprise_id',
        'type_id',
        'leader_id',
        'name',
        'description',
        'price',
        'discount',
        'cost',
        'start_date',
        'end_date',
        'responsible',
        'status',
        'created_at',
        'updated_at'
    ];

    public function type()
    {
        return $this->belongsTo(ProjectType::class);
    }

    public function client()
    {
        return $this->belongsTo(Enterprise::class, 'enterprise_id');
    }

    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function getStatusLabelAttribute()
    {
        switch ($this->status)
        {
            case 1:
                return '<span class="badge rounded-pill bg-label-primary">Budget</span>';
            case 2:
                return '<span class="badge rounded-pill bg-label-warning">Budgeted</span>';
            case 3:
                return '<span class="badge rounded-pill bg-label-success">Authorized</span>';
            case 4:
                return '<span class="badge rounded-pill bg-label-info">Sent</span>';
            case 5:
                return '<span class="badge rounded-pill bg-label-info">Received</span>';
            case 7:
                return '<span class="badge rounded-pill bg-label-success">Approved</span>';
            case 8:
                return '<span class="badge rounded-pill bg-label-warning">Waiting for response</span>';
            case 9:
                return '<span class="badge rounded-pill bg-label-primary">In progress</span>';
            case 10:
                return '<span class="badge rounded-pill bg-label-success">Finished</span>';
            case 11:
                return '<span class="badge rounded-pill bg-label-warning">To invoice</span>';
            case 12:
                return '<span class="badge rounded-pill bg-label-success">Invoiced</span>';
            case 13:
                return '<span class="badge rounded-pill bg-label-danger">Not approved</span>';
            default:
                return '<span class="badge rounded-pill bg-label-secondary">Unknown</span>';
        }
    }

}