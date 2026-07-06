<?php

namespace App\Support;

use Symfony\Component\VarExporter\VarExporter;

final class LocaleTranslationFiller
{
    /**
     * @param  list<string>  $targetLocales
     * @return array<string, array{json_added: int, php_created: int, php_updated: int}>
     */
    public function fillFromLocale(string $sourceLocale, array $targetLocales): array
    {
        $summary = [];

        foreach ($targetLocales as $targetLocale)
        {
            if ($targetLocale === $sourceLocale)
            {
                continue;
            }

            $summary[$targetLocale] = [
                'json_added' => $this->fillJsonFile($sourceLocale, $targetLocale),
                'php_created' => 0,
                'php_updated' => 0,
            ];

            [$created, $updated] = $this->fillPhpFiles($sourceLocale, $targetLocale);
            $summary[$targetLocale]['php_created'] = $created;
            $summary[$targetLocale]['php_updated'] = $updated;
        }

        return $summary;
    }

    private function fillJsonFile(string $sourceLocale, string $targetLocale): int
    {
        $sourcePath = lang_path("{$sourceLocale}.json");
        $targetPath = lang_path("{$targetLocale}.json");

        if (! is_file($sourcePath))
        {
            return 0;
        }

        /** @var array<string, string> $source */
        $source = json_decode((string) file_get_contents($sourcePath), true, 512, JSON_THROW_ON_ERROR);

        /** @var array<string, string> $target */
        $target = is_file($targetPath)
            ? json_decode((string) file_get_contents($targetPath), true, 512, JSON_THROW_ON_ERROR)
            : [];

        $added = 0;

        foreach ($source as $key => $value)
        {
            if (array_key_exists($key, $target))
            {
                continue;
            }

            $target[$key] = $targetLocale === 'en' ? $key : $value;
            $added++;
        }

        if ($added === 0)
        {
            return 0;
        }

        ksort($target, SORT_STRING);

        file_put_contents(
            $targetPath,
            json_encode($target, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
        );

        return $added;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function fillPhpFiles(string $sourceLocale, string $targetLocale): array
    {
        $sourceDir = lang_path($sourceLocale);
        $targetDir = lang_path($targetLocale);

        if (! is_dir($sourceDir))
        {
            return [0, 0];
        }

        if (! is_dir($targetDir))
        {
            mkdir($targetDir, 0755, true);
        }

        $created = 0;
        $updated = 0;

        foreach (glob($sourceDir.'/*.php') ?: [] as $sourceFile)
        {
            $filename = basename($sourceFile);
            $targetFile = $targetDir.'/'.$filename;

            /** @var array<string, mixed> $sourceTranslations */
            $sourceTranslations = require $sourceFile;

            if (! is_file($targetFile))
            {
                $this->writePhpTranslationFile($targetFile, $sourceTranslations);
                $created++;

                continue;
            }

            /** @var array<string, mixed> $targetTranslations */
            $targetTranslations = require $targetFile;
            $merged = $this->mergeMissingKeys($sourceTranslations, $targetTranslations);

            if ($merged !== $targetTranslations)
            {
                $this->writePhpTranslationFile($targetFile, $merged);
                $updated++;
            }
        }

        return [$created, $updated];
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    private function mergeMissingKeys(array $source, array $target): array
    {
        foreach ($source as $key => $value)
        {
            if (! array_key_exists($key, $target))
            {
                $target[$key] = $value;

                continue;
            }

            if (is_array($value) && is_array($target[$key]))
            {
                $target[$key] = $this->mergeMissingKeys($value, $target[$key]);
            }
        }

        return $target;
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    private function writePhpTranslationFile(string $path, array $translations): void
    {
        $exported = VarExporter::export($translations);

        file_put_contents($path, "<?php\n\nreturn {$exported};\n");
    }
}
