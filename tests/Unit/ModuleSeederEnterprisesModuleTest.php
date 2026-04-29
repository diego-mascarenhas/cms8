<?php

namespace Tests\Unit;

use App\Models\Module;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleSeederEnterprisesModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_enterprises_module_belongs_to_billing_group(): void
    {
        $this->seed(ModuleSeeder::class);

        $enterprises = Module::query()->where('key', 'enterprises')->first();

        $this->assertNotNull($enterprises);
        $this->assertSame('billing', $enterprises->group);
    }
}
