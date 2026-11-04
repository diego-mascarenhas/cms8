<?php

namespace Tests\Unit;

use App\Services\DocumentOcrService;
use Tests\TestCase;

class DocumentOcrServiceTest extends TestCase
{
    public function test_extracts_selectable_text_from_pdf(): void
    {
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

    public function test_extracts_text_from_a_compressed_pdf_stream(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'invoice-').'.pdf';
        file_put_contents($path, $this->flateTextPdf('Aceite 12900'));

        try
        {
            $text = app(DocumentOcrService::class)->extractTextFromLocalFile($path);
        } finally
        {
            @unlink($path);
        }

        $this->assertStringContainsString('Aceite 12900', (string) $text);
    }

    public function test_keeps_the_font_when_the_price_is_in_the_next_stream(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'price-').'.pdf';
        file_put_contents($path, $this->splitFontPdf());

        try
        {
            $text = app(DocumentOcrService::class)->extractTextFromLocalFile($path);
        } finally
        {
            @unlink($path);
        }

        $this->assertStringContainsString('MAXIKOL', (string) $text);
        $this->assertStringContainsString('5488.00', (string) $text);
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

    private function flateTextPdf(string $text): string
    {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $stream = gzcompress("BT /F1 12 Tf 50 700 Td ({$escaped}) Tj ET");
        $length = strlen($stream);

        return <<<PDF
%PDF-1.4
1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj
2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj
3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj
4 0 obj << /Filter /FlateDecode /Length {$length} >> stream
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

    private function splitFontPdf(): string
    {
        $name = '1 0 0 1 40 700 Tm /F1 12 Tf <004D004100580049004B004F004C> Tj';
        $price = '1 0 0 1 220 700 Tm <0035003400380038002E00300030> Tj';
        $cmap = <<<'CMAP'
5 beginbfchar
<002E> <002E>
<0030> <0030>
<0034> <0034>
<0035> <0035>
<0038> <0038>
endbfchar
1 beginbfrange
<0041> <0058> <0041>
endbfrange
CMAP;

        return <<<PDF
%PDF-1.4
1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj
2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj
3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents [4 0 R 5 0 R] /Resources << /Font << /F1 6 0 R >> >> >> endobj
4 0 obj << /Length 62 >> stream
{$name}
endstream endobj
5 0 obj << /Length 48 >> stream
{$price}
endstream endobj
6 0 obj << /Type /Font /Subtype /Type0 /BaseFont /Arial /ToUnicode 7 0 R >> endobj
7 0 obj << /Length 120 >> stream
{$cmap}
endstream endobj
trailer << /Size 8 /Root 1 0 R >>
%%EOF
PDF;
    }
}
