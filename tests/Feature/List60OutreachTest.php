<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppGateway;
use App\Enums\ContactInteractionType;
use App\Jobs\SendNotificationJob;
use App\Models\Contact;
use App\Models\ContactInteraction;
use App\Models\List60;
use App\Models\List60Status;
use App\Models\Module;
use App\Models\Notification;
use App\Models\User;
use App\Support\List60NextContactDate;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\List60StatusesSeeder;
use Database\Seeders\NotificationTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class List60OutreachTest extends TestCase
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
            NotificationTypesSeeder::class,
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

    public function test_send_outreach_whatsapp_records_interaction(): void
    {
        config(['whatsapp.driver' => 'twilio']);

        $sent = false;

        $this->app->instance(WhatsAppGateway::class, new class($sent) implements WhatsAppGateway
        {
            public function __construct(private bool &$sent) {}

            public function sendMessage(string $to, string $message, ?array $metadata = null, ?int $userId = null): mixed
            {
                $this->sent = true;

                return 'ok';
            }

            public function sendMedia(string $to, string $mediaPath, ?string $caption = null): bool
            {
                return true;
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function getQrUrl(): ?string
            {
                return null;
            }

            public function getConnectionStatus(): ?array
            {
                return ['status' => 'connected'];
            }
        });

        $contact = Contact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'phone' => '34600111222',
        ]);

        $sinContactar = List60Status::query()->where('name', 'Sin contactar')->firstOrFail();
        $oneContact = List60Status::query()->where('name', '1 Contacto')->firstOrFail();

        $list60 = List60::query()->create([
            'contact_id' => $contact->id,
            'type_id' => 1,
            'date_next' => now()->addWeek(),
            'status_id' => $sinContactar->id,
            'responsible_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('list60.send-outreach', $list60->id), [
            'channel' => 'whatsapp',
            'message' => 'Hola, seguimos en contacto.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', __('app.list60_outreach_success'));

        $this->assertTrue($sent);

        $list60->refresh();
        $this->assertTrue($list60->date_next->isSameDay(List60NextContactDate::afterOutreach()));
        $this->assertSame($oneContact->id, $list60->status_id);

        $interaction = ContactInteraction::query()->where('contact_id', $contact->id)->first();
        $this->assertNotNull($interaction);
        $this->assertSame(ContactInteractionType::WhatsApp, $interaction->type);
        $this->assertSame('Hola, seguimos en contacto.', $interaction->body);
    }

    public function test_send_outreach_email_queues_notification_and_records_interaction(): void
    {
        Queue::fake();

        $contact = Contact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'email' => 'cliente@example.com',
        ]);

        $sinContactar = List60Status::query()->where('name', 'Sin contactar')->firstOrFail();
        $oneContact = List60Status::query()->where('name', '1 Contacto')->firstOrFail();

        $list60 = List60::query()->create([
            'contact_id' => $contact->id,
            'type_id' => 1,
            'date_next' => now()->addWeek(),
            'status_id' => $sinContactar->id,
            'responsible_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('list60.send-outreach', $list60->id), [
            'channel' => 'email',
            'subject' => 'Seguimiento comercial',
            'message' => 'Te escribo para coordinar la próxima reunión.',
        ]);

        $response->assertOk();

        $list60->refresh();
        $this->assertTrue($list60->date_next->isSameDay(List60NextContactDate::afterOutreach()));
        $this->assertSame($oneContact->id, $list60->status_id);

        $notification = Notification::query()->where('contact_id', $contact->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame('Seguimiento comercial', $notification->subject);
        $this->assertSame(['format' => 'plain_text'], $notification->metadata);

        Queue::assertPushed(SendNotificationJob::class, function (SendNotificationJob $job) use ($notification)
        {
            return $job->notification->id === $notification->id;
        });

        $interaction = ContactInteraction::query()->where('contact_id', $contact->id)->first();
        $this->assertNotNull($interaction);
        $this->assertSame(ContactInteractionType::Email, $interaction->type);
        $this->assertSame('Seguimiento comercial', $interaction->subject);
    }
}
