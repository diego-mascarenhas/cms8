<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountManagementDataTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_management_datatable_returns_all_teams_including_those_without_owner(): void
    {
        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);

        $owner = User::factory()->create();
        $root = User::factory()->create();
        $root->assignRole('root');

        Account::query()->create([
            'name' => 'With Owner',
            'user_id' => $owner->id,
            'personal_team' => false,
        ]);

        $orphanOwner = User::factory()->create();
        $orphanTeam = Account::query()->create([
            'name' => 'Orphan Owner',
            'user_id' => $orphanOwner->id,
            'personal_team' => false,
        ]);
        $orphanOwner->delete();
        $orphanTeam->refresh();

        $response = $this->actingAs($root)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('account-management').'?'.http_build_query($this->accountDataTableBaseQuery()));

        $response->assertOk();
        $payload = $response->json();
        $this->assertArrayNotHasKey('error', $payload);
        $this->assertSame(2, (int) $payload['recordsTotal']);
        $this->assertCount(2, $payload['data']);
        $names = collect($payload['data'])->pluck('name')->implode(' ');
        $this->assertStringContainsString('Orphan Owner', $names);
        $this->assertStringContainsString('With Owner', $names);
    }

    /**
     * @return array<string, mixed>
     */
    private function accountDataTableBaseQuery(): array
    {
        $columns = [];
        foreach ($this->accountDataTableColumnDefinitions() as $def)
        {
            $columns[] = array_merge($def, [
                'search' => ['value' => '', 'regex' => 'false'],
            ]);
        }

        return [
            'draw' => 1,
            'start' => 0,
            'length' => 60,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 1, 'dir' => 'asc']],
            'columns' => $columns,
        ];
    }

    /**
     * @return array<int, array{data: string, name: string, searchable: string, orderable: string}>
     */
    private function accountDataTableColumnDefinitions(): array
    {
        return [
            ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'owner_name', 'name' => 'owner_name', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'members_count', 'name' => 'members_count', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'active_clients_count', 'name' => 'active_clients_count', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'total_time', 'name' => 'total_time', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'subscriptions_count', 'name' => 'subscriptions_count', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'created_at', 'name' => 'created_at', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false'],
        ];
    }
}
