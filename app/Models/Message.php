<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\MessageStatus;

class Message extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $timestamps = true;

	protected $table = 'messages';

    protected $fillable = ['name', 'type_id', 'category_id', 'template_id', 'text', 'status_id', 'team_id'];

    protected $casts = [
        'status_id' => MessageStatus::class,
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
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function type()
    {
        return $this->belongsTo(MessageType::class);
    }
    
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function templates()
    {
        return $this->belongsTo(Template::class);
    }
}
