<?php

namespace Tests\Unit;

use App\Http\Requests\Api\ImportSupplierPriceListRequest;
use App\Http\Requests\Api\InterpretQuoteDocumentRequest;
use App\Services\DocumentOcrService;
use App\Services\SupplierPriceListImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

class SupplierPriceListReaderRequirementsTest extends TestCase
{
    public function test_supplier_price_lists_accept_every_required_extension(): void
    {
        $this->assertSame(
            ['csv', 'txt', 'xlsx', 'pdf', 'png', 'jpg', 'jpeg', 'webp'],
            SupplierPriceListImportService::FILE_EXTENSIONS,
        );

        foreach ([InterpretQuoteDocumentRequest::class, ImportSupplierPriceListRequest::class] as $requestClass)
        {
            foreach (SupplierPriceListImportService::FILE_EXTENSIONS as $extension)
            {
                $this->assertFalse(
                    $this->extensionErrors($requestClass, "lista.{$extension}")->errors()->has('file'),
                    $requestClass.' rejects .'.$extension,
                );
            }

            $this->assertTrue($this->extensionErrors($requestClass, 'lista.docx')->errors()->has('file'));
        }
    }

    public function test_pdf_and_spreadsheet_readers_are_installed(): void
    {
        $binary = app(DocumentOcrService::class)->pdftotextBinary();
        $this->assertNotNull($binary);
        $this->assertTrue(is_executable($binary));

        $path = tempnam(sys_get_temp_dir(), 'lista-').'.pdf';
        file_put_contents($path, $this->minimalTextPdf('Aceite 12900'));
        $process = new Process([$binary, '-layout', '-enc', 'UTF-8', $path, '-']);

        try
        {
            $process->setTimeout(15);
            $process->run();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $this->assertStringContainsString('Aceite 12900', $process->getOutput());
        } finally
        {
            @unlink($path);
        }

        $this->assertTrue(class_exists(ZipArchive::class));
        $sheet = tempnam(sys_get_temp_dir(), 'lista-').'.xlsx';
        $zip = new ZipArchive;
        $opened = $zip->open($sheet, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $this->assertTrue($opened);
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet/>');
        $zip->close();
        $this->assertFileExists($sheet);
        @unlink($sheet);

        $this->assertTrue(function_exists('mime_content_type'));
    }

    /**
     * @param  class-string<InterpretQuoteDocumentRequest|ImportSupplierPriceListRequest>  $requestClass
     */
    private function extensionErrors(string $requestClass, string $filename): Validator
    {
        $file = UploadedFile::fake()->create($filename, 1);
        $request = $requestClass::create('/api/shop/supplier-price-lists/preview', 'POST', files: ['file' => $file]);
        $request->setContainer($this->app);
        $validator = validator(['file' => $file], $request->rules());
        $request->withValidator($validator);
        $validator->passes();

        return $validator;
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
0000000009 00000 n 
0000000058 00000 n 
0000000115 00000 n 
0000000317 00000 n 
0000000400 00000 n 
trailer << /Size 6 /Root 1 0 R >>
startxref
480
%%EOF
PDF;
    }
}
