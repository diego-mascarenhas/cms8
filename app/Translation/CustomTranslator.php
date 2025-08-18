<?php

namespace App\Translation;

use Illuminate\Translation\Translator;
use App\Services\CustomTranslationService;

class CustomTranslator extends Translator
{
    protected $customTranslationService;

    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        // Try custom translation first
        try {
            if (!$this->customTranslationService) {
                $this->customTranslationService = app(CustomTranslationService::class);
            }

            $customTranslation = $this->customTranslationService->get($key, 'app', $locale);

            if ($customTranslation !== null) {
                // Apply replacements to custom translation
                foreach ($replace as $search => $replaceValue) {
                    $customTranslation = str_replace(':' . $search, $replaceValue, $customTranslation);
                }
                return $customTranslation;
            }
        } catch (\Exception $e) {
            // Continue to parent if error
        }

        // Fallback to Laravel's default translation
        return parent::get($key, $replace, $locale, $fallback);
    }
}
