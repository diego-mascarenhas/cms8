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

    public function test_a_photo_is_read_by_the_vision_model(): void
    {
        $ai = $this->createMock(DocumentAiOcrService::class);
        $ai->expects($this->never())->method('extractTextFromLocalFile');
        $ai->expects($this->once())
            ->method('completeWithFile')
            ->willReturn('{"kind":"price_list","supplier_name":"Don Silvio","currency":"ARS","lines":[{"name":"Asado","cost":13890,"unit":"KG"}]}');
        $documents = new CommercialDocumentInterpreter($ai);
        $path = tempnam(sys_get_temp_dir(), 'foto').'.jpg';
        file_put_contents($path, 'not-a-real-image');

        try
        {
            $document = $documents->interpret($path, 'lista.jpg');
        } finally
        {
            @unlink($path);
        }

        $this->assertSame('price_list', $document['kind']);
        $this->assertSame('Don Silvio', $document['supplier_name']);
        $this->assertEquals(13890, $document['lines'][0]['cost']);
        $this->assertSame('KG', $document['lines'][0]['unit']);
    }

    public function test_a_pdf_price_list_uses_the_invoice_vision_model(): void
    {
        $ai = $this->createMock(DocumentAiOcrService::class);
        $ai->expects($this->never())->method('complete');
        $ai->expects($this->once())
            ->method('completeWithFile')
            ->willReturn('{"kind":"price_list","supplier_name":"MarJuanC","currency":"ARS","lines":[{"code":"ACE120","name":"Aceite","cost":17753.37}]}');
        $documents = new CommercialDocumentInterpreter($ai);
        $path = tempnam(sys_get_temp_dir(), 'lista-').'.pdf';
        file_put_contents($path, $this->minimalTextPdf('LISTA DE PRECIOS'));

        try
        {
            $document = $documents->interpret($path, 'LISTA DE PRECIOS 13-11.pdf');
        } finally
        {
            @unlink($path);
        }

        $this->assertSame('MarJuanC', $document['supplier_name']);
        $this->assertEquals(17753.37, $document['lines'][0]['cost']);
    }

    public function test_a_spreadsheet_uses_the_same_model_as_invoice_images(): void
    {
        $ai = $this->createMock(DocumentAiOcrService::class);
        $ai->expects($this->never())->method('completeWithFile');
        $ai->expects($this->once())
            ->method('complete')
            ->willReturn('{"kind":"price_list","supplier_name":"La Reina Mayorista","currency":"ARS","lines":[{"name":"Aceite girasol","cost":23300.5}]}');
        $documents = new CommercialDocumentInterpreter($ai);
        $path = sys_get_temp_dir().'/lista-'.uniqid('', true).'.xlsx';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row><c t="inlineStr"><is><t>Aceite girasol</t></is></c><c><v>23300.5</v></c></row></sheetData></worksheet>');
        $zip->close();

        try
        {
            $document = $documents->interpret($path, 'Precios_Mayorista La reina.xlsx');
        } finally
        {
            @unlink($path);
        }

        $this->assertSame('La Reina Mayorista', $document['supplier_name']);
        $this->assertEquals(23300.5, $document['lines'][0]['cost']);
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
