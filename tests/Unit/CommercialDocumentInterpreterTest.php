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

    public function test_excel_markup_compatibility_prefixes_do_not_break_the_sheet(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'list');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString(
            'xl/sharedStrings.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="1" uniqueCount="1">'
            .'<si><t>STAINLESS STEEL CONTAINERS</t></si></sst>',
        );
        $zip->addFromString(
            'xl/worksheets/sheet1.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            .' xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"'
            .' mc:Ignorable="x14ac xr xr2 xr3"'
            .' xmlns:x14ac="http://schemas.microsoft.com/office/spreadsheetml/2009/9/ac"'
            .' xmlns:xr="http://schemas.microsoft.com/office/spreadsheetml/2014/revision"'
            .' xr:uid="{00000000-0001-0000-0000-000000000000}">'
            .'<sheetFormatPr defaultRowHeight="15" x14ac:dyDescent="0.25"/>'
            .'<sheetData>'
            .'<row r="1" spans="1:2" x14ac:dyDescent="0.25">'
            .'<c r="A1" t="s"><v>0</v></c>'
            .'<c r="B1"><v>3</v></c>'
            .'</row>'
            .'</sheetData>'
            .'<mc:AlternateContent><mc:Choice Requires="x14ac"><x14ac:note/></mc:Choice></mc:AlternateContent>'
            .'</worksheet>',
        );
        $zip->close();

        $documents = new CommercialDocumentInterpreter(new \App\Services\DocumentAiOcrService);
        $text = $documents->spreadsheetText($path);

        $this->assertSame("STAINLESS STEEL CONTAINERS\t3", $text);
        unlink($path);
    }
}
