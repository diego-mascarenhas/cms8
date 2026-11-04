<?php

namespace Tests\Unit;

use App\Services\CommercialDocumentInterpreter;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class CommercialDocumentInterpreterTest extends TestCase
{
    public function test_argentine_prices_and_requisition_quantities_are_normalized(): void
    {
        $documents = new CommercialDocumentInterpreter(new \App\Services\DocumentAiOcrService);

        $document = $documents->documentFromModelText(<<<'JSON'
```json
{"kind":"price_list","supplier_name":"Maxikol","currency":"ARS","lines":[{"code":"A1","name":"Aceite","cost":"12.900,00","quantity":"3 PC","unit":"PC"}]}
```
JSON);

        $this->assertSame('price_list', $document['kind']);
        $this->assertSame('Maxikol', $document['supplier_name']);
        $this->assertEquals(12900, $document['lines'][0]['cost']);
        $this->assertEquals(3, $document['lines'][0]['quantity']);
    }

    public function test_a_sheet_without_prices_is_a_requisition(): void
    {
        $documents = new CommercialDocumentInterpreter(new \App\Services\DocumentAiOcrService);
        $document = $documents->documentFromModelText('{"kind":"price_list","lines":[{"name":"Aprons","code":"15 04 63","quantity":5}]}');

        $this->assertSame('requisition', $document['kind']);
        $this->assertNull($document['lines'][0]['cost']);
    }

    public function test_spreadsheet_text_keeps_the_item_and_the_quantity(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'list');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>STAINLESS STEEL CONTAINERS</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row><c t="s"><v>0</v></c><c><v>3</v></c></row></sheetData></worksheet>');
        $zip->close();

        $documents = new CommercialDocumentInterpreter(new \App\Services\DocumentAiOcrService);
        $text = $documents->spreadsheetText($path);

        $this->assertStringContainsString('STAINLESS STEEL CONTAINERS', $text);
        $this->assertStringContainsString('3', $text);
        unlink($path);
    }
}
