<?php

namespace Tests\Unit;

use App\Models\Module;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleSeederContactsModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_contacts_module_icon_matches_vertical_menu(): void
    {
        $this->seed(ModuleSeeder::class);

        $contacts = Module::query()->where('key', 'contacts')->first();

        $this->assertNotNull($contacts);
        $this->assertSame('users', $contacts->icon);
    }
}
