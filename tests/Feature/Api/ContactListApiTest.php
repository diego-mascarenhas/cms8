<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactSentimentHistory;
use App\Models\ContactStatus;
use App\Models\Country;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\ContactSentimentSeeder;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactListApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_list_returns_datatable_columns_filters_and_page_size(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed(ContactStatusSeeder::class);
        $this->seed(ContactSentimentSeeder::class);
        $this->seed(LanguageSeeder::class);

        $user = User::factory()->create(['name' => 'Diego Mascarenhas']);
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Country::query()->updateOrCreate(
            ['id' => 32],
            ['name' => 'Argentina', 'code' => 'AR'],
        );
        Country::query()->updateOrCreate(
            ['id' => 724],
            ['name' => 'España', 'code' => 'ES'],
        );

        $module = Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            ['name' => 'Contacts', 'description' => 'Contacts', 'is_core' => 1, 'status' => 1, 'order' => 0],
        );
        $category = Category::query()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Lead web',
            'color' => '#6B8CAE',
            'status' => 1,
            'order' => 0,
        ]);

        $leadId = ContactStatus::query()->where('name', 'Lead')->value('id');
        $contact = Contact::query()->create([
            'team_id' => $team->id,
            'name' => 'Resumir',
            'email' => 'resumir@example.com',
            'language' => 'es',
            'country' => 32,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => $leadId,
        ]);
        $contact->categories()->attach($category->id);
        ContactSentimentHistory::query()->create([
            'contact_id' => $contact->id,
            'sentiment_id' => 5,
            'intent_id' => 1,
            'notes' => 'Seguimiento',
        ]);

        foreach (range(1, 10) as $index)
        {
            Contact::query()->create([
                'team_id' => $team->id,
                'name' => 'Contacto '.$index,
                'language' => 'es',
                'creator_id' => $user->id,
                'status_id' => $leadId,
            ]);
        }

        $token = $user->createToken('contacts')->plainTextToken;

        $page = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/contacts?per_page=10');

        $page->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 11)
            ->assertJsonPath('data.per_page', 10)
            ->assertJsonPath('data.last_page', 2)
            ->assertJsonCount(10, 'data.data');

        $filtered = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/contacts?search=Resumir&sentiment_id=5&category_id='.$category->id.'&country=32');

        $filtered->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.display_name', 'Resumir')
            ->assertJsonPath('data.data.0.intent.emoji', '🛒')
            ->assertJsonPath('data.data.0.intent.name', 'Comprar')
            ->assertJsonPath('data.data.0.sentiment.emoji', '🥳')
            ->assertJsonPath('data.data.0.categories.0.name', 'Lead web')
            ->assertJsonPath('data.data.0.categories.0.color', '#6b8cae')
            ->assertJsonPath('data.data.0.responsible.name', 'Diego Mascarenhas')
            ->assertJsonPath('data.data.0.status.name', 'Lead')
            ->assertJsonPath('data.data.0.enterprise_id', null)
            ->assertJsonPath('data.data.0.user', null)
            ->assertJsonPath('show_list60', false);

        $this->assertStringStartsWith('data:image/svg+xml', (string) $filtered->json('data.data.0.avatar_url'));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/contacts/filters')
            ->assertOk()
            ->assertJsonFragment(['name' => '🥳 Muy Positivo'])
            ->assertJsonFragment(['name' => '🛒 Comprar'])
            ->assertJsonFragment(['name' => 'Lead web', 'color' => '#6b8cae'])
            ->assertJsonFragment(['name' => 'Argentina'])
            ->assertJsonMissing(['name' => 'España'])
            ->assertJsonFragment(['name' => 'Lead'])
            ->assertJsonFragment(['name' => 'Finalizado']);
    }

    public function test_sentiment_filter_uses_only_the_latest_history(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed(ContactStatusSeeder::class);
        $this->seed(ContactSentimentSeeder::class);
        $this->seed(LanguageSeeder::class);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $leadId = ContactStatus::query()->where('name', 'Lead')->value('id');
        $contact = Contact::query()->create([
            'team_id' => $team->id,
            'name' => 'Marini',
            'language' => 'es',
            'creator_id' => $user->id,
            'status_id' => $leadId,
        ]);
        ContactSentimentHistory::query()->create([
            'contact_id' => $contact->id,
            'sentiment_id' => 1,
            'notes' => 'Antes',
        ]);
        ContactSentimentHistory::query()->create([
            'contact_id' => $contact->id,
            'sentiment_id' => 3,
            'notes' => 'Ahora',
        ]);

        $token = $user->createToken('contacts')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/contacts?sentiment_id=1')
            ->assertOk()
            ->assertJsonPath('data.total', 0);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/contacts?sentiment_id=3')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.name', 'Marini')
            ->assertJsonPath('data.data.0.sentiment.emoji', '😐')
            ->assertJsonPath('data.data.0.sentiment.name', 'Neutral');
    }

    public function test_contact_picker_returns_every_contact_without_the_page_cap(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed(ContactStatusSeeder::class);
        $this->seed(LanguageSeeder::class);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $leadId = ContactStatus::query()->where('name', 'Lead')->value('id');

        foreach (range(1, 120) as $index)
        {
            Contact::query()->create([
                'team_id' => $team->id,
                'name' => 'Contacto '.$index,
                'email' => 'contacto'.$index.'@example.com',
                'language' => 'es',
                'creator_id' => $user->id,
                'status_id' => $leadId,
            ]);
        }

        $token = $user->createToken('contacts')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/contacts?per_page=100')
            ->assertOk()
            ->assertJsonPath('data.total', 120)
            ->assertJsonCount(100, 'data.data');

        $picker = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/contacts?picker=1');

        $picker->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(120, 'data')
            ->assertJsonPath('data.0.name', 'Contacto 1');
    }
}
