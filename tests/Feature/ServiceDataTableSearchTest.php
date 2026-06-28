<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceDataTableSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            CurrencySeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->user = User::factory()->withPersonalTeam()->create();
        $team = $this->user->ownedTeams()->first();
        $this->user->forceFill(['current_team_id' => $team->id])->save();
        $this->user->assignRole('admin');
    }

    public function test_service_datatable_loads_rows_without_search(): void
    {
        $team = $this->user->currentTeam;

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme Hosting Client',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $category = $this->createHostingCategory($team->id);

        Service::withoutGlobalScopes()->create([
            'enterprise_id' => $enterprise->id,
            'category_id' => $category->id,
            'operation' => 'sell',
            'description' => 'Visible active service',
            'data' => ['domain' => 'visible.example.test'],
            'currency_id' => 1,
            'price' => 19.99,
            'discount' => 0,
            'frequency' => 12,
            'next_billing' => now()->addMonth(),
            'responsible_id' => $this->user->id,
            'status' => 4,
        ]);

        $response = $this->actingAs($this->user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get($this->serviceDataTableUrl(''));

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, (int) $response->json('recordsTotal'));
        $this->assertNull($response->json('error'));
    }

    public function test_service_datatable_search_does_not_fail_on_virtual_columns(): void
    {
        $team = $this->user->currentTeam;

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme Hosting Client',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $category = $this->createHostingCategory($team->id);

        Service::withoutGlobalScopes()->create([
            'enterprise_id' => $enterprise->id,
            'category_id' => $category->id,
            'operation' => 'sell',
            'description' => 'Primary hosting service',
            'data' => [
                'domain' => 'unique-search-domain.test',
                'serviceName' => 'Managed Hosting',
            ],
            'currency_id' => 1,
            'price' => 49.99,
            'discount' => 0,
            'frequency' => 12,
            'next_billing' => now()->addMonth(),
            'responsible_id' => $this->user->id,
            'status' => 4,
        ]);

        $response = $this->actingAs($this->user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get($this->serviceDataTableUrl('test'));

        $response->assertOk();
        $response->assertJsonStructure([
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data',
        ]);
    }

    public function test_service_datatable_search_matches_description(): void
    {
        $team = $this->user->currentTeam;

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme Hosting Client',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $category = $this->createHostingCategory($team->id);

        $matchingService = Service::withoutGlobalScopes()->create([
            'enterprise_id' => $enterprise->id,
            'category_id' => $category->id,
            'operation' => 'sell',
            'description' => 'Servicio de correo corporativo premium',
            'data' => ['domain' => 'mail.example.test'],
            'currency_id' => 1,
            'price' => 29.99,
            'discount' => 0,
            'frequency' => 12,
            'next_billing' => now()->addMonth(),
            'responsible_id' => $this->user->id,
            'status' => 4,
        ]);

        Service::withoutGlobalScopes()->create([
            'enterprise_id' => $enterprise->id,
            'category_id' => $category->id,
            'operation' => 'sell',
            'description' => 'Otro servicio sin coincidencia',
            'data' => ['domain' => 'other.example.test'],
            'currency_id' => 1,
            'price' => 9.99,
            'discount' => 0,
            'frequency' => 12,
            'next_billing' => now()->addMonth(),
            'responsible_id' => $this->user->id,
            'status' => 4,
        ]);

        $response = $this->actingAs($this->user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get($this->serviceDataTableUrl('correo corporativo'));

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('recordsFiltered'));
        $this->assertSame((string) $matchingService->id, (string) $response->json('data.0.DT_RowId'));
    }

    private function createHostingCategory(int $teamId): Category
    {
        $module = Module::query()->create([
            'name' => 'Services',
            'key' => 'services',
            'icon' => 'ti-briefcase',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        return Category::query()->create([
            'name' => 'Web Hosting',
            'module_id' => $module->id,
            'team_id' => $teamId,
            'description' => 'Hosting plans',
            'status' => 1,
        ]);
    }

    private function serviceDataTableUrl(string $searchValue): string
    {
        $query = $this->serviceDataTableBaseQuery();
        $query['search'] = ['value' => $searchValue, 'regex' => 'false'];

        return route('service-list').'?'.http_build_query($query);
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceDataTableBaseQuery(): array
    {
        $columns = [];
        foreach ($this->serviceDataTableColumnDefinitions() as $definition)
        {
            $columns[] = array_merge($definition, [
                'search' => ['value' => '', 'regex' => 'false'],
            ]);
        }

        return [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 7, 'dir' => 'asc']],
            'columns' => $columns,
            'status_filter' => '4',
        ];
    }

    /**
     * @return array<int, array{data: string, name: string, searchable: string, orderable: string}>
     */
    private function serviceDataTableColumnDefinitions(): array
    {
        return [
            ['data' => 'id', 'name' => 'id', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'metadata_search', 'name' => 'metadata_search', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'description', 'name' => 'description', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'operation_type', 'name' => 'operation_type', 'searchable' => 'false', 'orderable' => 'false'],
            ['data' => 'enterprise_id', 'name' => 'enterprise_id', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'category_id', 'name' => 'category_id', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'calculated_price', 'name' => 'calculated_price', 'searchable' => 'false', 'orderable' => 'false'],
            ['data' => 'next_billing', 'name' => 'next_billing', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'status', 'name' => 'status', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false'],
        ];
    }
}
