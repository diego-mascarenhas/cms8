<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\AssistantToolAuthorizationService;
use App\Services\AssistantToolsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantToolExposureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function definedNames(): array
    {
        return array_values(array_map(
            fn (array $def) => (string) $def['name'],
            app(AssistantToolsService::class)->getDefinitions(),
        ));
    }

    public function test_whatsapp_catalog_exposes_only_sales_tools(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->assignRole($role);

        $names = app(AssistantToolAuthorizationService::class)->exposedToolNames(
            $this->definedNames(),
            $user,
            (int) $team->id,
            true,
            'products:assistant_catalogo',
            false,
        );

        $this->assertEqualsCanonicalizing(
            ['list_product_catalog', 'search_products', 'add_to_whatsapp_cart', 'view_whatsapp_cart', 'get_store_info', 'confirm_whatsapp_order', 'search_contacts', 'get_contact_detail', 'create_contact', 'update_contact'],
            $names,
        );
        $this->assertNotContains('get_account_report', $names);
        $this->assertNotContains('create_message', $names);
    }

    public function test_whatsapp_customer_thread_without_flow_omits_staff_crm(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->assignRole($role);

        $names = app(AssistantToolAuthorizationService::class)->exposedToolNames(
            $this->definedNames(),
            $user,
            (int) $team->id,
            true,
            null,
            false,
        );

        $this->assertContains('list_product_catalog', $names);
        $this->assertContains('create_calendar_event', $names);
        $this->assertNotContains('get_account_report', $names);
        $this->assertNotContains('create_message', $names);
        $this->assertLessThan(count($this->definedNames()), count($names));
    }
}
