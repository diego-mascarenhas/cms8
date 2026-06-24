<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\List60;
use App\Models\List60Status;
use App\Models\Module;
use App\Models\User;
use App\Support\List60StatusAdvancer;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\List60StatusesSeeder;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class List60StoreTest extends TestCase
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
            EnterpriseTypeSeeder::class,
            List60StatusesSeeder::class,
        ]);

        Module::query()->firstOrCreate(
            ['key' => 'list60'],
            [
                'name' => 'List 60',
                'icon' => 'list',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->user = User::factory()->withPersonalTeam()->create();
        $team = $this->user->ownedTeams()->first();
        $this->user->forceFill(['current_team_id' => $team->id])->save();
        $this->user->assignRole('admin');
        $team->enableModule('list60');
    }

    public function test_store_sets_contact_status_to_en_seguimiento(): void
    {
        $leadStatus = ContactStatus::query()->where('name', 'Lead')->firstOrFail();
        $followingStatus = ContactStatus::query()->where('name', 'En seguimiento')->firstOrFail();

        $contact = Contact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'status_id' => $leadStatus->id,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('list60.store'), [
            'contact_id' => $contact->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', 'Contacto agregado exitosamente a la Lista de 60');

        $this->assertDatabaseHas('list60', [
            'contact_id' => $contact->id,
            'responsible_id' => $this->user->id,
            'status_id' => List60StatusAdvancer::initialStatusId(),
        ]);

        $contact->refresh();
        $this->assertSame($followingStatus->id, $contact->status_id);
    }

    public function test_store_assigns_responsible_categories_and_notes(): void
    {
        $this->seed(ModuleSeeder::class);

        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);

        $collaborator = User::factory()->create();
        $this->user->currentTeam->users()->attach($collaborator, ['role' => 'editor']);
        $collaborator->forceFill(['current_team_id' => $this->user->currentTeam->id])->save();
        $collaborator->assignRole('collaborator');

        $contactsModuleId = Module::query()->where('key', 'contacts')->value('id');
        $category = Category::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'module_id' => $contactsModuleId,
            'name' => 'Prospecto caliente',
        ]);

        $contact = Contact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'data' => ['notes' => 'Nota anterior'],
        ]);

        $response = $this->actingAs($this->user)->postJson(route('list60.store'), [
            'contact_id' => $contact->id,
            'responsible_id' => $collaborator->id,
            'category_ids' => [$category->id],
            'notes' => 'Nota de seguimiento comercial',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('list60', [
            'contact_id' => $contact->id,
            'responsible_id' => $collaborator->id,
            'status_id' => List60StatusAdvancer::initialStatusId(),
        ]);

        $contact->refresh();
        $this->assertTrue($contact->categories->contains('id', $category->id));
        $this->assertSame('Nota de seguimiento comercial', $contact->data->notes);
    }

    public function test_prefill_returns_contact_notes_and_categories(): void
    {
        $this->seed(ModuleSeeder::class);

        $contactsModuleId = Module::query()->where('key', 'contacts')->value('id');
        $category = Category::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'module_id' => $contactsModuleId,
            'name' => 'VIP',
        ]);

        $contact = Contact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'data' => ['notes' => 'Nota existente'],
        ]);
        $contact->categories()->attach($category->id);

        $response = $this->actingAs($this->user)->getJson(route('list60.prefill', $contact));

        $response->assertOk();
        $response->assertJsonPath('name', $contact->name);
        $response->assertJsonPath('notes', 'Nota existente');
        $response->assertJsonPath('category_ids', [$category->id]);
    }

    public function test_update_can_change_responsible_without_date_next(): void
    {
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);

        $collaborator = User::factory()->create();
        $this->user->currentTeam->users()->attach($collaborator, ['role' => 'editor']);
        $collaborator->forceFill(['current_team_id' => $this->user->currentTeam->id])->save();
        $collaborator->assignRole('collaborator');

        $contact = Contact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $list60 = List60::query()->create([
            'contact_id' => $contact->id,
            'type_id' => 1,
            'date_next' => now()->addWeek(),
            'status_id' => 1,
            'responsible_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->putJson(route('list60.update', $list60->id), [
            'responsible_id' => $collaborator->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', 'Asignación actualizada');

        $list60->refresh();
        $this->assertSame($collaborator->id, $list60->responsible_id);
    }

    public function test_datatable_shows_contact_categories_instead_of_type(): void
    {
        $this->seed(ModuleSeeder::class);

        $contactsModuleId = Module::query()->where('key', 'contacts')->value('id');
        $category = Category::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'module_id' => $contactsModuleId,
            'name' => 'Staff VIP',
        ]);

        $contact = Contact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);
        $contact->categories()->attach($category->id);

        $sinContactar = List60Status::query()->where('name', 'Sin contactar')->firstOrFail();

        List60::query()->create([
            'contact_id' => $contact->id,
            'type_id' => 1,
            'date_next' => now()->addWeek(),
            'status_id' => $sinContactar->id,
            'responsible_id' => $this->user->id,
        ]);

        $columnKeys = ['id', 'contact_id', 'list60_status', 'date_next', 'categories', 'responsible_name', 'action'];
        $columns = [];
        foreach ($columnKeys as $data)
        {
            $columns[] = [
                'data' => $data,
                'name' => $data,
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ];
        }

        $response = $this->actingAs($this->user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('list60-list'), [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 3, 'dir' => 'asc']],
            'columns' => $columns,
        ]);

        $response->assertOk();
        $categoriesHtml = data_get($response->json(), 'data.0.categories');
        $this->assertIsString($categoriesHtml);
        $this->assertStringContainsString('Staff VIP', $categoriesHtml);
        $this->assertStringContainsString('badge bg-label-primary', $categoriesHtml);

        $statusHtml = data_get($response->json(), 'data.0.list60_status');
        $this->assertIsString($statusHtml);
        $this->assertStringContainsString('Sin contactar', $statusHtml);
        $this->assertStringContainsString('badge', $statusHtml);
    }
}
