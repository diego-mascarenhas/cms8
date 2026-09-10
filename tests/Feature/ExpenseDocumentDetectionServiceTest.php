<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\Team;
use App\Services\DocumentAiOcrService;
use App\Services\DocumentOcrService;
use App\Services\ExpenseDocumentDetectionService;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ExpenseDocumentDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
        ]);
    }

    public function test_detect_from_uploaded_file_prefers_supplier_and_extracts_multiple_items(): void
    {
        $team = Team::factory()->create([
            'name' => 'REVISION ALPHA S.L.',
        ]);
        $team->setSetting('documents_ocr_mode', 'local', ['group' => 'documents']);

        $buyerEnterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 2,
            'status_id' => 1,
            'name' => 'REVISION ALPHA S.L.',
        ]);

        $supplierEnterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 2,
            'status_id' => 1,
            'name' => 'YOIGO S.A.',
        ]);

        $currency = Currency::query()->create([
            'code' => 'EUR',
            'name' => 'Euro',
            'symbol' => '€',
            'status' => true,
        ]);

        $ocrText = implode("\n", [
            'PROVEEDOR: YOIGO S.A.',
            'CLIENTE: REVISION ALPHA S.L.',
            'Teléfono atención: +34 613 194 131',
            'Número factura: YC260001189727',
            'Fecha factura: 2026-01-01',
            'Fecha vencimiento: 2026-01-05',
            'Servicio Fibra 500MB 45,00 21% 0%',
            'Bono móvil empresa 30,00 21% 0%',
            'TOTAL A PAGAR: 90,75 €',
        ]);

        $ocrService = $this->createMock(DocumentOcrService::class);
        $ocrService->expects($this->once())
            ->method('extractTextFromLocalFile')
            ->willReturn($ocrText);

        $aiOcrService = $this->createMock(DocumentAiOcrService::class);
        $aiOcrService->expects($this->never())
            ->method('extractTextFromLocalFile');

        $service = new ExpenseDocumentDetectionService(
            $ocrService,
            $aiOcrService,
            app(\App\Services\ExpenseSupplierService::class),
        );
        $uploadedFile = UploadedFile::fake()->create('factura-proveedor.pdf', 128, 'application/pdf');

        $detected = $service->detectFromUploadedFile($uploadedFile, $team->id);

        $this->assertSame($supplierEnterprise->id, $detected['enterprise_id']);
        $this->assertNotSame($buyerEnterprise->id, $detected['enterprise_id']);
        $this->assertSame('YOIGO S.A.', $detected['enterprise_name']);
        $this->assertSame('YC260001189727', $detected['document_number']);
        $this->assertSame('2026-01-01', $detected['date']);
        $this->assertSame('2026-01-05', $detected['due_date']);
        $this->assertSame($currency->id, $detected['currency_id']);
        $this->assertCount(2, $detected['lines']);
        $this->assertSame('Servicio Fibra 500MB', $detected['lines'][0]['concept']);
        $this->assertSame('Bono móvil empresa', $detected['lines'][1]['concept']);
    }

    public function test_detect_from_uploaded_file_ignores_phone_like_document_number(): void
    {
        $team = Team::factory()->create([
            'name' => 'REVISION ALPHA S.L.',
        ]);
        $team->setSetting('documents_ocr_mode', 'local', ['group' => 'documents']);

        $ocrText = implode("\n", [
            'PROVEEDOR: YOIGO S.A.',
            'Número factura: 613194131',
            'Teléfono atención: +34 613 194 131',
            'Fecha factura: 2026-01-01',
            'TOTAL A PAGAR: 16,00 €',
            'Servicio móvil 13,22 21% 0%',
        ]);

        $ocrService = $this->createMock(DocumentOcrService::class);
        $ocrService->expects($this->once())
            ->method('extractTextFromLocalFile')
            ->willReturn($ocrText);

        $aiOcrService = $this->createMock(DocumentAiOcrService::class);
        $aiOcrService->expects($this->never())
            ->method('extractTextFromLocalFile');

        $service = new ExpenseDocumentDetectionService(
            $ocrService,
            $aiOcrService,
            app(\App\Services\ExpenseSupplierService::class),
        );
        $uploadedFile = UploadedFile::fake()->create('factura-telefono.pdf', 64, 'application/pdf');

        $detected = $service->detectFromUploadedFile($uploadedFile, $team->id);

        $this->assertNull($detected['document_number']);
    }

    public function test_detect_from_uploaded_file_ignores_tax_breakdown_rows_for_single_item_invoice(): void
    {
        $team = Team::factory()->create([
            'name' => 'REVISION ALPHA S.L.',
        ]);
        $team->setSetting('documents_ocr_mode', 'local', ['group' => 'documents']);

        $ocrText = implode("\n", [
            'PROVEEDOR: YOIGO S.A.',
            'Fecha factura: 2026-01-01',
            'Servicio móvil 13,22 21% 0%',
            'Base imponible 13,22',
            'IVA 21% 2,78',
            'TOTAL A PAGAR: 16,00 €',
        ]);

        $ocrService = $this->createMock(DocumentOcrService::class);
        $ocrService->expects($this->once())
            ->method('extractTextFromLocalFile')
            ->willReturn($ocrText);

        $aiOcrService = $this->createMock(DocumentAiOcrService::class);
        $aiOcrService->expects($this->never())
            ->method('extractTextFromLocalFile');

        $service = new ExpenseDocumentDetectionService(
            $ocrService,
            $aiOcrService,
            app(\App\Services\ExpenseSupplierService::class),
        );
        $uploadedFile = UploadedFile::fake()->create('factura-iva-desglose.pdf', 64, 'application/pdf');

        $detected = $service->detectFromUploadedFile($uploadedFile, $team->id);

        $this->assertCount(1, $detected['lines']);
        $this->assertSame('Servicio móvil', $detected['lines'][0]['concept']);
        $this->assertSame(13.22, $detected['lines'][0]['base_amount']);
        $this->assertSame(21.0, $detected['lines'][0]['vat_percent']);
        $this->assertSame(16.0, $detected['payment_amount']);
    }

    public function test_detect_from_uploaded_file_extracts_anthropic_english_invoice_fields(): void
    {
        $team = Team::factory()->create([
            'name' => 'REVISION ALPHA S.L.',
        ]);
        $team->setSetting('documents_ocr_mode', 'local', ['group' => 'documents']);

        Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 2,
            'status_id' => 1,
            'name' => 'Anthropic, PBC',
        ]);

        $currency = Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'status' => true,
        ]);

        $ocrText = implode("\n", [
            'Invoice',
            'Invoice number OWZPCFGE-0030',
            'Date of issue July 29, 2026',
            'Date due July 29, 2026',
            'Anthropic, PBC',
            '548 Market Street',
            'San Francisco, California 94104',
            'United States',
            'support@anthropic.com',
            'Bill to',
            'REVISION ALPHA S.L.',
            'Calle González Besada 39 4º B',
            '33007 Oviedo Asturias',
            'Spain',
            '$200.00 USD due July 29, 2026',
            'API Usage 1 $200.00 $200.00',
            'Subtotal $200.00',
            'Total $200.00',
            'Amount due $200.00',
        ]);

        $ocrService = $this->createMock(DocumentOcrService::class);
        $ocrService->expects($this->once())
            ->method('extractTextFromLocalFile')
            ->willReturn($ocrText);

        $aiOcrService = $this->createMock(DocumentAiOcrService::class);
        $aiOcrService->expects($this->never())
            ->method('extractTextFromLocalFile');

        $service = new ExpenseDocumentDetectionService(
            $ocrService,
            $aiOcrService,
            app(\App\Services\ExpenseSupplierService::class),
        );
        $uploadedFile = UploadedFile::fake()->create('Invoice-OWZPCFGE-0030.pdf', 128, 'application/pdf');

        $detected = $service->detectFromUploadedFile($uploadedFile, $team->id);

        $this->assertSame('OWZPCFGE-0030', $detected['document_number']);
        $this->assertSame('2026-07-29', $detected['date']);
        $this->assertSame('2026-07-29', $detected['due_date']);
        $this->assertSame('USD', $detected['currency_code']);
        $this->assertSame($currency->id, $detected['currency_id']);
        $this->assertSame(200.0, $detected['payment_amount']);
        $this->assertNotEmpty($detected['lines']);
        $this->assertSame(200.0, $detected['lines'][0]['base_amount']);
    }

    public function test_ai_mode_skips_ai_ocr_when_local_pdf_text_is_complete(): void
    {
        $team = Team::factory()->create([
            'name' => 'REVISION ALPHA S.L.',
        ]);
        $team->setSetting('documents_ocr_mode', 'ai', ['group' => 'documents']);

        Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'status' => true,
        ]);

        $ocrText = implode("\n", [
            'Invoice',
            'Invoice number OWZPCFGE-0030',
            'Date of issue July 29, 2026',
            'Date due July 29, 2026',
            'Anthropic, PBC',
            '$200.00 USD due July 29, 2026',
            'API Usage 1 200.00',
            'Total $200.00',
        ]);

        $ocrService = $this->createMock(DocumentOcrService::class);
        $ocrService->expects($this->once())
            ->method('extractTextFromLocalFile')
            ->willReturn($ocrText);

        $aiOcrService = $this->createMock(DocumentAiOcrService::class);
        $aiOcrService->expects($this->never())
            ->method('extractTextFromLocalFile');

        $supplierService = $this->createMock(\App\Services\ExpenseSupplierService::class);
        $supplierService->expects($this->once())
            ->method('resolveForDetectedInvoice')
            ->willReturn([
                'enterprise_id' => null,
                'enterprise_name' => 'Anthropic, PBC',
                'match' => ['status' => 'unmatched', 'source' => null, 'confidence' => 0.0],
                'supplier' => [
                    'legal_name' => 'Anthropic, PBC',
                    'brand_name' => 'Anthropic',
                    'identification_number' => null,
                    'email' => 'support@anthropic.com',
                    'phone' => null,
                    'website' => null,
                    'address' => null,
                    'postal_code' => null,
                    'locality' => null,
                    'province' => null,
                    'country' => 'US',
                ],
            ]);

        $service = new ExpenseDocumentDetectionService(
            $ocrService,
            $aiOcrService,
            $supplierService,
        );

        $detected = $service->detectFromUploadedFile(
            UploadedFile::fake()->create('Invoice-OWZPCFGE-0030.pdf', 128, 'application/pdf'),
            $team->id,
        );

        $this->assertSame('OWZPCFGE-0030', $detected['document_number']);
        $this->assertSame('local', $detected['ocr']['engine_used']);
    }
}
