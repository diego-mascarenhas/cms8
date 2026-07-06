<?php

namespace App\Console\Commands;

use App\Support\ApplicationLocales;
use App\Support\LocaleTranslationFiller;
use Illuminate\Console\Command;

class FillTranslationsFromLocaleCommand extends Command
{
    protected $signature = 'translations:fill-from-locale
                            {source=es_ES : Source locale directory and JSON file}
                            {--targets=* : Target locales (defaults to all supported except source)}';

    protected $description = 'Fill missing translation keys and PHP files from a source locale (Spanish by default)';

    public function handle(LocaleTranslationFiller $filler): int
    {
        $sourceLocale = ApplicationLocales::normalize($this->argument('source'));
        $targets = $this->option('targets');

        if ($targets === [])
        {
            $targets = array_values(array_filter(
                ApplicationLocales::supported(),
                static fn (string $locale): bool => $locale !== $sourceLocale,
            ));
            $targets[] = 'en';
            $targets = array_values(array_unique($targets));
        }

        $this->info("Filling missing translations from [{$sourceLocale}] into: ".implode(', ', $targets));

        $summary = $filler->fillFromLocale($sourceLocale, $targets);

        foreach ($summary as $locale => $stats)
        {
            $this->line(sprintf(
                '- %s: %d JSON keys, %d PHP files created, %d PHP files updated',
                $locale,
                $stats['json_added'],
                $stats['php_created'],
                $stats['php_updated'],
            ));
        }

        return self::SUCCESS;
    }
}
