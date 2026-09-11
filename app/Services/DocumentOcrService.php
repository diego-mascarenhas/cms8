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

        $pdfText = $this->extractTextFromPdf($absolutePath);
        if ($pdfText !== null)
        {
            return $pdfText;
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

    private function extractTextFromPdf(string $absolutePath): ?string
    {
        if (! $this->isPdfFile($absolutePath))
        {
            return null;
        }

        $binary = $this->resolvePdftotextBinary();
        if ($binary === null)
        {
            return null;
        }

        $process = new Process([
            $binary,
            '-layout',
            '-enc',
            'UTF-8',
            $absolutePath,
            '-',
        ]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful())
        {
            return null;
        }

        $text = trim($process->getOutput());

        return $text !== '' ? $text : null;
    }

    private function isPdfFile(string $absolutePath): bool
    {
        $mime = (string) (mime_content_type($absolutePath) ?: '');

        return str_contains($mime, 'pdf')
            || str_ends_with(strtolower($absolutePath), '.pdf');
    }

    private function resolvePdftotextBinary(): ?string
    {
        $configured = trim((string) config('app.pdftotext_binary_path', ''));
        if ($configured !== '' && is_file($configured) && is_executable($configured))
        {
            return $configured;
        }

        $candidates = [
            '/opt/homebrew/bin/pdftotext',
            '/usr/local/bin/pdftotext',
            '/usr/bin/pdftotext',
        ];

        foreach ($candidates as $candidate)
        {
            if (is_file($candidate) && is_executable($candidate))
            {
                return $candidate;
            }
        }

        $process = new Process(['which', 'pdftotext']);
        $process->run();
        $resolved = trim($process->isSuccessful() ? $process->getOutput() : '');

        return $resolved !== '' && is_executable($resolved) ? $resolved : null;
    }
}
