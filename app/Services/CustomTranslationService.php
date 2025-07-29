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
		$locale = $locale ?: app()->getLocale();

		// Get team ID
		$teamId = null;
		if (auth()->check() && auth()->user()->currentTeam) {
			$teamId = auth()->user()->currentTeam->id;
		} else {
			$teamId = session('current_team_id');
			if (!$teamId) {
				$teamId = 1; // Default team ID
			}
		}

		// Try to get from cache first
		$cacheKey = "custom_translation_{$teamId}_{$key}_{$group}_{$locale}";
		$cached = Cache::get($cacheKey);
		if ($cached !== null) {
			return $cached;
		}

		// Get from database
		$translation = CustomTranslation::where('team_id', $teamId)
			->where('key', $key)
			->where('group', $group)
			->where('locale', $locale)
			->first();

		if (!$translation) {
			return null;
		}

		// Get the value (now it's a simple string)
		$value = $translation->value;

		// Cache the result
		Cache::put($cacheKey, $value, now()->addHours(24));

		return $value;
	}

	/**
	 * Set a custom translation for the current team
	 */
	public function set($key, $value, $group = 'app', $locale = null)
	{
		$locale = $locale ?: app()->getLocale();

		// Get team ID
		$teamId = null;
		if (auth()->check() && auth()->user()->currentTeam) {
			$teamId = auth()->user()->currentTeam->id;
		} else {
			$teamId = session('current_team_id');
			if (!$teamId) {
				$teamId = 1; // Default team ID
			}
		}

		// Create or update the translation
		$translation = CustomTranslation::updateOrCreate(
			[
				'team_id' => $teamId,
				'key' => $key,
				'group' => $group,
				'locale' => $locale,
			],
			[
				'value' => $value, // Now it's a simple string
			]
		);

		// Clear cache
		$this->clearCache($key, $group, $locale);

		return $translation;
	}

	/**
	 * Remove a custom translation
	 */
	public function remove($key, $group = 'app', $locale = null)
	{
		$locale = $locale ?: app()->getLocale();

		// Get team ID
		$teamId = null;
		if (auth()->check() && auth()->user()->currentTeam) {
			$teamId = auth()->user()->currentTeam->id;
		} else {
			$teamId = session('current_team_id');
			if (!$teamId) {
				$teamId = 1; // Default team ID
			}
		}

		if (!$teamId) {
			return false;
		}

		// Clear cache
		$cacheKey = "custom_translation_{$teamId}_{$key}_{$group}_{$locale}";
		Cache::forget($cacheKey);

		return CustomTranslation::where('team_id', $teamId)
			->where('key', $key)
			->where('group', $group)
			->where('locale', $locale)
			->delete();
	}

	/**
	 * Get all custom translations for a team
	 */
	public function getAll($group = null, $locale = null)
	{
		// Get team ID
		$teamId = null;
		if (auth()->check() && auth()->user()->currentTeam) {
			$teamId = auth()->user()->currentTeam->id;
		} else {
			$teamId = session('current_team_id');
			if (!$teamId) {
				$teamId = 1; // Default team ID
			}
		}

		if (!$teamId) {
			return collect();
		}

		$query = CustomTranslation::where('team_id', $teamId);

		if ($group) {
			$query->where('group', $group);
		}

		if ($locale) {
			$query->where('locale', $locale);
		}

		return $query->get();
	}

	/**
	 * Clear cache for specific translation
	 */
	public function clearCache($key = null, $group = null, $locale = null)
	{
		// Get team ID
		$teamId = null;
		if (auth()->check() && auth()->user()->currentTeam) {
			$teamId = auth()->user()->currentTeam->id;
		} else {
			$teamId = session('current_team_id');
			if (!$teamId) {
				$teamId = 1; // Default team ID
			}
		}

		if (!$teamId) {
			return false;
		}

		if ($key && $group && $locale) {
			// Clear specific cache
			$cacheKey = "custom_translation_{$teamId}_{$key}_{$group}_{$locale}";
			Cache::forget($cacheKey);
		} else {
			// Clear all cache for team
			$translations = CustomTranslation::where('team_id', $teamId)->get();

			foreach ($translations as $translation) {
				$cacheKey = "custom_translation_{$teamId}_{$translation->key}_{$translation->group}_{$translation->locale}";
				Cache::forget($cacheKey);
			}
		}

		return true;
	}
}
