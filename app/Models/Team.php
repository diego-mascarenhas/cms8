<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;

class Team extends JetstreamTeam
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'personal_team' => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'personal_team',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    public function settings()
    {
        return $this->hasMany(TeamSetting::class);
    }

    public function getSetting($key, $default = null)
    {
        return $this->settings()->where('key', $key)->first()?->value ?? $default;
    }

    public function setSetting($key, $value, $options = [])
    {
        $defaultOptions = [
            'type' => 'string',
            'group' => 'general',
            'is_encrypted' => false,
        ];

        $options = array_merge($defaultOptions, $options);

        $setting = $this->settings()->firstOrNew(['key' => $key]);

        $setting->fill([
            'type' => $options['type'],
            'group' => $options['group'],
            'is_encrypted' => $options['is_encrypted'],
        ]);

        if (! $setting->exists) {
            $setting->save();
        }

        $setting->value = $value;

        return $setting->save();
    }

    /**
     * Get the modules enabled for this team.
     */
    public function modules()
    {
        return $this->belongsToMany(Module::class)
            ->withPivot('settings', 'status')
            ->withTimestamps();
    }

    /**
     * Check if a specific module is active for this team.
     */
    public function hasModule($moduleKey)
    {
        return $this->modules()
            ->where('key', $moduleKey)
            ->where('module_team.status', 1)
            ->exists();
    }

    /**
     * Enable a module for this team.
     */
    public function enableModule($moduleKey, $settings = null)
    {
        $module = Module::where('key', $moduleKey)->first();

        if (! $module) {
            return false;
        }

        $existingPivot = $this->modules()
            ->where('modules.id', $module->id)
            ->first();

        if ($existingPivot) {
            $this->modules()->updateExistingPivot($module->id, [
                'status' => 1,
                'settings' => $settings ? json_encode($settings) : $existingPivot->pivot->settings,
            ]);
        } else {
            $this->modules()->attach($module->id, [
                'status' => 1,
                'settings' => $settings ? json_encode($settings) : null,
            ]);
        }

        return true;
    }

    /**
     * Disable a module for this team.
     */
    public function disableModule($moduleKey)
    {
        $module = Module::where('key', $moduleKey)->first();

        if (! $module) {
            return false;
        }

        $this->modules()->updateExistingPivot($module->id, [
            'status' => 0,
        ]);

        return true;
    }
}
