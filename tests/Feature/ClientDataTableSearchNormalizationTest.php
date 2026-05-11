<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\User;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientDataTableSearchNormalizationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
        ]);

        Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            [
                'name' => 'Contacts',
                'icon' => 'users',
                'description' => 'CRM contacts',
                'status' => 1,
            ],
        );

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->user = User::factory()->withPersonalTeam()->create();
        $team = $this->user->ownedTeams()->first();
        $this->user->forceFill(['current_team_id' => $team->id])->save();
        $this->user->assignRole('admin');
        $team->enableModule('contacts');
    }

    public function test_client_datatable_search_matches_spanish_accents_when_query_has_none(): void
    {
        $team = $this->user->currentTeam;

        $matchingId = DB::table('enterprises')->insertGetId([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 2,
            'name' => 'Gestión Fernández SL',
            'code' => 'GF1',
            'creator_id' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('enterprises')->insert([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 2,
            'name' => 'Other Active Client',
            'code' => 'OAC',
            'creator_id' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get($this->clientDataTableUrl('fernandez'));

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('recordsFiltered'));
        $this->assertSame((string) $matchingId, (string) $response->json('data.0.DT_RowId'));
    }

    private function clientDataTableUrl(string $searchValue): string
    {
        $query = $this->clientDataTableBaseQuery();
        $query['search'] = ['value' => $searchValue, 'regex' => 'false'];

        return route('client-list').'?'.http_build_query($query);
    }

    /**
     * @return array<string, mixed>
     */
    private function clientDataTableBaseQuery(): array
    {
        $columns = [];
        foreach ($this->clientDataTableColumnDefinitions() as $def)
        {
            $columns[] = array_merge($def, [
                'search' => ['value' => '', 'regex' => 'false'],
            ]);
        }

        return [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 1, 'dir' => 'asc']],
            'columns' => $columns,
        ];
    }

    /**
     * @return array<int, array{data: string, name: string, searchable: string, orderable: string}>
     */
    private function clientDataTableColumnDefinitions(): array
    {
        return [
            ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'sources', 'name' => 'sources', 'searchable' => 'false', 'orderable' => 'false'],
            ['data' => 'responsible_name', 'name' => 'responsible_name', 'searchable' => 'false', 'orderable' => 'false'],
            ['data' => 'status_id', 'name' => 'status_id', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false'],
        ];
    }
}
