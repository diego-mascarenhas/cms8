<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ContactAbsence extends Model
{
    use HasFactory;

    protected $table = 'contact_absences';

    protected $fillable = [
        'contact_id',
        'absence_date',
        'reason',
        'team_id'
    ];

    protected $casts = [
        'absence_date' => 'date',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check())
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });
    }
} 