<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Team;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppInvoiceSheetImportService;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WhatsAppInvoiceSheetImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function attachUserToTeam(User $user, Team $team, string $role = 'editor'): void
    {
        $user->teams()->attach($team->id, ['role' => $role]);
    }

    private function sampleSheet(): string
    {
        return <<<'CSV'
Concepto,Propuesta,Cliente,Importe,IVA,IRPF,Fecha envío,Estado,Nota
Servicio X,Prop A,Acme Cliente,100.00,21,,24/3/2026,Pendiente,
CSV;
    }

    private function seedInvoiceDependencies(): void
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);
    }

    public function test_returns_null_without_invoice_store_prefix(): void
    {
        $this->seedInvoiceDependencies();

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $user = User::factory()->create();
        $this->attachUserToTeam($user, $team);
        $perm = Permission::firstOrCreate(['name' => 'invoice.store', 'guard_name' => 'web']);
        $user->givePermissionTo($perm);

        $this->assertNull(app(WhatsAppInvoiceSheetImportService::class)->tryHandle($this->sampleSheet(), $user, (int) $team->id));
        $this->assertSame(0, Invoice::withoutGlobalScopes()->where('team_id', $team->id)->count());
    }

    public function test_imports_invoice_linked_to_enterprise_when_cliente_matches(): void
    {
        $this->seedInvoiceDependencies();

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme Cliente',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $user = User::factory()->create();
        $this->attachUserToTeam($user, $team);
        $perm = Permission::firstOrCreate(['name' => 'invoice.store', 'guard_name' => 'web']);
        $user->givePermissionTo($perm);

        $body = "invoice.store\n".$this->sampleSheet();
        $reply = app(WhatsAppInvoiceSheetImportService::class)->tryHandle($body, $user, (int) $team->id);

        $this->assertIsString($reply);
        $this->assertStringContainsString('factura', $reply);

        $invoice = Invoice::withoutGlobalScopes()->where('team_id', $team->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame((int) $enterprise->id, (int) $invoice->enterprise_id);
        $this->assertSame(1, (int) $invoice->status);
    }

    public function test_imports_draft_when_cliente_unknown_uses_placeholder_enterprise(): void
    {
        $this->seedInvoiceDependencies();

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $user = User::factory()->create();
        $this->attachUserToTeam($user, $team);
        $perm = Permission::firstOrCreate(['name' => 'invoice.store', 'guard_name' => 'web']);
        $user->givePermissionTo($perm);

        $sheet = <<<'CSV'
Concepto,Propuesta,Cliente,Importe
Línea A,Prop,ZYX No Existe,50.00
CSV;
        $reply = app(WhatsAppInvoiceSheetImportService::class)->tryHandle("invoice.store\n".$sheet, $user, (int) $team->id);

        $this->assertIsString($reply);
        $this->assertStringContainsString('Borrador', $reply);

        $invoice = Invoice::withoutGlobalScopes()->where('team_id', $team->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame(9, (int) $invoice->status);

        $placeholder = Enterprise::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('code', '__SHEET_IMPORT_NO_CLIENT__')
            ->first();
        $this->assertNotNull($placeholder);
        $this->assertSame((int) $placeholder->id, (int) $invoice->enterprise_id);
    }
}
