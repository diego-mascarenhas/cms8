<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactSentimentHistory;
use App\Models\Conversation;
use App\Models\List60;
use App\Models\List60Status;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\ContactSentimentSeeder;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\List60StatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantCommercialStatsApiTest extends TestCase
{
    use RefreshDatabase;

    private const TEAM_NUMBER = '34999000111';

    private const CLIENT_PHONE = '34600111222';

    public function test_commercial_stats_require_authentication(): void
    {
        $this->getJson('/api/assistant/commercial-stats')->assertStatus(401);
    }

    public function test_commercial_stats_return_pipeline_sentiment_ai_and_list60(): void
    {
        [$token, $user, $team] = $this->team();

        $lead = $this->crmContact($team, $user, [
            'name' => 'Lead',
            'surname' => 'Nuevo',
            'phone' => '34600000001',
            'status_id' => 1,
        ]);
        $this->crmContact($team, $user, [
            'name' => 'Seguimiento',
            'surname' => 'Activo',
            'phone' => '34600000002',
            'status_id' => 2,
        ]);
        $this->crmContact($team, $user, [
            'name' => 'Cierre',
            'surname' => 'IA',
            'phone' => '34600000003',
            'status_id' => 3,
            'data' => (object) ['chat_assistant_prompt_key' => 'chat:citas_y_ventas'],
        ]);
        $this->crmContact($team, $user, [
            'name' => 'Cliente',
            'surname' => 'IA',
            'phone' => '34600000004',
            'status_id' => 5,
            'data' => (object) ['chat_assistant_prompt_key' => 'chat:citas_y_ventas'],
        ]);

        ContactSentimentHistory::query()->create([
            'contact_id' => $lead->id,
            'sentiment_id' => 4,
            'notes' => 'Bien',
        ]);

        $sinRespuesta = List60Status::query()->where('name', 'Sin respuesta')->firstOrFail();
        List60::query()->create([
            'contact_id' => $lead->id,
            'type_id' => 1,
            'date_next' => now()->subDays(2)->toDateString(),
            'status_id' => $sinRespuesta->id,
            'responsible_id' => $user->id,
            'notes' => 'Esperando respuesta',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/commercial-stats');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.kpis.leads', 1);
        $response->assertJsonPath('data.kpis.follow_up', 1);
        $response->assertJsonPath('data.kpis.conversions', 1);
        $response->assertJsonPath('data.kpis.clients', 1);
        $response->assertJsonPath('data.kpis.list60_due', 1);

        $pipeline = collect($response->json('data.pipeline'))->keyBy('label');
        $this->assertSame(1, $pipeline['Lead']['count']);
        $this->assertSame(1, $pipeline['En seguimiento']['count']);

        $positive = collect($response->json('data.sentiment'))->firstWhere('id', 4);
        $this->assertSame(1, $positive['count']);
        $this->assertSame('🙂', $positive['emoji']);

        $ai = collect($response->json('data.ai_conversions'))->firstWhere('key', 'chat:citas_y_ventas');
        $this->assertNotNull($ai);
        $this->assertSame(2, $ai['conversions']);
        $this->assertSame(1, $ai['clients']);

        $resume = $response->json('data.list60_resume.0');
        $this->assertSame($lead->id, $resume['contact_id']);
        $this->assertSame('Sin respuesta', $resume['status']);
        $this->assertStringContainsString('sin respuesta', mb_strtolower($resume['suggestion']));
    }

    public function test_commercial_stats_measure_agent_response_and_waiting_inbox(): void
    {
        [$token, $user, $team] = $this->team();
        $this->crmContact($team, $user, [
            'name' => 'Ana',
            'surname' => 'Pérez',
            'phone' => self::CLIENT_PHONE,
        ]);

        $this->whatsappMessage([
            'message_sid' => 'SM_in_1',
            'from' => self::CLIENT_PHONE,
            'to' => self::TEAM_NUMBER,
            'body' => 'Hola, ¿tienen stock?',
            'status' => 'read',
            'direction' => 'inbound',
            'at' => now()->subMinutes(20),
        ]);
        $this->whatsappMessage([
            'message_sid' => 'SM_out_1',
            'from' => self::TEAM_NUMBER,
            'to' => self::CLIENT_PHONE,
            'body' => 'Sí, te confirmo',
            'status' => 'sent',
            'direction' => 'outbound',
            'user_id' => $user->id,
            'at' => now()->subMinutes(15),
        ]);
        $this->whatsappMessage([
            'message_sid' => 'SM_in_2',
            'from' => self::CLIENT_PHONE,
            'to' => self::TEAM_NUMBER,
            'body' => '¿Y el precio?',
            'status' => 'received',
            'direction' => 'inbound',
            'at' => now()->subMinutes(8),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/commercial-stats');

        $response->assertOk();
        $response->assertJsonPath('data.kpis.unread_inbound', 1);
        $response->assertJsonPath('data.kpis.waiting_replies', 1);

        $agent = collect($response->json('data.agent_response'))->firstWhere('key', 'user:'.$user->id);
        $this->assertNotNull($agent);
        $this->assertSame(1, $agent['replies']);
        $this->assertGreaterThanOrEqual(240, $agent['avg_seconds']);
        $this->assertLessThanOrEqual(360, $agent['avg_seconds']);

        $waiting = $response->json('data.inbox_waiting.0');
        $this->assertSame(self::CLIENT_PHONE, $waiting['phone']);
        $this->assertSame('Ana Pérez', $waiting['name']);
        $this->assertTrue($waiting['unread']);
        $this->assertSame('/inbox?phone='.self::CLIENT_PHONE, $waiting['inbox_href']);
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
            ContactSentimentSeeder::class,
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

        return [$user->createToken('stats-test')->plainTextToken, $user, $team];
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
