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

        try
        {
            // Load all translations for this team at once and cache them
            $allTranslations = $this->getAllTranslationsForTeam($teamId);

            // Find the specific translation
            $cacheKey = "{$key}_{$group}_{$locale}";

            return $allTranslations[$cacheKey] ?? null;
        } catch (\Exception $e)
        {
            // Return null if database query fails
            return null;
        }
    }

    /**
     * Get all translations for a team, cached
     */
    protected function getAllTranslationsForTeam($teamId)
    {
        $cacheKey = "custom_translations_team_{$teamId}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($teamId) {
            $translations = CustomTranslation::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->get();

            // Build associative array for fast lookups
            $result = [];
            foreach ($translations as $translation)
            {
                $key = "{$translation->key}_{$translation->group}_{$translation->locale}";
                $result[$key] = $translation->value;
            }

            return $result;
        });
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

            // Clear team cache
            $this->clearTeamCache($teamId);

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
            // Clear team cache
            $this->clearTeamCache($teamId);

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
     * Clear cache for specific translation or entire team
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
            // Always clear the entire team cache since we now cache all translations together
            $this->clearTeamCache($teamId);

            return true;
        } catch (\Exception $e)
        {
            return false;
        }
    }

    /**
     * Clear all cached translations for a team
     */
    protected function clearTeamCache($teamId)
    {
        Cache::forget("custom_translations_team_{$teamId}");
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

        // Check table existence with cache (24 hour TTL) to avoid repeated schema queries
        try
        {
            $tableExists = Cache::remember('custom_translations_table_exists', now()->addHours(24), function () {
                return \Illuminate\Support\Facades\Schema::hasTable('custom_translations');
            });

            if (! $tableExists)
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
