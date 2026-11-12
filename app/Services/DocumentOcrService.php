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
            return $this->ensureUtf8Text($pdfText);
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

            return $text !== '' ? $this->ensureUtf8Text($text) : null;
        }

        $text = trim($process->getOutput());
        if ($text === '')
        {
            return null;
        }

        return $this->ensureUtf8Text($text);
    }

    private function ensureUtf8Text(string $text): string
    {
        if ($text === '' || mb_check_encoding($text, 'UTF-8'))
        {
            return $text;
        }

        $fromLatin1 = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $text);
        if (is_string($fromLatin1) && $fromLatin1 !== '' && mb_check_encoding($fromLatin1, 'UTF-8'))
        {
            return $fromLatin1;
        }

        $stripped = @iconv('UTF-8', 'UTF-8//IGNORE', $text);

        return is_string($stripped) ? $stripped : '';
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
        if ($binary !== null)
        {
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

            if ($process->isSuccessful())
            {
                $text = trim($process->getOutput());
                if ($text !== '')
                {
                    return $text;
                }
            }
        }

        $decoded = (new PdfTextExtractor)->extract($absolutePath);
        if ($decoded !== null)
        {
            return $decoded;
        }

        return $this->extractTextFromPdfStreams($absolutePath);
    }

    private function extractTextFromPdfStreams(string $absolutePath): ?string
    {
        $raw = file_get_contents($absolutePath);
        if (! is_string($raw) || $raw === '')
        {
            return null;
        }

        $parts = [];
        $offset = 0;
        while (($start = strpos($raw, 'stream', $offset)) !== false)
        {
            $dataStart = $start + strlen('stream');
            if (($raw[$dataStart] ?? '') === "\r")
            {
                $dataStart++;
            }
            if (($raw[$dataStart] ?? '') === "\n")
            {
                $dataStart++;
            }

            $end = strpos($raw, 'endstream', $dataStart);
            if ($end === false)
            {
                break;
            }

            $headerStart = max(0, $start - 400);
            $header = substr($raw, $headerStart, $start - $headerStart);
            $chunk = rtrim(substr($raw, $dataStart, $end - $dataStart), "\r\n");
            $offset = $end + strlen('endstream');

            if (str_contains($header, '/Image'))
            {
                continue;
            }

            if (str_contains($header, 'FlateDecode'))
            {
                $inflated = @gzuncompress($chunk);
                if ($inflated === false)
                {
                    $inflated = @gzinflate($chunk);
                }
                if (! is_string($inflated) || $inflated === '')
                {
                    continue;
                }
                $chunk = $inflated;
            }

            $text = $this->textFromPdfOperators($chunk);
            if ($text !== '')
            {
                $parts[] = $text;
            }
        }

        $text = trim(implode("\n", $parts));

        return $text !== '' ? $text : null;
    }

    private function textFromPdfOperators(string $content): string
    {
        preg_match_all(
            '/\(((?:\\\\.|[^\\\\)])*)\)\s*Tj|\[(.*?)\]\s*TJ/s',
            $content,
            $matches,
            PREG_SET_ORDER,
        );

        $lines = [];
        foreach ($matches as $match)
        {
            if (($match[1] ?? '') !== '')
            {
                $lines[] = $this->decodePdfLiteral($match[1]);

                continue;
            }

            preg_match_all('/\(((?:\\\\.|[^\\\\)])*)\)/', $match[2] ?? '', $pieces);
            $line = trim(implode('', array_map(
                fn (string $piece): string => $this->decodePdfLiteral($piece),
                $pieces[1] ?? [],
            )));
            if ($line !== '')
            {
                $lines[] = $line;
            }
        }

        return trim(implode("\n", $lines));
    }

    private function decodePdfLiteral(string $value): string
    {
        $decoded = preg_replace_callback(
            '/\\\\([nrtbf()\\\\]|[0-7]{1,3})/',
            function (array $match): string
            {
                return match ($match[1])
                {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'b' => "\x08",
                    'f' => "\f",
                    '(', ')', '\\' => $match[1],
                    default => chr(octdec($match[1])),
                };
            },
            $value,
        );

        return trim($decoded ?? $value);
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
