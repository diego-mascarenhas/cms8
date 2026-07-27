<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\EnterpriseBillingAddress;
use App\Models\Team;
use App\Services\ExpenseSupplierService;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTaxStatusTypeSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseSupplierServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            EnterpriseTaxStatusTypeSeeder::class,
        ]);
    }

    public function test_match_enterprise_by_tax_id(): void
    {
        $team = Team::factory()->create();
        $supplier = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 2,
            'status_id' => 1,
            'name' => 'YOIGO S.A.',
        ]);

        EnterpriseBillingAddress::query()->create([
            'enterprise_id' => $supplier->id,
            'name' => 'YOIGO S.A.',
            'tax_status_type_id' => 1,
            'identification_number' => 'A-12345678',
            'status' => 1,
        ]);

        $service = app(ExpenseSupplierService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('matchEnterprise');
        $method->setAccessible(true);
        $match = $method->invoke($service, [
            'legal_name' => 'YOIGO S.A.',
            'brand_name' => null,
            'identification_number' => 'A12345678',
        ], $team->id);

        $this->assertSame('matched', $match['status']);
        $this->assertSame('tax_id', $match['source']);
        $this->assertSame($supplier->id, $match['enterprise_id']);
    }

    public function test_create_supplier_persists_fiscal_data(): void
    {
        $team = Team::factory()->create();
        $service = app(ExpenseSupplierService::class);

        $enterprise = $service->createSupplier($team->id, [
            'name' => 'Proveedor OCR',
            'identification_number' => 'B12345678',
            'email' => 'facturas@proveedor.test',
            'phone' => '600111222',
            'address' => 'Calle Mayor 1',
            'postal_code' => '28001',
            'locality' => 'Madrid',
            'province' => 'Madrid',
            'country' => 'ES',
        ]);

        $this->assertSame(2, $enterprise->type_id);
        $this->assertSame(2, (int) $enterprise->status_id);
        $this->assertSame('Proveedor OCR', $enterprise->name);

        $billing = EnterpriseBillingAddress::query()->where('enterprise_id', $enterprise->id)->first();
        $this->assertNotNull($billing);
        $this->assertSame('B12345678', $billing->identification_number);
    }

    public function test_extract_supplier_tax_id_excludes_buyer_and_own_company(): void
    {
        $team = Team::factory()->create([
            'name' => 'REVISION ALPHA S.L.',
        ]);

        $ownEnterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'REVISION ALPHA S.L.',
        ]);

        EnterpriseBillingAddress::query()->create([
            'enterprise_id' => $ownEnterprise->id,
            'name' => 'REVISION ALPHA S.L.',
            'tax_status_type_id' => 1,
            'identification_number' => 'B99999999',
            'status' => 1,
        ]);

        $ocrText = implode("\n", [
            'PROVEEDOR: YOIGO S.A.',
            'CIF: A12345678',
            'CLIENTE: REVISION ALPHA S.L.',
            'NIF: B99999999',
        ]);

        $service = app(ExpenseSupplierService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractSupplierFromOcrText');
        $method->setAccessible(true);
        $supplier = $method->invoke($service, $ocrText, $team->id);

        $this->assertSame('A12345678', $supplier['identification_number']);
    }

    public function test_sanitize_detected_supplier_removes_own_business_contact_data(): void
    {
        $team = Team::factory()->create([
            'name' => 'REVISION ALPHA S.L.',
        ]);
        $team->setSetting('business_config', [
            'business_name' => 'REVISION ALPHA S.L.',
            'business_email' => 'hola@revisionalpha.com',
            'business_phone' => '+34 600 111 222',
            'business_website' => 'https://www.revisionalpha.com',
            'business_location' => 'Calle Propia 10 Madrid',
            'business_postal_code' => '28001',
        ], ['type' => 'json', 'group' => 'business-config']);

        $service = app(ExpenseSupplierService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('sanitizeSupplierAgainstOwnBusiness');
        $method->setAccessible(true);

        $supplier = $method->invoke($service, [
            'brand_name' => 'REVISION ALPHA S.L.',
            'legal_name' => 'REVISION ALPHA S.L.',
            'identification_number' => 'B99999999',
            'email' => 'hola@revisionalpha.com',
            'phone' => '600111222',
            'website' => 'https://www.revisionalpha.com',
            'address' => 'Calle Propia 10 Madrid',
            'postal_code' => '28001',
            'locality' => null,
            'province' => null,
            'country' => 'ES',
        ], '', $team->id);

        $this->assertNull($supplier['legal_name']);
        $this->assertNull($supplier['brand_name']);
        $this->assertNull($supplier['email']);
        $this->assertNull($supplier['phone']);
        $this->assertNull($supplier['website']);
        $this->assertNull($supplier['address']);
        $this->assertNull($supplier['postal_code']);
    }

    public function test_sanitize_detected_supplier_removes_invalid_and_buyer_phones(): void
    {
        $team = Team::factory()->create([
            'name' => 'REVISION ALPHA S.L.',
        ]);
        $team->setSetting('business_config', [
            'business_phone' => '+34 600 111 222',
        ], ['type' => 'json', 'group' => 'business-config']);

        $ocrText = implode("\n", [
            'PROVEEDOR: YOIGO S.A.',
            'Teléfono: 912 345 678',
            'CLIENTE: REVISION ALPHA S.L.',
            'Teléfono: 600 111 222',
        ]);

        $service = app(ExpenseSupplierService::class);
        $reflection = new \ReflectionClass($service);
        $sanitize = $reflection->getMethod('sanitizeSupplierAgainstOwnBusiness');
        $sanitize->setAccessible(true);

        $supplier = $sanitize->invoke($service, [
            'brand_name' => null,
            'legal_name' => 'YOIGO S.A.',
            'identification_number' => 'A12345678',
            'email' => null,
            'phone' => '600111222',
            'website' => null,
            'address' => null,
            'postal_code' => '28001',
            'locality' => null,
            'province' => null,
            'country' => 'ES',
        ], $ocrText, $team->id);

        $this->assertNull($supplier['phone']);
        $this->assertSame('28001', $supplier['postal_code']);

        $supplierWithInvalidPhone = $sanitize->invoke($service, [
            'brand_name' => null,
            'legal_name' => 'YOIGO S.A.',
            'identification_number' => null,
            'email' => null,
            'phone' => 'FACT-2024-001',
            'website' => null,
            'address' => null,
            'postal_code' => null,
            'locality' => null,
            'province' => null,
            'country' => 'ES',
        ], '', $team->id);

        $this->assertNull($supplierWithInvalidPhone['phone']);

        $extractPhone = $reflection->getMethod('extractSupplierPhoneFromText');
        $extractPhone->setAccessible(true);
        $extractedPhone = $extractPhone->invoke($service, $ocrText, $team->id);

        $this->assertSame('912 345 678', $extractedPhone);
    }
}
