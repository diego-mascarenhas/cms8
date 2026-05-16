<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactDataTableSearchNormalizationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
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

    public function test_contact_datatable_search_matches_spanish_accents_when_query_has_none(): void
    {
        $team = $this->user->currentTeam;

        $matching = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Ana',
            'surname' => 'López Fernández',
            'email' => 'ana.lopez@example.test',
            'profile' => '',
            'responsible_id' => $this->user->id,
            'creator_id' => $this->user->id,
        ]);

        Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Other',
            'surname' => 'Person',
            'email' => 'other@example.test',
            'profile' => '',
            'responsible_id' => $this->user->id,
            'creator_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get($this->contactDataTableUrl('fernandez'));

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('recordsFiltered'));
        $this->assertSame((string) $matching->id, (string) $response->json('data.0.DT_RowId'));
    }

    public function test_contact_datatable_name_column_links_to_show_when_user_can_view(): void
    {
        $team = $this->user->currentTeam;

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Ana',
            'surname' => 'López Fernández',
            'email' => 'ana.lopez-link@example.test',
            'profile' => '',
            'responsible_id' => $this->user->id,
            'creator_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('contact-list').'?'.http_build_query($this->contactDataTableBaseQuery()));

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('DT_RowId', (string) $contact->id);
        $this->assertNotNull($row);
        $this->assertStringContainsString(route('contact.show', $contact->id), $row['name']);
        $this->assertStringContainsString('<a href', $row['name']);
    }

    public function test_contact_datatable_search_matches_linked_enterprise_name_without_accents(): void
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
        ]);

        $team = $this->user->currentTeam;

        $enterpriseId = DB::table('enterprises')->insertGetId([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Distribución Ibérica SA',
            'code' => 'DIB',
            'creator_id' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $matching = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Carlos',
            'surname' => 'Ruiz',
            'email' => 'carlos@example.test',
            'profile' => '',
            'responsible_id' => $this->user->id,
            'creator_id' => $this->user->id,
        ]);
        $matching->enterprises()->attach($enterpriseId);

        Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Unrelated',
            'surname' => 'Contact',
            'email' => 'unrelated@example.test',
            'profile' => '',
            'responsible_id' => $this->user->id,
            'creator_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get($this->contactDataTableUrl('distribucion iberica'));

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('recordsFiltered'));
        $this->assertSame((string) $matching->id, (string) $response->json('data.0.DT_RowId'));
    }

    private function contactDataTableUrl(string $searchValue): string
    {
        $query = $this->contactDataTableBaseQuery();
        $query['search'] = ['value' => $searchValue, 'regex' => 'false'];

        return route('contact-list').'?'.http_build_query($query);
    }

    /**
     * @return array<string, mixed>
     */
    private function contactDataTableBaseQuery(): array
    {
        $columns = [];
        foreach ($this->contactDataTableColumnDefinitions() as $def)
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
    private function contactDataTableColumnDefinitions(): array
    {
        return [
            ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'current_sentiment', 'name' => 'current_sentiment', 'searchable' => 'true', 'orderable' => 'false'],
            ['data' => 'sources', 'name' => 'sources', 'searchable' => 'false', 'orderable' => 'false'],
            ['data' => 'responsible_name', 'name' => 'responsible_name', 'searchable' => 'false', 'orderable' => 'false'],
            ['data' => 'categories', 'name' => 'categories', 'searchable' => 'true', 'orderable' => 'false'],
            ['data' => 'status_id', 'name' => 'status_id', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false'],
        ];
    }
}
