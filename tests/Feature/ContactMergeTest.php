<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Enterprise;
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

class ContactMergeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_merge_keeps_each_enterprise_role_and_archives_the_duplicate(): void
    {
        [$user, $team] = $this->adminTeam();
        $doa = $this->enterprise($team->id, 'DOA');
        $estudio = $this->enterprise($team->id, 'Estudio Doa');

        $keeper = $this->contact($team->id, $user->id, 'Ignacio', 'Escobar', [
            'email' => 'ignacio@doa.test',
        ]);
        $duplicate = $this->contact($team->id, $user->id, 'Ignacio', 'E', [
            'email' => null,
            'phone' => '5493413661548',
        ]);

        $keeper->enterprises()->attach($doa->id, ['position' => 'Director']);
        $duplicate->enterprises()->attach($doa->id, ['position' => 'Asistente']);
        $duplicate->enterprises()->attach($estudio->id, ['position' => 'Contador']);

        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Legacy',
            'team_id' => $team->id,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('contact_category')->insert([
            'contact_id' => $duplicate->id,
            'category_id' => $categoryId,
        ]);

        DB::table('contact_interactions')->insert([
            'contact_id' => $duplicate->id,
            'type' => 'note',
            'subject' => 'Llamada',
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('contact.show', $keeper->id))
            ->assertOk()
            ->assertSee('Fusionar')
            ->assertSee("\$('#current-enterprise-selector')", false)
            ->assertSee('Confirmá la fusión', false)
            ->assertSee('Valores fusionados', false);

        $preview = $this->actingAs($user)
            ->getJson(route('contact.merge-preview', $keeper->id).'?contact_id='.$duplicate->id);

        $preview->assertOk();
        $preview->assertJsonPath('blocked', false);
        $lines = implode(' ', $preview->json('lines'));
        $this->assertStringContainsString('Ya está en DOA como Director', $lines);
        $this->assertStringContainsString('Pasa a Estudio Doa como Contador', $lines);
        $this->assertStringContainsString('Apellido: se conserva Escobar. El otro tiene E.', $lines);
        $this->assertStringContainsString('Teléfono: se completa con 5493413661548.', $lines);
        $this->assertStringContainsString('Categoría: pasa Legacy.', $lines);

        $this->actingAs($user)
            ->postJson(route('contact.merge', $keeper->id), [
                'contact_id' => $duplicate->id,
            ])
            ->assertOk()
            ->assertJsonPath('redirect', route('contact.show', $keeper->id));

        $keeper->refresh();
        $links = DB::table('contact_enterprise')
            ->where('contact_id', $keeper->id)
            ->orderBy('enterprise_id')
            ->get();

        $this->assertCount(2, $links);
        $this->assertSame('Director', $links->firstWhere('enterprise_id', $doa->id)->position);
        $this->assertSame('Contador', $links->firstWhere('enterprise_id', $estudio->id)->position);
        $this->assertSame('5493413661548', (string) $keeper->phone);
        $this->assertSame('ignacio@doa.test', $keeper->email);
        $this->assertSoftDeleted('contacts', ['id' => $duplicate->id]);
        $this->assertSame($keeper->id, (int) data_get($duplicate->fresh()->data, 'merged_into_contact_id'));
        $this->assertSame($keeper->id, (int) DB::table('contact_interactions')->value('contact_id'));
        $this->assertSame($keeper->id, (int) DB::table('contact_category')->where('category_id', $categoryId)->value('contact_id'));

        $this->actingAs($user)
            ->get(route('contact.show', $duplicate->id))
            ->assertOk()
            ->assertSee('Este contacto está archivado.')
            ->assertSee('Ignacio Escobar');
    }

    public function test_merge_fills_a_blank_role_at_the_same_enterprise(): void
    {
        [$user, $team] = $this->adminTeam();
        $doa = $this->enterprise($team->id, 'DOA');
        $keeper = $this->contact($team->id, $user->id, 'Ignacio', 'Escobar');
        $duplicate = $this->contact($team->id, $user->id, 'Nacho', 'Escobar');
        $keeper->enterprises()->attach($doa->id, ['position' => null]);
        $duplicate->enterprises()->attach($doa->id, ['position' => 'Director']);

        $this->actingAs($user)
            ->postJson(route('contact.merge', $keeper->id), [
                'contact_id' => $duplicate->id,
            ])
            ->assertOk();

        $this->assertSame('Director', DB::table('contact_enterprise')->where('contact_id', $keeper->id)->value('position'));
        $this->assertSame(1, DB::table('contact_enterprise')->where('contact_id', $keeper->id)->count());
    }

    public function test_merge_refuses_two_different_linked_users(): void
    {
        [$user, $team] = $this->adminTeam();
        $otherUser = User::factory()->create();
        $keeper = $this->contact($team->id, $user->id, 'Ignacio', 'Escobar', ['user_id' => $user->id]);
        $duplicate = $this->contact($team->id, $user->id, 'Ignacio', 'Otro', ['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)
            ->postJson(route('contact.merge', $keeper->id), [
                'contact_id' => $duplicate->id,
            ]);

        $response->assertStatus(422);
        $message = (string) $response->json('message');
        $this->assertStringContainsString($user->email, $message);
        $this->assertStringContainsString($otherUser->email, $message);
        $this->assertStringContainsString('dejaría uno de los dos sin ficha', $message);

        $this->assertNull($keeper->fresh()->deleted_at);
        $this->assertNull($duplicate->fresh()->deleted_at);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team}
     */
    private function adminTeam(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return [$user, $team];
    }

    private function enterprise(int $teamId, string $name): Enterprise
    {
        return Enterprise::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'name' => $name,
            'type_id' => 1,
            'status_id' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function contact(int $teamId, int $userId, string $name, string $surname, array $extra = []): Contact
    {
        return Contact::factory()->create(array_merge([
            'team_id' => $teamId,
            'name' => $name,
            'surname' => $surname,
            'email' => strtolower($name.'.'.$surname).'@example.test',
            'phone' => null,
            'birthday' => null,
            'profile' => '',
            'responsible_id' => $userId,
            'creator_id' => $userId,
        ], $extra));
    }
}
