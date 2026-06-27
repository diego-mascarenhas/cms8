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

        $service = new ExpenseDocumentDetectionService($ocrService, $aiOcrService);
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

        $service = new ExpenseDocumentDetectionService($ocrService, $aiOcrService);
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

        $service = new ExpenseDocumentDetectionService($ocrService, $aiOcrService);
        $uploadedFile = UploadedFile::fake()->create('factura-iva-desglose.pdf', 64, 'application/pdf');

        $detected = $service->detectFromUploadedFile($uploadedFile, $team->id);

        $this->assertCount(1, $detected['lines']);
        $this->assertSame('Servicio móvil', $detected['lines'][0]['concept']);
        $this->assertSame(13.22, $detected['lines'][0]['base_amount']);
        $this->assertSame(21.0, $detected['lines'][0]['vat_percent']);
        $this->assertSame(16.0, $detected['payment_amount']);
    }
}
