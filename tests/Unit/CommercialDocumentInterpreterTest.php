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

    public function test_a_column_price_list_uses_the_bonified_price(): void
    {
        $documents = new CommercialDocumentInterpreter(new \App\Services\DocumentAiOcrService);
        $document = $documents->priceListFromText(<<<'TEXT'
13-11-2026
Grito de Asencio 3632
ACEITES                                                                                      Cant p/ desc   U.V   Prec. Lista         Desc.         Precio bonif.
ACE20                               ACEITE DE GIRASOL DON HUGO X 5 LTS (21.00)                   20         Uni       18,698.28       5.00%              17,763.37
DUL012             DULCE DE LECHE DON BENITO CARTÓN X 10 KG (21.00)                         2,416.13   +1 tacho bonif.    2,196.48
AZU001                              AZUCAR IMPALPABLE MARJUANC X 10 KG (21.00)                   100        Kg         1,759.40   +1 bolsa bonif.         1,599.46
TEXT, 'LISTA DE PRECIOS 13-11.pdf');

        $this->assertNotNull($document);
        $this->assertSame('MarJuanC', $document['supplier_name']);
        $this->assertSame('ARS', $document['currency']);
        $this->assertSame('ACE20', $document['lines'][0]['code']);
        $this->assertSame('ACEITE DE GIRASOL DON HUGO X 5 LTS', $document['lines'][0]['name']);
        $this->assertEquals(17763.37, $document['lines'][0]['cost']);
        $this->assertNull($document['lines'][0]['quantity']);
        $this->assertSame('Uni', $document['lines'][0]['unit']);
        $this->assertSame('DULCE DE LECHE DON BENITO CARTÓN X 10 KG', $document['lines'][1]['name']);
        $this->assertEquals(2196.48, $document['lines'][1]['cost']);
        $this->assertEquals(1599.46, $document['lines'][2]['cost']);
        $this->assertSame('Kg', $document['lines'][2]['unit']);
    }

    public function test_a_price_sheet_names_the_supplier_and_the_first_price(): void
    {
        $documents = new CommercialDocumentInterpreter(new \App\Services\DocumentAiOcrService);
        $document = $documents->priceListFromText(<<<'TEXT'
MAXIKOL Av. eva peron 3453
COD REF	PRODUCTO	PV 1	PV 2
7792180001665	ACEITE CAÑUELAS X 1,5 L - UNIDAD	5,488.00	5,390.00
7792180001641	ACEITE CAÑUELAS X 900 ML - UNIDAD	3,353.28	3,293.40
7790060234868	ACEITE COCINERO X MEZCLA 900 ML - UNIDAD	3,143.19	3,061.20
TEXT, '2026-10-28-Maxikol-Listado-productos.pdf');

        $this->assertNotNull($document);
        $this->assertSame('MAXIKOL', $document['supplier_name']);
        $this->assertSame('7792180001665', $document['lines'][0]['code']);
        $this->assertSame('ACEITE CAÑUELAS X 1,5 L - UNIDAD', $document['lines'][0]['name']);
        $this->assertEquals(5488, $document['lines'][0]['cost']);
        $this->assertCount(3, $document['lines']);
    }

    public function test_a_ship_quotation_keeps_code_name_quantity_and_net_cost(): void
    {
        $documents = new CommercialDocumentInterpreter(new \App\Services\DocumentAiOcrService);
        $document = $documents->quotationFromText(<<<'TEXT'
Page 	2 of 5
2BAMBOO 	000103	-	-	10.0	KG	-	-NA
SHOOT FRESH
3BEANS STRING 	000107	-	-	15.0	KG	15.0	3.02	10.00%	40.77	2-
FRESH
4BROCCOLI 	000109	-	-	30.0	KG	30.0	5.02	10.00%	135.54	2-
FRESH
Subtotal:	USD 7,922.29
TEXT, 'qt_9020-FRS265012A_Seven_Seas_Ship_Suppliers_Argentina.pdf');

        $this->assertNotNull($document);
        $this->assertSame('price_list', $document['kind']);
        $this->assertSame('Seven Seas Ship Suppliers Argentina', $document['supplier_name']);
        $this->assertSame('USD', $document['currency']);
        $this->assertSame('000103', $document['lines'][0]['code']);
        $this->assertSame('BAMBOO SHOOT FRESH', $document['lines'][0]['name']);
        $this->assertNull($document['lines'][0]['cost']);
        $this->assertEquals(10, $document['lines'][0]['quantity']);
        $this->assertSame('KG', $document['lines'][0]['unit']);
        $this->assertSame('BEANS STRING FRESH', $document['lines'][1]['name']);
        $this->assertEquals(2.72, $document['lines'][1]['cost']);
        $this->assertEquals(15, $document['lines'][1]['quantity']);
        $this->assertSame('BROCCOLI FRESH', $document['lines'][2]['name']);
        $this->assertEquals(4.52, $document['lines'][2]['cost']);
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

    public function test_a_workbook_keeps_every_category_sheet_and_skips_the_intro(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'list');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $main = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $zip->addFromString(
            'xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="'.$main.'" xmlns:r="'.$rel.'">'
            .'<sheets>'
            .'<sheet name="INTRO" sheetId="1" r:id="rId1"/>'
            .'<sheet name="ALMACEN" sheetId="2" r:id="rId2"/>'
            .'<sheet name="FIAMBRES" sheetId="3" r:id="rId3"/>'
            .'<sheet name="Sheet28" sheetId="4" state="hidden" r:id="rId4"/>'
            .'</sheets></workbook>',
        );
        $zip->addFromString(
            'xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="worksheet" Target="worksheets/sheet2.xml"/>'
            .'<Relationship Id="rId3" Type="worksheet" Target="worksheets/sheet3.xml"/>'
            .'<Relationship Id="rId4" Type="worksheet" Target="worksheets/sheet4.xml"/>'
            .'</Relationships>',
        );
        $sheet = fn (string $value): string => '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="'.$main.'"><sheetData><row><c><v>'.$value.'</v></c></row></sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet('1'));
        $zip->addFromString('xl/worksheets/sheet2.xml', $sheet('23300.5'));
        $zip->addFromString('xl/worksheets/sheet3.xml', $sheet('11100'));
        $zip->addFromString('xl/worksheets/sheet4.xml', $sheet('999'));
        $zip->close();

        $documents = new CommercialDocumentInterpreter(new \App\Services\DocumentAiOcrService);
        $text = $documents->spreadsheetText($path);

        $this->assertStringContainsString("# ALMACEN\n23300.5", $text);
        $this->assertStringContainsString("# FIAMBRES\n11100", $text);
        $this->assertStringNotContainsString('INTRO', $text);
        $this->assertStringNotContainsString('999', $text);
        unlink($path);
    }
}
