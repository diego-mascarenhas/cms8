<?php

namespace Tests\Unit;

use App\Services\CommercialDocumentInterpreter;
use App\Services\DocumentAiOcrService;
use Tests\TestCase;

class CommercialDocumentSourceTextTest extends TestCase
{
    public function test_selectable_pdf_text_is_read_without_calling_ai(): void
    {
        $ai = $this->createMock(DocumentAiOcrService::class);
        $ai->expects($this->never())->method('extractTextFromLocalFile');
        $documents = new CommercialDocumentInterpreter($ai);
        $path = tempnam(sys_get_temp_dir(), 'lista-').'.pdf';
        file_put_contents($path, $this->minimalTextPdf('Aceite 12900'));

        try
        {
            $text = $documents->sourceText($path, 'lista.pdf');
        } finally
        {
            @unlink($path);
        }

        $this->assertStringContainsString('Aceite 12900', $text);
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
