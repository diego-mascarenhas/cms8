<?php

namespace App\Translation;

use App\Services\CustomTranslationService;
use Illuminate\Translation\Translator;

class CustomTranslator extends Translator
{
    protected $customTranslationService;

    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        // Skip custom translation during bootstrap/console commands to prevent DB queries
        if ($this->shouldSkipCustomTranslation())
        {
            return parent::get($key, $replace, $locale, $fallback);
        }

        // Try custom translation first
        try
        {
            if (! $this->customTranslationService)
            {
                $this->customTranslationService = app(CustomTranslationService::class);
            }

            $customTranslation = $this->customTranslationService->get($key, 'app', $locale);

            if ($customTranslation !== null)
            {
                // Apply replacements to custom translation
                foreach ($replace as $search => $replaceValue)
                {
                    $customTranslation = str_replace(':'.$search, $replaceValue, $customTranslation);
                }

                return $customTranslation;
            }
        } catch (\Exception $e)
        {
            // Continue to parent if error
        }

        // Fallback to Laravel's default translation
        return parent::get($key, $replace, $locale, $fallback);
    }

    /**
     * Determine if custom translation should be skipped
     */
    protected function shouldSkipCustomTranslation()
    {
        // Skip during console commands (like package:discover, migration, etc.)
        if (app()->runningInConsole())
        {
            return true;
        }

        // Skip if no authenticated user or no current team
        if (! auth()->check() || ! auth()->user()->currentTeam)
        {
            return true;
        }

        // Skip during bootstrap phase
        if (! app()->hasBeenBootstrapped())
        {
            return true;
        }

        return false;
    }
}
