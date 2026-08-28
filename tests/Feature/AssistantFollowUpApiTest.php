<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Conversation;
use App\Models\List60;
use App\Models\List60Status;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\List60StatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\AnonymousAgent;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantFollowUpApiTest extends TestCase
{
    use RefreshDatabase;

    private const TEAM_NUMBER = '34999000111';

    private const CLIENT_PHONE = '34600111888';

    public function test_can_change_list60_responsible(): void
    {
        [$token, $user, $team] = $this->team();
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);

        $advisor = User::factory()->create(['name' => 'Ana Asesora']);
        $advisor->assignRole('collaborator');
        $advisor->teams()->attach($team->id, ['role' => 'collaborator']);

        $contact = $this->crmContact($team, $user, [
            'name' => 'Diego',
            'phone' => '34600000088',
        ]);

        $sinContactar = List60Status::query()->where('name', 'Sin contactar')->firstOrFail();
        $record = List60::query()->create([
            'contact_id' => $contact->id,
            'type_id' => 1,
            'date_next' => now()->toDateString(),
            'status_id' => $sinContactar->id,
            'responsible_id' => $user->id,
            'notes' => 'Inbox /list: Assistant — abordar más tarde',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/assistant/list60/'.$record->id.'/responsible', [
                'responsible_id' => $advisor->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('responsible_id', $advisor->id)
            ->assertJsonPath('responsible_name', 'Ana Asesora');

        $this->assertSame($advisor->id, (int) $record->fresh()->responsible_id);
    }

    public function test_finish_hides_contact_from_waiting_list(): void
    {
        [$token, $user, $team] = $this->team();

        $contact = $this->crmContact($team, $user, [
            'name' => 'Leticia',
            'surname' => null,
            'phone' => self::CLIENT_PHONE,
        ]);

        $this->whatsappMessage([
            'message_sid' => 'SM_wait_1',
            'from' => self::CLIENT_PHONE,
            'to' => self::TEAM_NUMBER,
            'body' => '¿Cuánta gente lo puede usar?',
            'status' => 'received',
            'direction' => 'inbound',
            'at' => now()->subHours(2),
        ]);

        $waiting = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/commercial-stats')
            ->assertOk()
            ->json('data.inbox_waiting');

        $this->assertTrue(collect($waiting)->contains('contact_id', $contact->id));

        $lost = ContactStatus::query()->where('name', 'Perdido')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/follow-up/finish', [
                'contact_id' => $contact->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('contact_id', $contact->id)
            ->assertJsonPath('status_id', $lost->id);

        $this->assertSame($lost->id, (int) $contact->fresh()->status_id);

        $after = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/commercial-stats')
            ->assertOk()
            ->json('data.inbox_waiting');

        $this->assertFalse(collect($after)->contains('contact_id', $contact->id));
    }

    public function test_summarize_writes_digest_and_returns_summary(): void
    {
        [$token, $user, $team] = $this->team();

        $contact = $this->crmContact($team, $user, [
            'name' => 'Leticia',
            'surname' => null,
            'phone' => self::CLIENT_PHONE,
        ]);

        $this->whatsappMessage([
            'message_sid' => 'SM_sum_1',
            'from' => self::CLIENT_PHONE,
            'to' => self::TEAM_NUMBER,
            'body' => 'Holaaaa',
            'status' => 'received',
            'direction' => 'inbound',
            'at' => now()->subHours(3),
        ]);
        $this->whatsappMessage([
            'message_sid' => 'SM_sum_2',
            'from' => self::CLIENT_PHONE,
            'to' => self::TEAM_NUMBER,
            'body' => 'Pero cuál es su precio??',
            'status' => 'received',
            'direction' => 'inbound',
            'at' => now()->subHours(2),
        ]);

        AnonymousAgent::fake([
            '{"summary":"Pidió precio.\\nPreguntó por usuarios.\\nResponder hoy.","intent_key":"buy"}',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/follow-up/summarize', [
                'contact_id' => $contact->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('contact_id', $contact->id)
            ->assertJsonPath('summary', "Pidió precio.\nPreguntó por usuarios.\nResponder hoy.")
            ->assertJsonPath('intent.key', 'buy')
            ->assertJsonPath('intent.name', 'Comprar');

        $digest = $contact->fresh()->data->inbox_digest;
        $this->assertSame("Pidió precio.\nPreguntó por usuarios.\nResponder hoy.", $digest->summary);
        $this->assertSame('buy', $digest->intent_key);
        $this->assertSame(now()->toDateString(), $digest->date);
    }

    public function test_summarize_requires_messages(): void
    {
        [$token, $user, $team] = $this->team();

        $contact = $this->crmContact($team, $user, [
            'name' => 'Sin hilo',
            'phone' => '34600999111',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/follow-up/summarize', [
                'contact_id' => $contact->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /**
     * @return array{0: string, 1: User, 2: \App\Models\Team}
     */
    private function team(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
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

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->enableModule('list60');
        $team->setSetting('whatsapp_from', self::TEAM_NUMBER);

        return [$user->createToken('follow-up-test')->plainTextToken, $user, $team];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function whatsappMessage(array $payload): Conversation
    {
        $at = $payload['at'];
        unset($payload['at']);

        $message = Conversation::query()->create(array_merge([
            'channel' => 'whatsapp',
        ], $payload));

        $message->forceFill([
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();

        return $message;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function crmContact(\App\Models\Team $team, User $user, array $overrides = []): Contact
    {
        return Contact::withoutGlobalScopes()->create(array_merge([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'name' => 'Contacto',
            'surname' => 'Prueba',
            'phone' => '34600999000',
            'status_id' => 1,
        ], $overrides));
    }
}
