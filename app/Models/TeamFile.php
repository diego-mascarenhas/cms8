<?php

namespace App\Models;

use App\Enums\MultimediaVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TeamFile extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $table = 'team_files';

    protected $fillable = [
        'team_id',
        'title',
        'description',
        'visibility',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'visibility' => MultimediaVisibility::class,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check())
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });

        static::creating(function (self $teamFile)
        {
            if (auth()->check())
            {
                $teamFile->team_id = $teamFile->team_id ?? auth()->user()->currentTeam->id;
                $teamFile->created_by = $teamFile->created_by ?? auth()->id();
                $teamFile->updated_by = $teamFile->updated_by ?? auth()->id();
            }
        });

        static::updating(function (self $teamFile)
        {
            if (auth()->check())
            {
                $teamFile->updated_by = auth()->id();
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('file')
            ->singleFile()
            ->useDisk('public')
            ->acceptsMimeTypes([
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'image/svg+xml',
            ]);
    }
}
