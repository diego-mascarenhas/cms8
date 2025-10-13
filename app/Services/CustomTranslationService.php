<?php

namespace App\Services;

use App\Models\CustomTranslation;
use Illuminate\Support\Facades\Cache;

class CustomTranslationService
{
    /**
     * Get a custom translation for the current team and locale
     */
    public function get($key, $group = 'app', $locale = null)
    {
        // Skip during bootstrap or when database is not available
        if ($this->shouldSkipDatabaseQuery())
        {
            return null;
        }

        $locale = $locale ?: app()->getLocale();

        // Get team ID
        $teamId = $this->getTeamId();
        if (! $teamId)
        {
            return null;
        }

        // Try to get from cache first
        $cacheKey = "custom_translation_{$teamId}_{$key}_{$group}_{$locale}";

        try
        {
            $cached = Cache::get($cacheKey);
            if ($cached !== null)
            {
                return $cached;
            }

            // Get from database
            $translation = CustomTranslation::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->where('key', $key)
                ->where('group', $group)
                ->where('locale', $locale)
                ->first();

            if (! $translation)
            {
                return null;
            }

            // Get the value (now it's a simple string)
            $value = $translation->value;

            // Cache the result
            Cache::put($cacheKey, $value, now()->addHours(24));

            return $value;
        } catch (\Exception $e)
        {
            // Return null if database query fails
            return null;
        }
    }

    /**
     * Set a custom translation for the current team
     */
    public function set($key, $value, $group = 'app', $locale = null)
    {
        // Skip during bootstrap or when database is not available
        if ($this->shouldSkipDatabaseQuery())
        {
            return null;
        }

        $locale = $locale ?: app()->getLocale();

        // Get team ID
        $teamId = $this->getTeamId();
        if (! $teamId)
        {
            return null;
        }

        try
        {
            // Create or update the translation
            $translation = CustomTranslation::withoutGlobalScope('team')->updateOrCreate(
                [
                    'team_id' => $teamId,
                    'key' => $key,
                    'group' => $group,
                    'locale' => $locale,
                ],
                [
                    'value' => $value, // Now it's a simple string
                ],
            );

            // Clear cache
            $this->clearCache($key, $group, $locale);

            return $translation;
        } catch (\Exception $e)
        {
            return null;
        }
    }

    /**
     * Remove a custom translation
     */
    public function remove($key, $group = 'app', $locale = null)
    {
        // Skip during bootstrap or when database is not available
        if ($this->shouldSkipDatabaseQuery())
        {
            return false;
        }

        $locale = $locale ?: app()->getLocale();

        // Get team ID
        $teamId = $this->getTeamId();
        if (! $teamId)
        {
            return false;
        }

        try
        {
            // Clear cache
            $cacheKey = "custom_translation_{$teamId}_{$key}_{$group}_{$locale}";
            Cache::forget($cacheKey);

            return CustomTranslation::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->where('key', $key)
                ->where('group', $group)
                ->where('locale', $locale)
                ->delete();
        } catch (\Exception $e)
        {
            return false;
        }
    }

    /**
     * Get all custom translations for a team
     */
    public function getAll($group = null, $locale = null)
    {
        // Skip during bootstrap or when database is not available
        if ($this->shouldSkipDatabaseQuery())
        {
            return collect();
        }

        // Get team ID
        $teamId = $this->getTeamId();
        if (! $teamId)
        {
            return collect();
        }

        try
        {
            $query = CustomTranslation::withoutGlobalScope('team')->where('team_id', $teamId);

            if ($group)
            {
                $query->where('group', $group);
            }

            if ($locale)
            {
                $query->where('locale', $locale);
            }

            return $query->get();
        } catch (\Exception $e)
        {
            return collect();
        }
    }

    /**
     * Clear cache for specific translation
     */
    public function clearCache($key = null, $group = null, $locale = null)
    {
        // Skip during bootstrap or when database is not available
        if ($this->shouldSkipDatabaseQuery())
        {
            return false;
        }

        // Get team ID
        $teamId = $this->getTeamId();
        if (! $teamId)
        {
            return false;
        }

        try
        {
            if ($key && $group && $locale)
            {
                // Clear specific cache
                $cacheKey = "custom_translation_{$teamId}_{$key}_{$group}_{$locale}";
                Cache::forget($cacheKey);
            } else
            {
                // Clear all cache for team
                $translations = CustomTranslation::withoutGlobalScope('team')->where('team_id', $teamId)->get();

                foreach ($translations as $translation)
                {
                    $cacheKey = "custom_translation_{$teamId}_{$translation->key}_{$translation->group}_{$translation->locale}";
                    Cache::forget($cacheKey);
                }
            }

            return true;
        } catch (\Exception $e)
        {
            return false;
        }
    }

    /**
     * Determine if database queries should be skipped
     */
    protected function shouldSkipDatabaseQuery()
    {
        // Skip during console commands
        if (app()->runningInConsole())
        {
            return true;
        }

        // Skip if not bootstrapped
        if (! app()->hasBeenBootstrapped())
        {
            return true;
        }

        // Skip if schema doesn't exist (during migrations)
        try
        {
            if (! \Illuminate\Support\Facades\Schema::hasTable('custom_translations'))
            {
                return true;
            }
        } catch (\Exception $e)
        {
            return true;
        }

        return false;
    }

    /**
     * Get the team ID safely
     */
    protected function getTeamId()
    {
        try
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                return auth()->user()->currentTeam->id;
            }

            $teamId = session('current_team_id');
            if ($teamId)
            {
                return $teamId;
            }

            // Return default team ID as fallback
            return 1;
        } catch (\Exception $e)
        {
            return null;
        }
    }
}
