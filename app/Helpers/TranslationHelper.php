<?php

namespace App\Helpers;

use App\Services\CustomTranslationService;

class TranslationHelper
{
	/**
	 * Get a translation, checking custom translations first
	 */
	public static function trans($key, $replace = [], $locale = null)
	{
		$translationService = app(CustomTranslationService::class);

		// First, check if there's a custom translation
		$customTranslation = $translationService->get($key, 'app', $locale);

		if ($customTranslation !== null) {
			// Apply replacements to custom translation
			foreach ($replace as $search => $replaceValue) {
				$customTranslation = str_replace(':' . $search, $replaceValue, $customTranslation);
			}
			return $customTranslation;
		}

		// If no custom translation, use Laravel's default translation
		return __($key, $replace, $locale);
	}

	/**
	 * Get a translation from a specific group, checking custom translations first
	 */
	public static function transGroup($key, $group, $replace = [], $locale = null)
	{
		$translationService = app(CustomTranslationService::class);

		// First, check if there's a custom translation
		$customTranslation = $translationService->get($key, $group, $locale);

		if ($customTranslation !== null) {
			// Apply replacements to custom translation
			foreach ($replace as $search => $replaceValue) {
				$customTranslation = str_replace(':' . $search, $replaceValue, $customTranslation);
			}
			return $customTranslation;
		}

		// If no custom translation, use Laravel's default translation with group
		return __($group . '.' . $key, $replace, $locale);
	}
}
