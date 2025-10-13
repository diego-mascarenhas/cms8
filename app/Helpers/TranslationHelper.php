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

        if ($customTranslation !== null)
        {
            // Apply replacements to custom translation
            foreach ($replace as $search => $replaceValue)
            {
                $customTranslation = str_replace(':'.$search, $replaceValue, $customTranslation);
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

        if ($customTranslation !== null)
        {
            // Apply replacements to custom translation
            foreach ($replace as $search => $replaceValue)
            {
                $customTranslation = str_replace(':'.$search, $replaceValue, $customTranslation);
            }

            return $customTranslation;
        }

        // If no custom translation, try to find the Laravel translation
        // For auth group with welcome key, we need to map to the correct Laravel key
        if ($group === 'auth' && $key === 'welcome')
        {
            return __('auth.login.welcome', $replace, $locale);
        }

        // For other cases, try different combinations
        $possibleKeys = [
            $group.'.'.$key,		   // auth.welcome
            $key,						  // welcome (fallback)
        ];

        foreach ($possibleKeys as $translationKey)
        {
            $translation = __($translationKey, $replace, $locale);
            // If Laravel returns the key itself, it means the translation doesn't exist
            if ($translation !== $translationKey)
            {
                return $translation;
            }
        }

        // If no translation found, return the most logical key
        return $group.'.'.$key;
    }
}
