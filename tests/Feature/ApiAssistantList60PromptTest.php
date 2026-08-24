<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\List60;
use App\Models\List60Status;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\User;
use App\Models\WhatsAppChatArchive;
use App\Services\ChatAssistantReplyService;
use App\Services\DefaultAssistantFlowPromptsService;
use App\Services\List60InboxReviewService;
use App\Support\List60OutreachPromptDefaults;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\List60StatusesSeeder;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiAssistantList60PromptTest extends TestCase
{
    use RefreshDatabase;

    private const TEAM_NUMBER = '34999000111';

    private const OPEN_PHONE = '34600111222';

    private const ARCHIVED_PHONE = '34600333444';

    private const LIST60_PHONE = '34600555666';

    public function test_list60_prompt_requires_authentication(): void
    {
        $this->getJson('/api/assistant/list60-prompt')->assertStatus(401);
        $this->postJson('/api/assistant/list60-prompt/review')->assertStatus(401);
        $this->postJson('/api/assistant/list60-prompt/suggest')->assertStatus(401);
    }

    public function test_owner_can_read_and_update_the_alta_prompt(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();

        $show = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/list60-prompt')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.key', List60InboxReviewService::ROUTING_KEY_ALTA)
            ->assertJsonPath('data.available', true);

        $this->assertStringContainsString('Lista 60: alta desde el inbox', (string) $show->json('data.prompt_instruction'));
        $this->assertSame(
            List60OutreachPromptDefaults::altaInstruction(),
            $show->json('data.default_instruction'),
        );

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/assistant/list60-prompt', [
                'prompt_instruction' => 'PROMPT ALTA PERSONALIZADO',
            ])
            ->assertOk()
            ->assertJsonPath('data.prompt_instruction', 'PROMPT ALTA PERSONALIZADO');

        $prompt = Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'alta')
            ->first();
        $this->assertNotNull($prompt);
        $this->assertSame('PROMPT ALTA PERSONALIZADO', $prompt->prompt_instruction);
    }

    public function test_review_classifies_open_archived_and_list60_threads_and_suggests_a_reply(): void
    {
        [$user, $team, $token] = $this->assistantUserWithToken();
        DefaultAssistantFlowPromptsService::syncForTeam((int) $team->id);

        $this->crmContact($team, $user, [
            'name' => 'Ana',
            'surname' => 'Abierta',
            'phone' => self::OPEN_PHONE,
        ]);
        $archivedContact = $this->crmContact($team, $user, [
            'name' => 'Bruno',
            'surname' => 'Archivado',
            'phone' => self::ARCHIVED_PHONE,
        ]);
        $listedContact = $this->crmContact($team, $user, [
            'name' => 'Carla',
            'surname' => 'Lista',
            'phone' => self::LIST60_PHONE,
        ]);

        $sinContactar = List60Status::query()->where('name', 'Sin contactar')->firstOrFail();
        List60::query()->create([
            'contact_id' => $listedContact->id,
            'type_id' => 1,
            'date_next' => now()->addWeek()->toDateString(),
            'status_id' => $sinContactar->id,
            'responsible_id' => $user->id,
        ]);

        $this->whatsappInbound(self::OPEN_PHONE, '¿Me lo pensáis y me decís?', now()->subHours(3));
        $this->whatsappInbound(self::ARCHIVED_PHONE, 'Lo miro la semana que viene', now()->subHours(2));
        $this->whatsappInbound(self::LIST60_PHONE, 'Ok, hablamos', now()->subHour());

        WhatsAppChatArchive::factory()->create([
            'team_id' => $team->id,
            'phone' => self::ARCHIVED_PHONE,
        ]);

        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldReceive('getReply')
                ->once()
                ->andReturn([
                    'success' => true,
                    'text' => json_encode([
                        'items' => [
                            [
                                'phone' => self::OPEN_PHONE,
                                'action' => 'list60',
                                'reason' => 'Quedó en seguimiento, no hay pregunta abierta.',
                                'suggested_message' => 'Ana, ¿lo viste? Te dejo una pregunta fácil.',
                            ],
                            [
                                'phone' => self::ARCHIVED_PHONE,
                                'action' => 'list60',
                                'reason' => 'Archivado, pero el hilo pide un toque futuro.',
                                'suggested_message' => 'Bruno, te escribo para retomar lo de la semana pasada.',
                            ],
                            [
                                'phone' => self::LIST60_PHONE,
                                'action' => 'already_on_list',
                                'reason' => 'Ya está en Lista 60.',
                                'suggested_message' => 'Carla, ¿seguimos?',
                            ],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                ]);
        });

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/list60-prompt/review');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.reviewed', 3);

        $items = collect($response->json('data.items'))->keyBy('phone');

        $this->assertSame('list60', $items[self::OPEN_PHONE]['action']);
        $this->assertSame('Ana Abierta', $items[self::OPEN_PHONE]['name']);
        $this->assertFalse($items[self::OPEN_PHONE]['is_archived']);
        $this->assertFalse($items[self::OPEN_PHONE]['on_list60']);
        $this->assertSame('Ana, ¿lo viste? Te dejo una pregunta fácil.', $items[self::OPEN_PHONE]['suggested_message']);
        $this->assertSame(
            '/inbox?phone='.rawurlencode(self::OPEN_PHONE).'&draft='.rawurlencode('Ana, ¿lo viste? Te dejo una pregunta fácil.'),
            $items[self::OPEN_PHONE]['inbox_href'],
        );
        $this->assertSame($user->id, $items[self::OPEN_PHONE]['responsible_id']);
        $this->assertNull($items[self::OPEN_PHONE]['suggested_responsible_id']);
        $this->assertFalse($items[self::OPEN_PHONE]['needs_advisor']);

        $this->assertSame('list60', $items[self::ARCHIVED_PHONE]['action']);
        $this->assertTrue($items[self::ARCHIVED_PHONE]['is_archived']);
        $this->assertSame($archivedContact->id, $items[self::ARCHIVED_PHONE]['contact_id']);
        $this->assertNotSame('', $items[self::ARCHIVED_PHONE]['suggested_message']);

        $this->assertSame('already_on_list', $items[self::LIST60_PHONE]['action']);
        $this->assertTrue($items[self::LIST60_PHONE]['on_list60']);
        $this->assertSame('Sin contactar', $items[self::LIST60_PHONE]['list60_status']);
    }

    public function test_review_suggests_the_teammate_whose_reply_the_client_followed(): void
    {
        [$user, $team, $token] = $this->assistantUserWithToken();
        DefaultAssistantFlowPromptsService::syncForTeam((int) $team->id);

        $advisor = User::factory()->create(['name' => 'Lucía Respuesta']);
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);
        $advisor->assignRole('collaborator');
        $advisor->teams()->attach($team->id, ['role' => 'collaborator']);

        $this->crmContact($team, $user, [
            'name' => 'Diego',
            'surname' => 'SinAsesor',
            'phone' => self::OPEN_PHONE,
            'responsible_id' => null,
        ]);

        $this->whatsappInbound(self::OPEN_PHONE, '¿Cuál es el precio?', now()->subHours(4));
        $this->whatsappOutbound($advisor, self::OPEN_PHONE, 'Son 90 € al mes, sin permanencia.', now()->subHours(3));
        $this->whatsappInbound(self::OPEN_PHONE, 'Ok, lo miro y te digo', now()->subHour());

        $this->mock(ChatAssistantReplyService::class, function ($mock) use ($advisor): void
        {
            $mock->shouldReceive('getReply')
                ->once()
                ->andReturn([
                    'success' => true,
                    'text' => json_encode([
                        'items' => [
                            [
                                'phone' => self::OPEN_PHONE,
                                'action' => 'list60',
                                'reason' => 'Ya tiene precio y quedó en seguimiento.',
                                'suggested_message' => 'Diego, ¿lo viste?',
                                'suggested_responsible_id' => $advisor->id,
                            ],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                ]);
        });

        $items = collect(
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/api/assistant/list60-prompt/review')
                ->assertOk()
                ->json('data.items'),
        )->keyBy('phone');

        $this->assertTrue($items[self::OPEN_PHONE]['needs_advisor']);
        $this->assertNull($items[self::OPEN_PHONE]['responsible_id']);
        $this->assertSame($advisor->id, $items[self::OPEN_PHONE]['suggested_responsible_id']);
        $this->assertSame('Lucía Respuesta', $items[self::OPEN_PHONE]['suggested_responsible_name']);
    }

    public function test_review_falls_back_to_the_human_whose_reply_got_an_answer(): void
    {
        [$user, $team, $token] = $this->assistantUserWithToken();
        DefaultAssistantFlowPromptsService::syncForTeam((int) $team->id);

        $advisor = User::factory()->create(['name' => 'Pedro Acierto']);
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);
        $advisor->assignRole('collaborator');
        $advisor->teams()->attach($team->id, ['role' => 'collaborator']);

        $this->crmContact($team, $user, [
            'name' => 'Eva',
            'surname' => 'Libre',
            'phone' => self::OPEN_PHONE,
            'responsible_id' => null,
        ]);

        $this->whatsappInbound(self::OPEN_PHONE, '¿Tienen hueco el martes?', now()->subHours(5));
        $this->whatsappOutbound($advisor, self::OPEN_PHONE, 'Sí, a las 10.', now()->subHours(4));
        $this->whatsappInbound(self::OPEN_PHONE, 'Perfecto, lo dejo así', now()->subHours(2));

        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldReceive('getReply')
                ->once()
                ->andReturn([
                    'success' => true,
                    'text' => json_encode([
                        'items' => [
                            [
                                'phone' => self::OPEN_PHONE,
                                'action' => 'list60',
                                'reason' => 'Cita resuelta, toca seguimiento.',
                                'suggested_message' => 'Eva, ¿te viene bien?',
                            ],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                ]);
        });

        $items = collect(
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/api/assistant/list60-prompt/review')
                ->assertOk()
                ->json('data.items'),
        )->keyBy('phone');

        $this->assertSame($advisor->id, $items[self::OPEN_PHONE]['suggested_responsible_id']);
        $this->assertSame('Pedro Acierto', $items[self::OPEN_PHONE]['suggested_responsible_name']);
    }

    public function test_suggest_fills_a_whatsapp_draft_for_a_list60_phone(): void
    {
        [$user, $team, $token] = $this->assistantUserWithToken();
        DefaultAssistantFlowPromptsService::syncForTeam((int) $team->id);

        $contact = $this->crmContact($team, $user, [
            'name' => 'Pepe',
            'phone' => self::LIST60_PHONE,
            'data' => ['notes' => 'Todavía no lo llamamos'],
        ]);
        $sinContactar = List60Status::query()->where('name', 'Sin contactar')->firstOrFail();
        List60::query()->create([
            'contact_id' => $contact->id,
            'type_id' => 1,
            'date_next' => now()->subDay()->toDateString(),
            'status_id' => $sinContactar->id,
            'responsible_id' => $user->id,
        ]);

        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldReceive('getReply')
                ->once()
                ->andReturn([
                    'success' => true,
                    'text' => '{"message":"Hola Pepe, te escribo para presentarnos."}',
                ]);
        });

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/list60-prompt/suggest', [
                'phone' => self::LIST60_PHONE,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message', 'Hola Pepe, te escribo para presentarnos.');
    }

    public function test_suggest_returns_not_found_when_the_phone_is_not_on_list60(): void
    {
        [$user, $team, $token] = $this->assistantUserWithToken();
        $this->crmContact($team, $user, [
            'name' => 'Fuera',
            'phone' => self::OPEN_PHONE,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/list60-prompt/suggest', [
                'phone' => self::OPEN_PHONE,
            ])
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string}
     */
    private function assistantUserWithToken(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $this->seed([
            ModuleSeeder::class,
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            EnterpriseTypeSeeder::class,
            List60StatusesSeeder::class,
        ]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $this->assertNotNull($team);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->enableModule('list60');
        $team->setSetting('whatsapp_from', self::TEAM_NUMBER);

        $this->assertNotNull(Module::query()->where('key', 'list60')->first());

        return [$user, $team, $user->createToken('list60-prompt-test')->plainTextToken];
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

    private function whatsappOutbound(User $agent, string $to, string $body, $at): Conversation
    {
        $message = Conversation::query()->create([
            'channel' => 'whatsapp',
            'message_sid' => 'SM_list60_out_'.$agent->id.'_'.$at->timestamp,
            'from' => self::TEAM_NUMBER,
            'to' => $to,
            'body' => $body,
            'status' => 'sent',
            'direction' => 'outbound',
            'user_id' => $agent->id,
        ]);

        $message->forceFill([
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();

        return $message;
    }

    private function whatsappInbound(string $from, string $body, $at): Conversation
    {
        $message = Conversation::query()->create([
            'channel' => 'whatsapp',
            'message_sid' => 'SM_list60_'.str_replace(' ', '_', $from).'_'.$at->timestamp,
            'from' => $from,
            'to' => self::TEAM_NUMBER,
            'body' => $body,
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        $message->forceFill([
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();

        return $message;
    }
}
