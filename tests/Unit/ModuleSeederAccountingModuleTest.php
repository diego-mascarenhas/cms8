<?php

namespace Tests\Unit;

use App\Models\Module;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleSeederAccountingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_module_exposes_stripe_billing_copy(): void
    {
        $this->seed(ModuleSeeder::class);

        $accounting = Module::query()->where('key', 'accounting')->first();

        $this->assertNotNull($accounting);
        $this->assertSame('Stripe billing', $accounting->name);
        $this->assertSame(
            'Stripe invoices, PDF downloads and quarterly CSV exports',
            $accounting->description,
        );
    }
}
