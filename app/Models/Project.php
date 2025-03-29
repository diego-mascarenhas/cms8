<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Project extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'enterprise_id',
        'category_id',
        'name',
        'description',
        'price',
        'discount',
        'cost',
        'start_date',
        'end_date',
        'responsible_id',
        'status_id',
        'created_at',
        'updated_at'
    ];

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $builder->whereHas('client', function($query) {
                    $query->where('team_id', auth()->user()->currentTeam->id);
                });
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function client()
    {
        return $this->belongsTo(Enterprise::class, 'enterprise_id');
    }

    public function responsible()
	{
		return $this->belongsTo(User::class, 'responsible_id');
	}

    public function status()
	{
		return $this->belongsTo(ProjectStatus::class);
	}

    public function getStatusLabelAttribute()
    {
        if ($this->status)
        {
            return '<span class="badge rounded-pill ' . $this->status->label_class . '">' . $this->status->translated_name . '</span>';
        }
        return '<span class="badge rounded-pill bg-label-secondary">Unknown</span>';
    }

}