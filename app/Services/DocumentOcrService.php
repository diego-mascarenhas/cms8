<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class DocumentOcrService
{
    public function extractTextFromLocalFile(string $absolutePath): ?string
    {
        if (! is_file($absolutePath))
        {
            return null;
        }

        $languages = (string) config('app.ocr_languages', 'spa+eng');
        $binary = $this->resolveTesseractBinary();
        $process = new Process([
            $binary,
            $absolutePath,
            'stdout',
            '-l',
            $languages,
        ]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful())
        {
            $fallback = new Process([
                $binary,
                $absolutePath,
                'stdout',
                '-l',
                'eng',
            ]);
            $fallback->setTimeout(30);
            $fallback->run();
            if (! $fallback->isSuccessful())
            {
                return null;
            }

            $text = trim($fallback->getOutput());

            return $text !== '' ? $text : null;
        }

        $text = trim($process->getOutput());
        if ($text === '')
        {
            return null;
        }

        return $text;
    }

    private function resolveTesseractBinary(): string
    {
        $configured = trim((string) config('app.ocr_binary_path', ''));
        if ($configured !== '')
        {
            return $configured;
        }

        $candidates = [
            '/opt/homebrew/bin/tesseract',
            '/usr/local/bin/tesseract',
            '/usr/bin/tesseract',
        ];

        foreach ($candidates as $candidate)
        {
            if (is_file($candidate) && is_executable($candidate))
            {
                return $candidate;
            }
        }

        return 'tesseract';
    }
}
