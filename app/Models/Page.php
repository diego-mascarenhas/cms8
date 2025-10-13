<?php

namespace App\Models;

use App\Enums\PageStatus;
use Dotlogics\Grapesjs\App\Contracts\Editable;
use Dotlogics\Grapesjs\App\Traits\EditableTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model implements Editable
{
    use EditableTrait;
    use HasFactory;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'pages';

    protected $fillable = ['name', 'gjs_data', 'status_id', 'team_id'];

    protected $casts = [
        'gjs_data' => 'array',
        'status_id' => PageStatus::class,
    ];

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check())
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });

        static::creating(function ($model)
        {
            if (! $model->team_id && auth()->check())
            {
                $model->team_id = auth()->user()->currentTeam->id;
            }
        });
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
