<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id',
        'responsible_id',
        'title',
        'description',
        'start_date',
        'due_date',
        'order',
        'status_id'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'due_date' => 'datetime',
    ];

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder) {
            if (auth()->check() && auth()->user()->currentTeam) {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function status()
    {
        return $this->belongsTo(TaskStatus::class);
    }

    public function getStatusLabelAttribute()
    {
        if ($this->status) {
            return '<span class="badge rounded-pill ' . $this->status->label_class . '">' . $this->status->name . '</span>';
        }
        return '<span class="badge rounded-pill bg-label-secondary">Unknown</span>';
    }

    public function scopePendingForUser($query, $userId)
    {
        return $query->where('responsible_id', $userId)
            ->whereHas('status', function($q) {
                $q->whereNotIn('name', ['DONE']);
            })
            ->orderBy('due_date', 'asc');
    }
} 