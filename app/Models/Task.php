<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Task extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $fillable = [
        'team_id',
        'board_id',
        'category_id',
        'responsible_id',
        'title',
        'description',
        'estimated_hours',
        'start_date',
        'due_date',
        'status_id',
        'order',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'estimated_hours' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
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

    public function board()
    {
        return $this->belongsTo(TaskBoard::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function project()
    {
        return $this->hasOneThrough(
            Project::class,
            TaskBoard::class,
            'id',  // Foreign key on TaskBoard table
            'board_id',  // Foreign key on Project table
            'board_id',  // Local key on Task table
            'id',  // Local key on TaskBoard table
        );
    }

    public function getStatusLabelAttribute()
    {
        if ($this->status)
        {
            return '<span class="badge rounded-pill '.$this->status->label_class.'">'.$this->status->translated_name.'</span>';
        }

        return '<span class="badge rounded-pill bg-label-secondary">'.__('task_status.UNKNOWN').'</span>';
    }

    public function getTranslatedStatusAttribute()
    {
        return $this->status ? $this->status->translated_name : __('task_status.UNKNOWN');
    }

    public function scopePendingForUser($query, $userId)
    {
        return $query
            ->where('responsible_id', $userId)
            ->whereHas('status', function ($q)
            {
                $q->whereNotIn('name', ['DONE']);
            })
            ->orderBy('due_date', 'asc');
    }

    /**
     * Default ordering for tasks
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDefaultOrder($query)
    {
        return $query->orderBy('status_id', 'asc')->orderBy('due_date', 'asc');
    }

    /**
     * Register media collections for this model
     */
    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('attachments')
            ->useDisk('public')
            ->acceptsMimeTypes([
                // Images
                'image/jpeg',
                'image/jpg',
                'image/png',
                'image/gif',
                'image/webp',
                'image/svg+xml',
                'image/bmp',
                'image/tiff',
                // Videos
                'video/mp4',
                'video/avi',
                'video/mov',
                'video/wmv',
                'video/flv',
                'video/webm',
                'video/mkv',
                // Audio
                'audio/mpeg',
                'audio/mp3',
                'audio/wav',
                'audio/ogg',
                'audio/aac',
                'audio/flac',
                // Documents
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                // Archives
                'application/zip',
                'application/x-rar-compressed',
                'application/x-7z-compressed',
                'application/x-tar',
            ]);
    }

    /**
     * Configure activity logging options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'description', 'due_date', 'status_id', 'responsible_id', 'category_id', 'estimated_hours'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
