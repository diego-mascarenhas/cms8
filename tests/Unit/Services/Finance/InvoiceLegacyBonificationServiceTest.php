<?php

namespace Tests\Unit\Services\Finance;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Finance\InvoiceLegacyBonificationService;
use App\Support\InvoiceLegacyBonificationLogWriter;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InvoiceLegacyBonificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceLegacyBonificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);

        $this->service = app(InvoiceLegacyBonificationService::class);
    }

    public function test_run_correction_bonifies_all_pending_legacy_manual_invoices(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $pending = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0001-00000541',
            'date' => '2008-06-05',
            'due_date' => '2008-06-05',
            'gross_amount' => 94.5,
            'discount' => 0,
            'total_amount' => 94.5,
            'balance' => 0.5,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        $stripe = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'STR-001',
            'date' => '2008-06-05',
            'due_date' => '2008-06-05',
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 2,
            'source_provider' => 'stripe',
        ]);

        $inconsistent = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'BON-OLD',
            'date' => '2012-01-01',
            'due_date' => '2012-01-01',
            'gross_amount' => 90,
            'discount' => 0,
            'total_amount' => 90,
            'balance' => 90,
            'status' => 5,
            'source_provider' => 'manual',
        ]);

        $report = $this->service->runCorrection(dryRun: false);

        $this->assertFalse($report['dry_run']);
        $this->assertSame(1, $report['summary']['bonified']['matched']);
        $this->assertSame(1, $report['summary']['bonified']['updated']);
        $this->assertSame(1, $report['summary']['balance_zeroed']['matched']);
        $this->assertSame(1, $report['summary']['balance_zeroed']['updated']);

        $this->assertSame([
            [
                'id' => $pending->id,
                'number' => '0001-00000541',
                'date' => '2008-06-05',
                'team_id' => $team->id,
                'enterprise_id' => $enterprise->id,
                'source_provider' => 'manual',
                'previous_status' => 2,
                'previous_balance' => 0.5,
                'new_status' => 5,
                'new_balance' => 0.0,
            ],
        ], $report['bonified_invoices']);

        $pending->refresh();
        $stripe->refresh();
        $inconsistent->refresh();

        $this->assertSame(5, (int) $pending->status);
        $this->assertSame(0.0, (float) $pending->balance);
        $this->assertSame(2, (int) $stripe->status);
        $this->assertSame(100.0, (float) $stripe->balance);
        $this->assertSame(5, (int) $inconsistent->status);
        $this->assertSame(0.0, (float) $inconsistent->balance);
    }

    public function test_dry_run_does_not_update_invoices(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0001-00000322',
            'date' => '2006-11-14',
            'due_date' => '2006-11-14',
            'gross_amount' => 140,
            'discount' => 0,
            'total_amount' => 140,
            'balance' => 46.38,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        $report = $this->service->runCorrection(dryRun: true);

        $this->assertTrue($report['dry_run']);
        $this->assertSame(1, $report['summary']['bonified']['matched']);
        $this->assertSame(0, $report['summary']['bonified']['updated']);

        $invoice->refresh();

        $this->assertSame(2, (int) $invoice->status);
        $this->assertSame(46.38, (float) $invoice->balance);
    }

    public function test_log_writer_persists_correction_payload(): void
    {
        $path = storage_path('logs/invoice-corrections/test-legacy-bonification.json');

        if (File::exists($path))
        {
            File::delete($path);
        }

        $payload = [
            'executed_at' => now()->toIso8601String(),
            'dry_run' => true,
            'summary' => [
                'bonified' => ['matched' => 1, 'updated' => 0],
                'balance_zeroed' => ['matched' => 0, 'updated' => 0],
            ],
            'bonified_invoices' => [],
            'balance_zeroed_invoices' => [],
        ];

        $writtenPath = app(InvoiceLegacyBonificationLogWriter::class)->write($payload, $path);

        $this->assertSame($path, $writtenPath);
        $this->assertFileExists($path);

        $decoded = json_decode(File::get($path), true);
        $this->assertSame(true, $decoded['dry_run']);

        File::delete($path);
    }
}
