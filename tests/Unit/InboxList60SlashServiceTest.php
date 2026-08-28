<?php

namespace Tests\Unit;

use App\Enums\ProductCatalogStatus;
use App\Models\Automation;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\List60;
use App\Models\Module;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Services\InboxList60SlashService;
use App\Support\List60StatusAdvancer;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\List60StatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxList60SlashServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private Contact $contact;

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

        $this->user = User::factory()->withPersonalTeam()->create();
        $this->team = $this->user->ownedTeams()->first();
        $this->user->forceFill(['current_team_id' => $this->team->id])->save();
        $this->team->enableModule('list60');

        $this->contact = Contact::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Diego Lista',
            'creator_id' => $this->user->id,
            'responsible_id' => $this->user->id,
            'status_id' => 1,
            'data' => ['notes' => 'Nota previa'],
        ]);

        $this->actingAs($this->user);
    }

    public function test_enrolls_contact_for_assistant(): void
    {
        $resolved = app(InboxList60SlashService::class)->enroll(
            $this->team,
            'assistant',
            $this->contact,
            $this->user,
        );

        $this->assertTrue($resolved['ok']);
        $this->assertTrue($resolved['silent']);
        $this->assertSame([], $resolved['messages']);
        $this->assertStringContainsString('Assistant', $resolved['notice']);

        $this->assertDatabaseHas('list60', [
            'contact_id' => $this->contact->id,
            'responsible_id' => $this->user->id,
            'status_id' => List60StatusAdvancer::initialStatusId(),
        ]);

        $this->contact->refresh();
        $this->assertStringContainsString('Inbox /list: Assistant', (string) $this->contact->data->notes);
        $this->assertStringContainsString('Nota previa', (string) $this->contact->data->notes);

        $following = ContactStatus::query()->where('name', 'En seguimiento')->first();
        $this->assertNotNull($following);
        $this->assertSame($following->id, $this->contact->status_id);
    }

    public function test_enrolls_contact_for_shop_product_by_code_or_name(): void
    {
        Product::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Remera básica',
            'code' => 'REM-001',
            'catalog_status' => ProductCatalogStatus::Publish,
        ]);

        $byCode = app(InboxList60SlashService::class)->enroll(
            $this->team,
            'REM-001',
            $this->contact,
            $this->user,
        );
        $this->assertTrue($byCode['ok']);
        $this->assertStringContainsString('Remera básica', $byCode['notice']);

        $other = Contact::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Ana Shop',
            'creator_id' => $this->user->id,
            'responsible_id' => $this->user->id,
            'status_id' => 1,
        ]);

        $byName = app(InboxList60SlashService::class)->enroll(
            $this->team,
            'Remera básica',
            $other,
            $this->user,
        );
        $this->assertTrue($byName['ok']);
        $this->assertStringContainsString('REM-001', $byName['notice']);
    }

    public function test_enrolls_contact_for_a_funnel(): void
    {
        Automation::factory()->funnel()->create([
            'team_id' => $this->team->id,
            'name' => 'Catálogo WhatsApp',
            'slug' => 'catalogo-whatsapp',
        ]);

        $resolved = app(InboxList60SlashService::class)->enroll(
            $this->team,
            'catalogo-whatsapp',
            $this->contact,
            $this->user,
        );

        $this->assertTrue($resolved['ok']);
        $this->assertStringContainsString('Catálogo WhatsApp', $resolved['notice']);
        $this->contact->refresh();
        $this->assertStringContainsString('Embudo «Catálogo WhatsApp»', (string) $this->contact->data->notes);
    }

    public function test_appends_note_when_contact_is_already_on_the_list(): void
    {
        app(InboxList60SlashService::class)->enroll($this->team, 'assistant', $this->contact, $this->user);
        $firstId = List60::query()->where('contact_id', $this->contact->id)->value('id');

        $again = app(InboxList60SlashService::class)->enroll($this->team, 'shop', $this->contact, $this->user);

        $this->assertTrue($again['ok']);
        $this->assertStringContainsString('ya estaba', $again['notice']);
        $this->assertSame(1, List60::query()->where('contact_id', $this->contact->id)->count());
        $this->assertSame($firstId, List60::query()->where('contact_id', $this->contact->id)->value('id'));

        $this->contact->refresh();
        $this->assertStringContainsString('Assistant', (string) $this->contact->data->notes);
        $this->assertStringContainsString('Shop', (string) $this->contact->data->notes);
    }

    public function test_rejects_empty_topic_and_saves_free_text_as_note(): void
    {
        $empty = app(InboxList60SlashService::class)->enroll($this->team, null, $this->contact, $this->user);
        $this->assertFalse($empty['ok']);
        $this->assertStringContainsString('/list', $empty['error']);

        $note = app(InboxList60SlashService::class)->enroll(
            $this->team,
            'hola le interesa shop',
            $this->contact,
            $this->user,
        );
        $this->assertTrue($note['ok']);
        $this->assertTrue($note['silent']);
        $this->assertStringContainsString('hola le interesa shop', $note['notice']);
        $this->assertStringContainsString('lista de seguimiento', mb_strtolower((string) $note['notice']));

        $this->contact->refresh();
        $this->assertStringContainsString('Inbox /list: hola le interesa shop', (string) $this->contact->data->notes);
    }

    public function test_rejects_when_there_is_no_contact(): void
    {
        $resolved = app(InboxList60SlashService::class)->enroll($this->team, 'assistant', null, $this->user);

        $this->assertFalse($resolved['ok']);
        $this->assertStringContainsString('contacto', $resolved['error']);
    }
}
