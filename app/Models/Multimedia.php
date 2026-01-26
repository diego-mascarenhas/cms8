<?php

namespace App\Models;

use App\Enums\MultimediaStatus;
use App\Enums\MultimediaVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Tags\HasTags;

class Multimedia extends Model implements HasMedia
{
    use HasFactory;
    use HasTags;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $table = 'multimedia';

    protected $fillable = [
        'team_id',
        'category_id',
        'title',
        'description',
        'status',
        'visibility',
        'type',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => MultimediaStatus::class,
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

        static::creating(function (self $multimedia)
        {
            if (auth()->check())
            {
                $multimedia->team_id = $multimedia->team_id ?? auth()->user()->currentTeam->id;
                $multimedia->created_by = $multimedia->created_by ?? auth()->id();
                $multimedia->updated_by = $multimedia->updated_by ?? auth()->id();
            }
        });

        static::updating(function (self $multimedia)
        {
            if (auth()->check())
            {
                $multimedia->updated_by = auth()->id();
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function galleryItems(): HasMany
    {
        return $this->hasMany(MultimediaGalleryItem::class, 'multimedia_id');
    }

    public function galleryTags(): Collection
    {
        return $this->tagsWithType('gallery');
    }

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('media')
            ->singleFile()
            ->useDisk('public');

        $this
            ->addMediaCollection('poster')
            ->singleFile()
            ->useDisk('public')
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/jpg',
                'image/png',
                'image/gif',
                'image/webp',
            ]);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $categoryData = $this->category ? (array) $this->category->data : [];
        $fit = $this->resolveFit($categoryData['fit'] ?? null);

        $thumbWidth = (int) ($categoryData['thumb_width'] ?? 320);
        $thumbHeight = (int) ($categoryData['thumb_height'] ?? 320);
        $imageWidth = $categoryData['image_width'] ?? null;
        $imageHeight = $categoryData['image_height'] ?? null;

        $thumbConversion = $this
            ->addMediaConversion('thumb')
            ->fit($fit, $thumbWidth, $thumbHeight)
            ->performOnCollections('media');

        if ($media && $media->mime_type && str_starts_with($media->mime_type, 'video/'))
        {
            $thumbConversion->extractVideoFrameAtSecond(1);
        }

        if (($imageWidth || $imageHeight) && $media && $media->mime_type && str_starts_with($media->mime_type, 'image/'))
        {
            $conversion = $this
                ->addMediaConversion('main')
                ->performOnCollections('media');

            if ($imageWidth && $imageHeight)
            {
                $conversion->fit($fit, (int) $imageWidth, (int) $imageHeight);
            } elseif ($imageWidth)
            {
                $conversion->width((int) $imageWidth);
            } else
            {
                $conversion->height((int) $imageHeight);
            }
        }

        if ($media && $media->mime_type && str_starts_with($media->mime_type, 'video/'))
        {
            $posterWidth = (int) ($categoryData['poster_width'] ?? $thumbWidth);
            $posterHeight = (int) ($categoryData['poster_height'] ?? $thumbHeight);

            $this
                ->addMediaConversion('poster')
                ->extractVideoFrameAtSecond(1)
                ->fit($fit, $posterWidth, $posterHeight)
                ->performOnCollections('media');
        }
    }

    private function resolveFit(?string $fit): Fit
    {
        return match ($fit)
        {
            'contain' => Fit::Contain,
            'max' => Fit::Max,
            'stretch' => Fit::Stretch,
            default => Fit::Crop,
        };
    }
}
