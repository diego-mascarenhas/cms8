<?php

namespace Tests\Feature;

use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AssistantWhatsAppUsageByLineService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiAssistantUsageTest extends TestCase
{
    use RefreshDatabase;

    private const TEAM_NUMBER = '34999000111';

    public function test_usage_requires_authentication(): void
    {
        $this->getJson('/api/assistant/usage')->assertStatus(401);
    }

    public function test_usage_is_empty_when_the_team_has_no_whatsapp_replies(): void
    {
        [$token] = $this->team();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/usage')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totals.lines', 0)
            ->assertJsonPath('data.totals.total_tokens', 0)
            ->assertJsonPath('data.lines', []);
    }

    public function test_usage_groups_tokens_by_whatsapp_line_and_exposes_the_model(): void
    {
        [$token, $user, $team] = $this->team();

        $this->crmContact($team, $user, [
            'name' => 'Ana',
            'surname' => 'Catalogo',
            'phone' => '34600111222',
        ]);

        $this->outboundUsage('34600111222', 29900, 148, 30048, 'claude-haiku-4-5', 'SM_usage_ana_1');
        $this->outboundUsage('34600111222', 800, 40, 840, 'claude-haiku-4-5', 'SM_usage_ana_2');
        $this->outboundUsage('34600999000', 400, 20, 420, 'claude-sonnet-4-5', 'SM_usage_other_1');

        Conversation::query()->create([
            'message_sid' => 'SM_usage_human',
            'channel' => 'whatsapp',
            'from' => self::TEAM_NUMBER,
            'to' => '34600111222',
            'body' => 'Te lo mando yo',
            'status' => 'sent',
            'direction' => 'outbound',
            'user_id' => $user->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/usage');

        $response->assertOk();
        $response->assertJsonPath('data.totals.lines', 2);
        $response->assertJsonPath('data.totals.replies', 3);
        $response->assertJsonPath('data.totals.total_tokens', 31308);
        $this->assertNotEmpty($response->json('data.period_start'));
        $this->assertNotEmpty($response->json('data.period_end'));
        $this->assertSame('Ana Catalogo', $response->json('data.lines.0.name'));
        $this->assertSame('34600111222', $response->json('data.lines.0.phone'));
        $this->assertSame(30888, $response->json('data.lines.0.total_tokens'));
        $this->assertSame('claude-haiku-4-5', $response->json('data.lines.0.model'));
        $this->assertSame('/inbox?phone=34600111222', $response->json('data.lines.0.inbox_href'));
        $this->assertSame('34600999000', $response->json('data.lines.1.phone'));
        $this->assertSame('claude-sonnet-4-5', $response->json('data.lines.1.model'));
        $this->assertSame('claude-haiku-4-5', $response->json('data.by_model.0.model'));
        $this->assertGreaterThan(0, $response->json('data.lines.0.amount_cents'));
    }

    public function test_usage_costs_and_savings_use_the_marked_up_sell_rate(): void
    {
        config([
            'humano_pricing.token_billing.amount_per_million' => 6,
            'humano_pricing.token_billing.markup_percent' => 50,
        ]);

        [$token] = $this->team();

        $this->outboundUsage(
            '34600111222',
            1_000_000,
            0,
            1_000_000,
            'claude-haiku-4-5',
            'SM_usage_rate_1',
            tokensSaved: 1_000_000,
        );

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/usage');

        $response->assertOk();
        $response->assertJsonPath('data.rate_per_million', 9);
        $response->assertJsonPath('data.totals.amount_cents', 900);
        $response->assertJsonPath('data.totals.saved_cents', 900);
        $response->assertJsonPath('data.totals.tokens_saved', 1_000_000);
        $response->assertJsonPath('data.lines.0.amount_cents', 900);
        $response->assertJsonPath('data.lines.0.saved_cents', 900);
        $response->assertJsonPath('data.by_model.0.saved_cents', 900);
    }

    public function test_usage_excludes_replies_outside_the_current_period(): void
    {
        [$token, , $team] = $this->team();

        $this->outboundUsage('34600111222', 800, 40, 840, 'claude-haiku-4-5', 'SM_usage_in_period');
        $old = $this->outboundUsage('34600111222', 9_000, 100, 9_100, 'claude-haiku-4-5', 'SM_usage_old');
        $old->forceFill(['created_at' => now()->subMonths(2)])->save();

        $from = now()->subDays(7);
        $to = now()->addDay();
        $payload = app(AssistantWhatsAppUsageByLineService::class)
            ->forTeam($team, $from, $to);

        $this->assertSame(840, $payload['totals']['total_tokens']);
        $this->assertSame(1, $payload['totals']['replies']);
        $this->assertSame($from->toIso8601String(), $payload['period_start']);
    }

    public function test_usage_falls_back_to_agent_conversations_when_whatsapp_metadata_is_missing(): void
    {
        [$token, $user, $team] = $this->team();

        $client = User::factory()->create([
            'phone' => '34600888777',
            'name' => 'Cliente WA',
        ]);

        $conversationId = (string) Str::uuid();
        AgentConversation::query()->create([
            'id' => $conversationId,
            'user_id' => $client->id,
            'team_id' => $team->id,
            'title' => 'Filtro de aceite',
        ]);
        AgentConversationMessage::query()->create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversationId,
            'user_id' => $user->id,
            'agent' => 'chat_assistant',
            'role' => 'assistant',
            'content' => 'Tenemos el filtro en stock.',
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [
                'prompt_tokens' => 12000,
                'completion_tokens' => 180,
                'total_tokens' => 12180,
            ],
            'meta' => [
                'model' => 'claude-haiku-4-5',
            ],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/usage')
            ->assertOk()
            ->assertJsonPath('data.totals.lines', 1)
            ->assertJsonPath('data.lines.0.phone', '34600888777')
            ->assertJsonPath('data.lines.0.total_tokens', 12180)
            ->assertJsonPath('data.lines.0.model', 'claude-haiku-4-5');
    }

    public function test_usage_does_not_double_count_agent_rows_when_whatsapp_metadata_exists(): void
    {
        [$token, $user, $team] = $this->team();

        $this->outboundUsage('34600111222', 800, 40, 840, 'claude-haiku-4-5', 'SM_usage_meta_dup');

        $client = User::factory()->create(['phone' => '34600111222']);
        $conversationId = (string) Str::uuid();
        AgentConversation::query()->create([
            'id' => $conversationId,
            'user_id' => $client->id,
            'team_id' => $team->id,
            'title' => 'Duplicado',
        ]);
        AgentConversationMessage::query()->create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversationId,
            'user_id' => $user->id,
            'agent' => 'chat_assistant',
            'role' => 'assistant',
            'content' => 'Dale, te busco el filtro.',
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [
                'prompt_tokens' => 800,
                'completion_tokens' => 40,
                'total_tokens' => 840,
            ],
            'meta' => ['model' => 'claude-haiku-4-5'],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/usage')
            ->assertOk()
            ->assertJsonPath('data.totals.lines', 1)
            ->assertJsonPath('data.totals.total_tokens', 840)
            ->assertJsonPath('data.totals.replies', 1);
    }

    public function test_usage_ignores_another_team_whatsapp_line(): void
    {
        [$token] = $this->team();

        Conversation::query()->create([
            'message_sid' => 'SM_usage_other_team',
            'channel' => 'whatsapp',
            'from' => '34911000000',
            'to' => '34600111222',
            'body' => 'Hola de otro equipo',
            'status' => 'sent',
            'direction' => 'outbound',
            'metadata' => [
                'token_usage' => [
                    'prompt_tokens' => 5000,
                    'completion_tokens' => 50,
                    'total_tokens' => 5050,
                    'model' => 'claude-haiku-4-5',
                ],
            ],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/usage')
            ->assertOk()
            ->assertJsonPath('data.totals.lines', 0);
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

        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->setSetting('whatsapp_from', self::TEAM_NUMBER);

        return [$user->createToken('usage-test')->plainTextToken, $user, $team];
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

    private function outboundUsage(
        string $to,
        int $prompt,
        int $completion,
        int $total,
        string $model,
        string $sid,
        int $tokensSaved = 0,
    ): Conversation {
        $tokenUsage = [
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $total,
            'model' => $model,
        ];
        if ($tokensSaved > 0)
        {
            $tokenUsage['tokens_saved'] = $tokensSaved;
        }

        return Conversation::query()->create([
            'message_sid' => $sid,
            'channel' => 'whatsapp',
            'from' => self::TEAM_NUMBER,
            'to' => $to,
            'body' => 'Respuesta automática',
            'status' => 'sent',
            'direction' => 'outbound',
            'metadata' => [
                'token_usage' => $tokenUsage,
            ],
        ]);
    }
}
