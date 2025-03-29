<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnterpriseOrganization extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'department_id',
        'name',
        'description',
        'responsible_id',
        'time_allocation',
        'availability',
        'order'
    ];

    public function department()
    {
        return $this->belongsTo(EnterpriseDepartment::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function responsible()
    {
        return $this->belongsTo(Contact::class, 'responsible_id');
    }
}