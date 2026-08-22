<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CollaboratorContactListVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ContactStatusSeeder::class, CountrySeeder::class, LanguageSeeder::class]);

        foreach (['admin', 'collaborator'] as $roleName)
        {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            [
                'name' => 'Contacts',
                'icon' => 'users',
                'description' => 'CRM contacts',
                'status' => 1,
            ],
        );
    }

    public function test_collaborator_sees_all_team_contacts_in_list(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $admin->assignRole('admin');
        $team = $admin->ownedTeams()->first();
        $team->enableModule('contacts');
        $admin->forceFill(['current_team_id' => $team->id])->save();

        $collaborator = User::factory()->create();
        $collaborator->assignRole('collaborator');
        $team->users()->attach($collaborator->id, ['role' => 'collaborator']);
        $collaborator->forceFill(['current_team_id' => $team->id])->save();

        $assigned = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Assigned',
            'creator_id' => $admin->id,
            'responsible_id' => $collaborator->id,
            'status_id' => 1,
        ]);

        $other = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Other',
            'creator_id' => $admin->id,
            'responsible_id' => $admin->id,
            'status_id' => 1,
        ]);

        $response = $this->actingAs($collaborator)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('contact-list').'?'.http_build_query($this->contactDataTableBaseQuery()));

        $response->assertOk();
        $this->assertSame(2, (int) $response->json('recordsFiltered'));

        $rowIds = collect($response->json('data'))->pluck('DT_RowId')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($assigned->id, $rowIds);
        $this->assertContains($other->id, $rowIds);

        $this->actingAs($collaborator)
            ->get(route('contact.show', $other->id))
            ->assertOk();

        $this->assertTrue($collaborator->can('view', $other));
    }

    /**
     * @return array<string, mixed>
     */
    private function contactDataTableBaseQuery(): array
    {
        $columns = [];
        foreach ([
            ['data' => 'id', 'name' => 'id'],
            ['data' => 'name', 'name' => 'name'],
            ['data' => 'current_sentiment', 'name' => 'current_sentiment'],
            ['data' => 'current_intent', 'name' => 'current_intent'],
            ['data' => 'sources', 'name' => 'sources'],
            ['data' => 'responsible_name', 'name' => 'responsible_name'],
            ['data' => 'categories', 'name' => 'categories'],
            ['data' => 'status_id', 'name' => 'status_id'],
            ['data' => 'action', 'name' => 'action'],
        ] as $def)
        {
            $columns[] = array_merge($def, [
                'searchable' => 'true',
                'orderable' => 'true',
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
}
