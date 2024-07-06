<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'services';

    protected $fillable = ['client_id', 'type_id', 'description', 'data', 'status'];

    protected $casts = [
        'data' => 'object',
    ];

    // public function type()
    // {
    //     return $this->belongsTo(InsuranceType::class, 'type_id');
    // }

    public function getStatusLabelAttribute()
    {
        switch ($this->status) {
            case 1:
                return '<span class="badge rounded-pill bg-label-danger">Suspended</span>';
            case 2:
                return '<span class="badge rounded-pill bg-label-warning">To suspend</span>';
            case 3:
                return '<span class="badge rounded-pill bg-label-success">To activate</span>';
            case 4:
                return '<span class="badge rounded-pill bg-label-info">Active</span>';
            default:
                return '<span class="badge rounded-pill bg-label-secondary">unknown</span>';
        }
    }

    public function type()
    {
        return $this->belongsTo(ServiceType::class);
    }
    
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }
}
