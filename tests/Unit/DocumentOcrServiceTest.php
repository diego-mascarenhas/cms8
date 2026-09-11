<?php

namespace Tests\Unit;

use App\Services\DocumentOcrService;
use Tests\TestCase;

class DocumentOcrServiceTest extends TestCase
{
    public function test_extracts_selectable_text_from_pdf(): void
    {
        $binary = $this->resolvePdftotextBinary();
        if ($binary === null)
        {
            $this->markTestSkipped('pdftotext is not installed.');
        }

        $path = tempnam(sys_get_temp_dir(), 'invoice-').'.pdf';
        file_put_contents($path, $this->minimalTextPdf('Invoice number OWZPCFGE-0030'));

        try
        {
            $text = app(DocumentOcrService::class)->extractTextFromLocalFile($path);
        } finally
        {
            @unlink($path);
        }

        $this->assertNotNull($text);
        $this->assertStringContainsString('OWZPCFGE-0030', (string) $text);
    }

    private function resolvePdftotextBinary(): ?string
    {
        foreach ([
            '/opt/homebrew/bin/pdftotext',
            '/usr/local/bin/pdftotext',
            '/usr/bin/pdftotext',
        ] as $candidate)
        {
            if (is_file($candidate) && is_executable($candidate))
            {
                return $candidate;
            }
        }

        return null;
    }

    private function minimalTextPdf(string $text): string
    {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $stream = "BT /F1 12 Tf 50 700 Td ({$escaped}) Tj ET";
        $length = strlen($stream);

        return <<<PDF
%PDF-1.4
1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj
2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj
3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj
4 0 obj << /Length {$length} >> stream
{$stream}
endstream endobj
5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj
xref
0 6
0000000000 65535 f 
trailer << /Size 6 /Root 1 0 R >>
startxref
0
%%EOF
PDF;
    }
}
