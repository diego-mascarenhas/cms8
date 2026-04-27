<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use HasRoles;
    use HasTeams;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     *            Note: currentTeam is not included here as it's an accessor from Jetstream,
     *            not a direct relationship. It should be loaded explicitly where needed.
     *            Note: Removed global eager loading of roles and teams to prevent conflicts
     *            with explicit eager loading in DataTables and other queries.
     */
    // protected $with = ['roles', 'teams'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name', 'phone', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone' => 'integer',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Size (width and height) used when cropping profile photos to square.
     */
    protected static int $profilePhotoCropSize = 512;

    /**
     * Update the user's profile photo. Crops to a square (center) before storing
     * so the image is never deformed in the UI.
     */
    public function updateProfilePhoto(UploadedFile $photo, $storagePath = 'profile-photos'): void
    {
        $croppedPath = $this->cropPhotoToSquare($photo);

        $fileToStore = $croppedPath
            ? new UploadedFile($croppedPath, $photo->getClientOriginalName(), $photo->getClientMimeType(), 0, true)
            : $photo;

        tap($this->profile_photo_path, function ($previous) use ($fileToStore, $storagePath)
        {
            $this->forceFill([
                'profile_photo_path' => $fileToStore->storePublicly(
                    $storagePath,
                    ['disk' => $this->profilePhotoDisk()],
                ),
            ])->save();

            if ($previous)
            {
                Storage::disk($this->profilePhotoDisk())->delete($previous);
            }
        });

        if ($croppedPath && file_exists($croppedPath))
        {
            @unlink($croppedPath);
        }
    }

    /**
     * Crop the uploaded photo to a square (center crop). Returns path to temp file or null on failure.
     *
     * @return string|null Path to temporary cropped file, or null if crop failed
     */
    protected function cropPhotoToSquare(UploadedFile $photo): ?string
    {
        try
        {
            $size = static::$profilePhotoCropSize;

            $tempPath = $photo->getRealPath();
            $ext = $photo->getClientOriginalExtension() ?: 'jpg';
            $tempCropped = sys_get_temp_dir().'/profile_photo_'.uniqid().'.'.strtolower($ext);

            Image::load($tempPath)
                ->fit(Fit::Crop, $size, $size)
                ->save($tempCropped);

            return $tempCropped;
        } catch (\Throwable)
        {
            return null;
        }
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_user', 'user_id', 'category_id');
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function clients()
    {
        return $this->hasMany(Enterprise::class, 'assigned_to', 'id');
    }

    public function externalAccounts(): HasMany
    {
        return $this->hasMany(ExternalAccount::class);
    }

    /**
     * Get the conversations associated with this user's phone number
     */
    public function conversations()
    {
        return $this->hasMany(Conversation::class, 'from', 'phone');
    }

    public function projects()
    {
        return $this->hasMany(Project::class, 'responsible_id');
    }

    /**
     * Get associated contact record
     */
    public function contact()
    {
        return $this->hasOne(Contact::class);
    }

    /**
     * Configure activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
