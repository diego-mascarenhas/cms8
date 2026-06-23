<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ExpenseDocumentDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExpenseDocumentDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_detect_document_returns_detected_payload(): void
    {
        $user = $this->makeAdminUser();

        $this->mock(ExpenseDocumentDetectionService::class, function ($mock): void
        {
            $mock->shouldReceive('detectFromUploadedFile')
                ->once()
                ->andReturn([
                    'enterprise_id' => 88,
                    'enterprise_name' => 'Proveedor Demo',
                    'document_number' => 'FAC-2026-001',
                    'date' => '2026-06-22',
                    'payment_date' => '2026-06-22',
                    'currency_id' => 978,
                    'currency_code' => 'EUR',
                    'payment_amount' => 121.00,
                    'lines' => [
                        [
                            'concept' => 'Servicio mensual',
                            'base_amount' => 100.00,
                            'vat_percent' => 21.00,
                            'retention_percent' => 0.00,
                            'allocation_percent' => 100.00,
                        ],
                    ],
                ]);
        });

        $response = $this->actingAs($user)->post(route('expense.detect-document'), [
            'document_file' => UploadedFile::fake()->create('factura.pdf', 120, 'application/pdf'),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.enterprise_id', 88)
            ->assertJsonPath('data.document_number', 'FAC-2026-001')
            ->assertJsonPath('data.lines.0.concept', 'Servicio mensual');
    }

    public function test_detect_document_validates_required_file(): void
    {
        $user = $this->makeAdminUser();

        $this->actingAs($user)
            ->post(route('expense.detect-document'), [])
            ->assertSessionHasErrors(['document_file']);
    }

    private function makeAdminUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user->fresh();
    }
}
