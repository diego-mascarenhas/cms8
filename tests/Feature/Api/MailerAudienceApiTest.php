<?php

namespace Tests\Feature\Api;

use App\Jobs\DispatchAudienceEmailDomainChecks;
use App\Jobs\ValidateAudienceEmailDomainsJob;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\List60;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\MessageDeliveryLink;
use App\Models\Module;
use App\Models\User;
use App\Services\Mail\EmailDomainDns;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\MessageTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MailerAudienceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string, 3: Category}
     */
    private function adminWithToken(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            EnterpriseTypeSeeder::class,
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $contactsModule = Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            [
                'name' => 'Contacts',
                'icon' => 'users',
                'description' => 'Contacts',
                'is_core' => false,
                'status' => 1,
            ],
        );
        Module::query()->firstOrCreate(
            ['key' => 'mailer'],
            [
                'name' => 'Mailer',
                'icon' => 'mail',
                'description' => 'Mailer',
                'is_core' => false,
                'status' => 1,
            ],
        );
        $team->enableModule('mailer');
        $team->enableModule('contacts');

        $leadStatusId = (int) ContactStatus::query()->where('name', 'Lead')->value('id');
        $category = Category::query()->create([
            'team_id' => $team->id,
            'module_id' => $contactsModule->id,
            'name' => 'Newsletter',
            'color' => '#d4a017',
            'status' => 1,
        ]);

        $sendable = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Lucía',
            'surname' => 'García',
            'email' => 'lucia.garcia@cliente.com',
            'language' => 'es',
            'country' => 724,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => $leadStatusId,
        ]);
        $sendable->categories()->attach($category->id);

        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Demo',
            'surname' => 'Fake',
            'email' => 'mailer-demo-'.$team->id.'-demo@fake.com',
            'language' => 'es',
            'country' => 724,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => $leadStatusId,
        ]);

        return [$user, $team->fresh(), $user->createToken('idoneo-mailer-audience')->plainTextToken, $category];
    }

    public function test_lists_contacts_with_email_and_marks_demo_as_not_sendable(): void
    {
        [, , $token] = $this->adminWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/audience');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('data.0.photo_url', null)
            ->assertJsonPath('data.0.status.label_class', 'bg-label-success')
            ->assertJsonPath('lists.0.color', '#d4a017')
            ->assertJsonPath('status_stats.0.key', 'leads')
            ->assertJsonPath('status_stats.0.count', 2)
            ->assertJsonPath('status_stats.0.percentage', 100)
            ->assertJsonPath('status_stats.0.label_class', 'bg-label-success');

        $rows = collect($response->json('data'));
        $lucia = $rows->firstWhere('email', 'lucia.garcia@cliente.com');
        $this->assertSame('#d4a017', $lucia['categories'][0]['color'] ?? null);
        $this->assertTrue((bool) $lucia['can_send']);
        $fake = $rows->first(fn (array $row): bool => str_ends_with((string) $row['email'], '@fake.com'));
        $this->assertNotNull($fake);
        $this->assertFalse((bool) $fake['can_send']);
    }

    public function test_filters_audience_by_category(): void
    {
        [, , $token, $category] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/audience?category_id='.$category->id)
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.email', 'lucia.garcia@cliente.com');
    }

    public function test_creates_audience_contact(): void
    {
        [, $team, $token, $category] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mailer/audience', [
                'name' => 'Martín',
                'surname' => 'Pérez',
                'email' => 'martin.perez@cliente.com',
                'phone' => '+34600111222',
                'category_ids' => [$category->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'martin.perez@cliente.com')
            ->assertJsonPath('data.phone', '34600111222')
            ->assertJsonPath('data.can_send', true)
            ->assertJsonPath('data.categories.0.name', 'Newsletter');

        $this->assertDatabaseHas('contacts', [
            'team_id' => $team->id,
            'email' => 'martin.perez@cliente.com',
            'name' => 'Martín',
            'phone' => '34600111222',
        ]);
    }

    public function test_updates_audience_contact_and_categories(): void
    {
        [$user, $team, $token, $category] = $this->adminWithToken();
        $leadStatusId = (int) ContactStatus::query()->where('name', 'Lead')->value('id');
        $vip = Category::query()->create([
            'team_id' => $team->id,
            'module_id' => $category->module_id,
            'name' => 'VIP',
            'status' => 1,
        ]);

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'lucia.garcia@cliente.com')
            ->firstOrFail();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/mailer/audience/'.$contact->id, [
                'name' => 'Lucía',
                'surname' => 'García López',
                'email' => 'lucia.garcia@cliente.com',
                'phone' => '+5491112345678',
                'category_ids' => [$category->id, $vip->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.surname', 'García López')
            ->assertJsonPath('data.phone', '5491112345678')
            ->assertJsonCount(2, 'data.categories');

        $this->assertEqualsCanonicalizing(
            ['Newsletter', 'VIP'],
            collect($response->json('data.categories'))->pluck('name')->all(),
        );

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'surname' => 'García López',
            'phone' => '5491112345678',
            'creator_id' => $user->id,
        ]);

        $this->assertSame(
            [$category->id, $vip->id],
            $contact->fresh()->categories()->orderBy('categories.id')->pluck('categories.id')->all(),
        );
        $this->assertSame($leadStatusId, (int) $contact->fresh()->status_id);
    }

    public function test_clearing_the_email_removes_the_contact_from_the_audience(): void
    {
        [, $team, $token] = $this->adminWithToken();

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'lucia.garcia@cliente.com')
            ->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/mailer/audience/'.$contact->id, [
                'name' => 'Lucía',
                'email' => '',
            ])
            ->assertOk()
            ->assertJsonPath('data.email', '')
            ->assertJsonPath('data.can_send', false);

        $this->assertNull($contact->fresh()->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/audience?search='.rawurlencode('lucia.garcia@cliente.com'))
            ->assertOk()
            ->assertJsonPath('pagination.total', 0);
    }

    public function test_can_add_category_when_another_contact_shares_the_email(): void
    {
        [$user, $team, $token, $category] = $this->adminWithToken();
        $leadStatusId = (int) ContactStatus::query()->where('name', 'Lead')->value('id');
        $staff = Category::query()->create([
            'team_id' => $team->id,
            'module_id' => $category->module_id,
            'name' => 'Staff',
            'status' => 1,
        ]);

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'lucia.garcia@cliente.com')
            ->firstOrFail();

        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Lucía',
            'surname' => 'Duplicada',
            'email' => 'Lucia.Garcia@cliente.com',
            'language' => 'es',
            'country' => 724,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => $leadStatusId,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/mailer/audience/'.$contact->id, [
                'name' => 'Lucía',
                'surname' => 'García',
                'email' => 'lucia.garcia@cliente.com',
                'category_ids' => [$category->id, $staff->id],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue($contact->fresh()->categories->contains('id', $staff->id));
    }

    public function test_cannot_update_missing_audience_contact(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/mailer/audience/999999', [
                'name' => 'Nadie',
                'email' => 'nadie@cliente.com',
            ])
            ->assertNotFound();
    }

    public function test_creates_audience_list_and_reuses_existing_name(): void
    {
        [, $team, $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mailer/audience/lists', ['name' => 'Clientes VIP'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Clientes VIP')
            ->assertJsonPath('data.subscribers', 0);

        $this->assertDatabaseHas('categories', [
            'team_id' => $team->id,
            'name' => 'Clientes VIP',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mailer/audience/lists', ['name' => 'clientes vip'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Clientes VIP');

        $this->assertSame(1, Category::query()->where('team_id', $team->id)->where('name', 'Clientes VIP')->count());
    }

    public function test_search_matches_name_without_case_or_accents(): void
    {
        [, , $token] = $this->adminWithToken();

        foreach (['lucia', 'LUCÍA', 'garcia'] as $term)
        {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->getJson('/api/mailer/audience?search='.rawurlencode($term))
                ->assertOk()
                ->assertJsonPath('pagination.total', 1)
                ->assertJsonPath('data.0.email', 'lucia.garcia@cliente.com');
        }
    }

    public function test_paginates_audience_contacts(): void
    {
        [$user, $team, $token] = $this->adminWithToken();
        $leadStatusId = (int) ContactStatus::query()->where('name', 'Lead')->value('id');

        for ($i = 1; $i <= 25; $i++)
        {
            Contact::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'name' => sprintf('Contacto %02d', $i),
                'surname' => 'Extra',
                'email' => "extra{$i}@cliente.com",
                'language' => 'es',
                'country' => 724,
                'creator_id' => $user->id,
                'responsible_id' => $user->id,
                'status_id' => $leadStatusId,
            ]);
        }

        $first = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/audience?per_page=20&page=1')
            ->assertOk()
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.per_page', 20)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('pagination.total', 27);

        $this->assertCount(20, $first->json('data'));

        $second = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/audience?per_page=20&page=2')
            ->assertOk()
            ->assertJsonPath('pagination.current_page', 2);

        $this->assertCount(7, $second->json('data'));
    }

    public function test_adds_an_audience_contact_to_list60(): void
    {
        [$user, $team, $token] = $this->adminWithToken();
        Module::query()->firstOrCreate(
            ['key' => 'list60'],
            [
                'name' => 'Lista 60',
                'icon' => 'list',
                'description' => 'Lista 60',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'lucia.garcia@cliente.com')
            ->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mailer/audience/'.$contact->id.'/list60')
            ->assertForbidden();

        $team->enableModule('list60');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mailer/audience/'.$contact->id.'/list60')
            ->assertCreated()
            ->assertJsonPath('data.in_list60', true)
            ->assertJsonPath('data.already', false);

        $this->assertDatabaseHas('list60', [
            'contact_id' => $contact->id,
            'responsible_id' => $user->id,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mailer/audience/'.$contact->id.'/list60')
            ->assertOk()
            ->assertJsonPath('data.already', true);

        $this->assertSame(1, List60::query()->where('contact_id', $contact->id)->count());
    }

    public function test_list60_note_describes_the_news_open_and_click(): void
    {
        [$user, $team, $token] = $this->adminWithToken();
        Module::query()->firstOrCreate(
            ['key' => 'list60'],
            [
                'name' => 'Lista 60',
                'icon' => 'list',
                'description' => 'Lista 60',
                'is_core' => false,
                'status' => 1,
            ],
        );
        $team->enableModule('list60');

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'lucia.garcia@cliente.com')
            ->firstOrFail();

        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Hemos vuelto',
            'text' => 'Asunto',
            'type_id' => 1,
            'status_id' => 0,
        ]);

        $delivery = MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'status_id' => 1,
            'sent_at' => '2026-10-01 10:00:00',
            'opened_at' => '2026-10-02 11:30:00',
            'clicked_at' => null,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mailer/audience/'.$contact->id.'/list60', [
                'delivery_id' => $delivery->id,
            ])
            ->assertCreated();

        $note = (string) List60::query()->where('contact_id', $contact->id)->value('notes');
        $this->assertStringContainsString('News «Hemos vuelto».', $note);
        $this->assertStringContainsString('Se envió el ', $note);
        $this->assertStringContainsString('Lo abrió el ', $note);
        $this->assertStringContainsString('No hizo clic.', $note);
        $this->assertStringNotContainsString('Hizo clic el ', $note);

        $contact->refresh();
        $this->assertStringContainsString('News «Hemos vuelto».', (string) ($contact->data->notes ?? ''));
        $this->assertSame($user->id, (int) List60::query()->where('contact_id', $contact->id)->value('responsible_id'));
    }

    public function test_list60_note_names_the_clicked_links(): void
    {
        [, $team, $token] = $this->adminWithToken();
        Module::query()->firstOrCreate(
            ['key' => 'list60'],
            [
                'name' => 'Lista 60',
                'icon' => 'list',
                'description' => 'Lista 60',
                'is_core' => false,
                'status' => 1,
            ],
        );
        $team->enableModule('list60');

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'lucia.garcia@cliente.com')
            ->firstOrFail();

        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Hemos vuelto',
            'text' => 'Asunto',
            'type_id' => 1,
            'status_id' => 0,
        ]);

        $delivery = MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'status_id' => 1,
            'sent_at' => '2026-10-01 10:00:00',
            'opened_at' => '2026-10-02 11:30:00',
            'clicked_at' => '2026-10-02 11:45:00',
        ]);

        MessageDeliveryLink::query()->create([
            'message_delivery_id' => $delivery->id,
            'link' => 'https://www.pedimosfacil.com',
            'click_count' => 1,
        ]);
        MessageDeliveryLink::query()->create([
            'message_delivery_id' => $delivery->id,
            'link' => 'https://idoneo.dev',
            'click_count' => 2,
        ]);
        MessageDeliveryLink::query()->create([
            'message_delivery_id' => $delivery->id,
            'link' => 'https://no-se-abrio.example',
            'click_count' => 0,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mailer/audience/'.$contact->id.'/list60', [
                'delivery_id' => $delivery->id,
            ])
            ->assertCreated();

        $note = (string) List60::query()->where('contact_id', $contact->id)->value('notes');
        $this->assertStringContainsString(
            'Hizo clic el ',
            $note,
        );
        $this->assertStringContainsString('en https://www.pedimosfacil.com y https://idoneo.dev.', $note);
        $this->assertStringNotContainsString('no-se-abrio.example', $note);
    }

    public function test_shows_an_audience_contact_for_editing(): void
    {
        [, $team, $token] = $this->adminWithToken();

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'lucia.garcia@cliente.com')
            ->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/audience/'.$contact->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'Lucía')
            ->assertJsonPath('data.email', 'lucia.garcia@cliente.com')
            ->assertJsonPath('data.phone', null)
            ->assertJsonStructure(['lists']);
    }

    public function test_lists_list60_contacts_for_the_mailer(): void
    {
        [$user, $team, $token] = $this->adminWithToken();
        Module::query()->firstOrCreate(
            ['key' => 'list60'],
            [
                'name' => 'Lista 60',
                'icon' => 'list',
                'description' => 'Lista 60',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/list60')
            ->assertOk()
            ->assertJsonPath('meta.enabled', false)
            ->assertJsonPath('data', []);

        $team->enableModule('list60');

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'lucia.garcia@cliente.com')
            ->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mailer/audience/'.$contact->id.'/list60')
            ->assertCreated();

        List60::query()->where('contact_id', $contact->id)->update([
            'notes' => 'News «Volvimos». No lo abrió. No hizo clic.',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/list60')
            ->assertOk()
            ->assertJsonPath('meta.enabled', true)
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.contact.email', 'lucia.garcia@cliente.com')
            ->assertJsonPath('data.0.contact.display_name', 'Lucía García')
            ->assertJsonPath('data.0.notes', 'News «Volvimos». No lo abrió. No hizo clic.')
            ->assertJsonPath('data.0.responsible', $user->name);
    }

    public function test_records_a_contact_interaction_from_the_mailer_list(): void
    {
        [, $team, $token] = $this->adminWithToken();

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'lucia.garcia@cliente.com')
            ->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mailer/audience/'.$contact->id.'/interactions', [
                'type' => 'call',
                'subject' => 'Seguimiento',
                'body' => 'Pidió la propuesta por correo.',
                'occurred_at' => '2026-10-05 19:10',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'call')
            ->assertJsonPath('data.subject', 'Seguimiento')
            ->assertJsonPath('data.occurred_at', '2026-10-05 19:10');

        $this->assertDatabaseHas('contact_interactions', [
            'contact_id' => $contact->id,
            'type' => 'call',
            'subject' => 'Seguimiento',
            'body' => 'Pidió la propuesta por correo.',
        ]);
    }

    public function test_audience_filters_failed_addresses_and_temporary_errors(): void
    {
        [$user, $team, $token] = $this->adminWithToken();

        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Caido',
            'email' => 'nadie@missing.test',
            'creator_id' => $user->id,
            'data' => [
                'channels' => [
                    'email' => [
                        'address' => 'nadie@missing.test',
                        'valid' => false,
                        'domain' => 'missing',
                        'reason' => Contact::EMAIL_DOMAIN_MISSING,
                    ],
                ],
            ],
        ]);
        $legacy = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Viejo',
            'email' => 'viejo@missing.test',
            'creator_id' => $user->id,
            'data' => [
                'channels' => [
                    'email' => [
                        'address' => 'viejo@missing.test',
                        'valid' => false,
                        'reason' => Contact::EMAIL_DOMAIN_MISSING,
                    ],
                ],
            ],
        ]);
        $this->assertFalse($legacy->fresh()->emailDomainOk());

        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Lleno',
            'email' => 'lleno@cliente.com',
            'creator_id' => $user->id,
            'data' => [
                'channels' => [
                    'email' => [
                        'address' => 'lleno@cliente.com',
                        'domain' => 'ok',
                        'last_error' => [
                            'message' => 'mailbox full',
                            'at' => now()->toIso8601String(),
                        ],
                    ],
                ],
            ],
        ]);

        $failed = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/audience?issue=failed')
            ->assertOk();
        $failedEmails = collect($failed->json('data'))->pluck('email');
        $this->assertTrue($failedEmails->contains('nadie@missing.test'));
        $this->assertFalse($failedEmails->contains('lleno@cliente.com'));
        $this->assertFalse($failedEmails->contains('lucia.garcia@cliente.com'));
        $this->assertFalse($failed->json('data.0.email_domain_ok'));

        $errors = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/audience?issue=error')
            ->assertOk();
        $errorEmails = collect($errors->json('data'))->pluck('email');
        $this->assertTrue($errorEmails->contains('lleno@cliente.com'));
        $this->assertFalse($errorEmails->contains('nadie@missing.test'));
        $this->assertTrue($errors->json('data.0.email_domain_ok'));

        $validated = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/audience?issue=validated')
            ->assertOk();
        $validatedEmails = collect($validated->json('data'))->pluck('email');
        $this->assertTrue($validatedEmails->contains('lleno@cliente.com'));
        $this->assertFalse($validatedEmails->contains('lucia.garcia@cliente.com'));
        $this->assertFalse($validatedEmails->contains('nadie@missing.test'));

        $unchecked = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/audience?issue=unchecked')
            ->assertOk();
        $uncheckedEmails = collect($unchecked->json('data'))->pluck('email');
        $this->assertTrue($uncheckedEmails->contains('lucia.garcia@cliente.com'));
        $this->assertFalse($uncheckedEmails->contains('lleno@cliente.com'));
        $this->assertFalse($uncheckedEmails->contains('nadie@missing.test'));
        $this->assertFalse($uncheckedEmails->contains('viejo@missing.test'));
    }

    public function test_guest_cannot_list_audience(): void
    {
        $this->getJson('/api/mailer/audience')->assertUnauthorized();
    }

    public function test_validating_domains_marks_missing_ones_and_leaves_bounces(): void
    {
        [$user, $team] = $this->adminWithToken();
        $this->seed(MessageTypeSeeder::class);

        $this->app->instance(EmailDomainDns::class, new class extends EmailDomainDns
        {
            protected function hasRecord(string $domain, string $type): bool
            {
                return $domain === 'cliente.com';
            }
        });

        $missing = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Sin dominio',
            'email' => 'nadie@missing.test',
            'creator_id' => $user->id,
            'data' => ['notes' => 'Nota previa'],
        ]);
        $present = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Con dominio',
            'email' => 'ana@cliente.com',
            'creator_id' => $user->id,
        ]);
        $bounced = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Rebotado',
            'email' => 'fuera@cliente.com',
            'creator_id' => $user->id,
            'data' => [
                'channels' => [
                    'email' => [
                        'address' => 'fuera@cliente.com',
                        'valid' => false,
                        'reason' => 'user unknown',
                    ],
                ],
            ],
        ]);

        $this->checkAudienceDomains((int) $team->id);

        $missing->refresh();
        $this->assertFalse($missing->storedChannelValid('email'));
        $this->assertFalse($missing->emailDomainOk());
        $this->assertSame(Contact::EMAIL_DOMAIN_MISSING, $missing->data->channels->email->reason);
        $this->assertSame('Nota previa', $missing->data->notes);

        $present->refresh();
        $this->assertNotFalse($present->storedChannelValid('email'));
        $this->assertTrue($present->emailDomainOk());

        $bounced->refresh();
        $this->assertFalse($bounced->storedChannelValid('email'));
        $this->assertSame('user unknown', $bounced->data->channels->email->reason);

        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Dominios',
            'text' => '<p>Hola</p>',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $audienceIds = $message->audienceContactsQuery()->pluck('contacts.id');
        $this->assertFalse($audienceIds->contains($missing->id));
        $this->assertTrue($audienceIds->contains($present->id));
        $this->assertFalse($audienceIds->contains($bounced->id));

        $this->app->instance(EmailDomainDns::class, new class extends EmailDomainDns
        {
            protected function hasRecord(string $domain, string $type): bool
            {
                return in_array($domain, ['cliente.com', 'missing.test'], true);
            }
        });
        $this->checkAudienceDomains((int) $team->id);
        $missing->refresh();
        $this->assertNull($missing->data->channels->email->valid);
        $this->assertTrue($missing->emailDomainOk());
        $this->assertTrue($message->audienceContactsQuery()->pluck('contacts.id')->contains($missing->id));
    }

    public function test_validate_domains_queues_the_check(): void
    {
        [$user, $team, $token] = $this->adminWithToken();
        Bus::fake();

        $dns = new class extends EmailDomainDns
        {
            public array $types = [];

            public array $checked = [];

            public int $teamId = 0;

            protected function hasRecord(string $domain, string $type): bool
            {
                $this->types[] = $type;
                $state = Cache::get(ValidateAudienceEmailDomainsJob::cacheKey($this->teamId));
                $this->checked[] = is_array($state) ? (int) ($state['checked'] ?? 0) : -1;

                return $type === 'MX' && $domain === 'cliente.com';
            }
        };
        $dns->teamId = (int) $team->id;
        $this->app->instance(EmailDomainDns::class, $dns);

        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Ana',
            'email' => 'ana@cliente.com',
            'creator_id' => $user->id,
        ]);
        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Luis',
            'email' => 'luis@otro.test',
            'creator_id' => $user->id,
        ]);
        $total = ValidateAudienceEmailDomainsJob::countContacts((int) $team->id);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mailer/audience/validate-domains')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('running', true)
            ->assertJsonPath('total', $total)
            ->assertJsonPath('checked', 0);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/audience')
            ->assertOk()
            ->assertJsonPath('domain_check.running', true)
            ->assertJsonPath('domain_check.total', $total)
            ->assertJsonPath('domain_check.checked', 0);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mailer/audience/validate-domains')
            ->assertOk()
            ->assertJsonPath('running', true);

        Bus::assertDispatchedTimes(DispatchAudienceEmailDomainChecks::class, 1);
        Bus::assertNotDispatched(ValidateAudienceEmailDomainsJob::class);

        $dispatcher = null;
        Bus::assertDispatched(DispatchAudienceEmailDomainChecks::class, function (DispatchAudienceEmailDomainChecks $dispatched) use (&$dispatcher, $team): bool
        {
            $dispatcher = $dispatched;

            return $dispatched->teamId === (int) $team->id;
        });

        $dispatcher->handle();

        $jobs = [];
        Bus::assertDispatched(ValidateAudienceEmailDomainsJob::class, function (ValidateAudienceEmailDomainsJob $dispatched) use (&$jobs, $team): bool
        {
            $jobs[] = $dispatched;

            return $dispatched->teamId === (int) $team->id && $dispatched->contactId > 0;
        });
        $this->assertCount($total, $jobs);

        foreach ($jobs as $job)
        {
            $job->handle(app(EmailDomainDns::class));
        }

        $this->assertNotEmpty($dns->checked);
        $this->assertSame(0, $dns->checked[0]);
        $this->assertLessThan($total, max($dns->checked));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/audience')
            ->assertOk()
            ->assertJsonPath('domain_check.running', false)
            ->assertJsonPath('domain_check.checked', 0);

        $this->assertNotEmpty($dns->types);
        $this->assertSame(['MX'], array_values(array_unique($dns->types)));
    }

    public function test_a_retry_keeps_the_count_and_skips_domains_already_checked(): void
    {
        [$user, $team] = $this->adminWithToken();
        $startedAt = now()->subMinute()->toIso8601String();
        $total = ValidateAudienceEmailDomainsJob::countContacts((int) $team->id) + 2;
        ValidateAudienceEmailDomainsJob::markRunning((int) $team->id, 'resume', $total);

        $done = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Ya',
            'email' => 'ya@dominio-ya.test',
            'creator_id' => $user->id,
        ]);
        $done->applyEmailDomainCheck(true);

        $pending = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Falta',
            'email' => 'falta@dominio-falta.test',
            'creator_id' => $user->id,
        ]);

        $dns = new class extends EmailDomainDns
        {
            public array $domains = [];

            public int $teamId = 0;

            protected function hasRecord(string $domain, string $type): bool
            {
                $this->domains[] = $domain;

                return true;
            }
        };
        $dns->teamId = (int) $team->id;

        $doneJob = new ValidateAudienceEmailDomainsJob((int) $team->id, 'resume', $startedAt, (int) $done->id);
        $doneJob->handle($dns);
        $this->assertSame([], $dns->domains);
        $this->assertSame(1, ValidateAudienceEmailDomainsJob::progress((int) $team->id)['checked']);

        $pendingJob = new ValidateAudienceEmailDomainsJob((int) $team->id, 'resume', $startedAt, (int) $pending->id);
        $pendingJob->handle($dns);
        $this->assertSame(['dominio-falta.test'], $dns->domains);
        $this->assertSame(2, ValidateAudienceEmailDomainsJob::progress((int) $team->id)['checked']);

        $dns->domains = [];
        $pendingJob->handle($dns);
        $this->assertSame([], $dns->domains);
        $this->assertSame(2, ValidateAudienceEmailDomainsJob::progress((int) $team->id)['checked']);
    }

    public function test_progress_closes_when_the_saved_checks_already_cover_the_total(): void
    {
        [$user, $team] = $this->adminWithToken();
        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Listo',
            'email' => 'listo@dominio-listo.test',
            'creator_id' => $user->id,
        ]);
        ValidateAudienceEmailDomainsJob::markRunning((int) $team->id, 'stuck', 1);
        $contact->applyEmailDomainCheck(true);

        $progress = ValidateAudienceEmailDomainsJob::progress((int) $team->id);

        $this->assertFalse($progress['running']);
        $this->assertFalse(ValidateAudienceEmailDomainsJob::isRunning((int) $team->id));
    }

    private function checkAudienceDomains(int $teamId): void
    {
        $dns = app(EmailDomainDns::class);
        Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id')
            ->pluck('id')
            ->each(function ($id) use ($teamId, $dns): void
            {
                (new ValidateAudienceEmailDomainsJob($teamId, '', '', (int) $id))->handle($dns);
            });
    }
}
