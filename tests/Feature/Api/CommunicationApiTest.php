<?php

namespace Tests\Feature\Api;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Jobs\SendCommunicationJob;
use App\Models\Communication;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommunicationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string}
     */
    private function adminWithToken(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $this->enableTeamModules($team, ['communications']);

        $token = $user->createToken('idoneo-communications-test')->plainTextToken;

        return [$user, $team, $token];
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/communications', [
            'channel' => 'email',
            'recipient_email' => 'ada@example.test',
            'subject' => 'Hello',
            'message' => 'Body',
        ])->assertUnauthorized();
    }

    public function test_can_queue_email_communication(): void
    {
        Queue::fake();
        [, $team, $token] = $this->adminWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/communications', [
                'channel' => 'email',
                'recipient_email' => 'ada@example.test',
                'recipient_name' => 'Ada',
                'subject' => 'Invoice',
                'message' => 'Your invoice is ready.',
                'metadata' => ['source' => 'erp', 'external_id' => 'INV-1'],
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.channel', 'email')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.recipient_email', 'ada@example.test')
            ->assertJsonPath('data.metadata.source', 'erp')
            ->assertJsonPath('data.events.0.type', 'queued');

        $this->assertDatabaseHas('communications', [
            'team_id' => $team->id,
            'recipient_email' => 'ada@example.test',
            'status' => CommunicationStatus::Pending->value,
        ]);

        Queue::assertPushedOn('communications', SendCommunicationJob::class);
    }

    public function test_email_requires_recipient_and_subject(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/communications', [
                'channel' => 'email',
                'message' => 'Missing destination',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_email', 'subject']);
    }

    public function test_whatsapp_and_sms_require_phone(): void
    {
        Queue::fake();
        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/communications', [
                'channel' => 'whatsapp',
                'message' => 'Hola',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_phone']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/communications', [
                'channel' => 'sms',
                'recipient_phone' => '123',
                'message' => 'Hola',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_phone']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/communications', [
                'channel' => 'whatsapp',
                'recipient_phone' => '+34 600 111 222',
                'message' => 'Hola',
            ])
            ->assertCreated()
            ->assertJsonPath('data.channel', 'whatsapp')
            ->assertJsonPath('data.recipient_phone', '34600111222');
    }

    public function test_index_is_scoped_to_current_team(): void
    {
        Queue::fake();
        [$user, $team, $token] = $this->adminWithToken();

        Communication::factory()->forTeamAndUser($team, $user)->email()->create([
            'subject' => 'Mine',
        ]);

        $other = User::factory()->withPersonalTeam()->create();
        $otherTeam = $other->ownedTeams()->first();
        Communication::factory()->forTeamAndUser($otherTeam, $other)->email()->create([
            'subject' => 'Other team',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/communications')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.subject', 'Mine');
    }

    public function test_index_includes_open_tracking_on_sent_communications(): void
    {
        [$user, $team, $token] = $this->adminWithToken();
        $sent = Communication::factory()->forTeamAndUser($team, $user)->email()->sent()->create([
            'subject' => 'Test 5',
        ]);
        $sent->markOpened();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/communications')
            ->assertOk()
            ->assertJsonPath('data.0.tracking.opened', true);
    }

    public function test_stats_and_retry(): void
    {
        Queue::fake();
        [$user, $team, $token] = $this->adminWithToken();

        Communication::factory()->forTeamAndUser($team, $user)->email()->sent()->create();
        $failed = Communication::factory()->forTeamAndUser($team, $user)->email()->failed()->create([
            'subject' => 'Retry me',
        ]);
        Communication::factory()->forTeamAndUser($team, $user)->email()->create();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/communications/stats')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.failed', 1)
            ->assertJsonPath('data.pending', 1)
            ->assertJsonPath('data.sent', 1);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/communications/'.$failed->id.'/retry')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.events.0.type', 'queued')
            ->assertJsonPath('data.events.1.type', 'retried');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/communications/'.$failed->id.'/retry')
            ->assertStatus(422);
    }

    public function test_show_synthesizes_timeline_when_events_are_missing(): void
    {
        [$user, $team, $token] = $this->adminWithToken();
        $sent = Communication::factory()->forTeamAndUser($team, $user)->email()->sent()->create([
            'subject' => 'Tu factura',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/communications/'.$sent->id)
            ->assertOk()
            ->assertJsonPath('data.events.0.type', 'queued')
            ->assertJsonPath('data.events.1.type', 'sent');
    }

    public function test_can_resend_sent_communication(): void
    {
        Queue::fake();
        [$user, $team, $token] = $this->adminWithToken();
        $sent = Communication::factory()->forTeamAndUser($team, $user)->email()->sent()->create();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/communications/'.$sent->id.'/retry')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.events.1.type', 'retried');

        Queue::assertPushed(SendCommunicationJob::class);
    }

    public function test_cannot_retry_pending_communication(): void
    {
        Queue::fake();
        [$user, $team, $token] = $this->adminWithToken();
        $pending = Communication::factory()->forTeamAndUser($team, $user)->email()->create();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/communications/'.$pending->id.'/retry')
            ->assertStatus(422);
    }

    public function test_team_token_can_store_and_show(): void
    {
        Queue::fake();
        [, $team] = $this->adminWithToken();
        $created = $team->createApiToken('ERP', '*');

        $store = $this->withHeader('Authorization', 'Bearer '.$created['plain'])
            ->postJson('/api/team/communications', [
                'channel' => 'email',
                'recipient_email' => 'erp@example.test',
                'subject' => 'From ERP',
                'message' => 'Queued by machine token',
            ]);

        $store->assertCreated()
            ->assertJsonPath('data.subject', 'From ERP');

        $id = $store->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$created['plain'])
            ->getJson('/api/team/communications/'.$id)
            ->assertOk()
            ->assertJsonPath('data.id', $id);
    }

    public function test_links_existing_contact_by_email(): void
    {
        Queue::fake();
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        [$user, $team, $token] = $this->adminWithToken();
        $statusId = ContactStatus::query()->value('id');

        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Ada',
            'email' => 'ada@example.test',
            'creator_id' => $user->id,
            'status_id' => $statusId,
            'country' => 724,
            'language' => 'es',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/communications', [
                'channel' => 'email',
                'recipient_email' => 'ada@example.test',
                'subject' => 'Linked',
                'message' => 'Hello Ada',
            ])
            ->assertCreated()
            ->assertJsonPath('data.contact.id', $contact->id);
    }

    public function test_email_attachments_are_stored(): void
    {
        Queue::fake();
        Storage::fake('public');
        [, , $token] = $this->adminWithToken();

        $file = UploadedFile::fake()->create('invoice.pdf', 120, 'application/pdf');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/communications', [
                'channel' => CommunicationChannel::Email->value,
                'recipient_email' => 'ada@example.test',
                'subject' => 'With file',
                'message' => 'See attachment',
                'attachments' => [$file],
            ], [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ])
            ->assertCreated()
            ->assertJsonPath('data.attachments.0.file_name', 'invoice.pdf');

        $communication = Communication::query()->first();
        $this->assertNotNull($communication);
        $this->assertSame(1, $communication->getMedia('attachments')->count());
    }

    public function test_docs_token_requires_authentication(): void
    {
        $this->getJson('/api/communications/docs-token')->assertUnauthorized();
    }

    public function test_docs_token_returns_team_api_token_when_present(): void
    {
        [, $team, $token] = $this->adminWithToken();
        $created = $team->createApiToken('Docs', '*');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/communications/docs-token')
            ->assertOk()
            ->assertJsonPath('data.api_token', $created['plain']);
    }

    public function test_docs_token_is_null_when_team_has_no_api_token(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/communications/docs-token')
            ->assertOk()
            ->assertJsonPath('data.api_token', null);
    }

    public function test_channels_endpoint_returns_configuration_flags(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/communications/channels')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'email' => ['configured', 'from_name', 'from_address'],
                    'whatsapp' => ['configured'],
                    'sms' => ['configured'],
                ],
            ]);
    }
}
