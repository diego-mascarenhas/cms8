<?php

namespace Tests\Feature\Api;

use App\Mail\TestMessageMail;
use App\Models\Message;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\MessageTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MessageApiWriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            ContactStatusSeeder::class,
            MessageTypeSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string}
     */
    private function adminWithToken(bool $configureSender = false): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::query()->firstOrCreate(
            ['key' => 'mailer'],
            [
                'name' => 'Mailer',
                'icon' => 'send',
                'description' => 'Email campaigns and marketing automation',
                'is_core' => false,
                'status' => 1,
            ],
        );

        Module::query()->firstOrCreate(
            ['key' => 'templates'],
            [
                'name' => 'Templates',
                'icon' => 'template',
                'description' => 'Templates management module',
                'is_core' => false,
                'status' => 1,
            ],
        );

        Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            [
                'name' => 'Contacts',
                'icon' => 'users',
                'description' => 'Contacts module',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $team->enableModule('mailer');
        $team->enableModule('templates');
        $team->enableModule('contacts');

        if ($configureSender)
        {
            $team->setSetting('mail_from_name', 'Mailer Team');
            $team->setSetting('mail_from_address', 'mailer@example.test');
        }

        $token = $user->createToken('idoneo-mailer-test')->plainTextToken;

        return [$user, $team, $token];
    }

    public function test_can_create_update_and_delete_message(): void
    {
        [, $team, $token] = $this->adminWithToken();

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/message', [
                'name' => 'Spring Campaign',
                'text' => 'Newsletter subject line',
                'mail_html' => '<p>Hello {{name}}</p>',
                'show_unsubscribe' => true,
                'enable_open_tracking' => true,
                'enable_click_tracking' => false,
                'min_hours_between_emails' => 24,
            ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Spring Campaign')
            ->assertJsonPath('data.type_id', 1)
            ->assertJsonPath('data.mail_html', '<p>Hello {{name}}</p>')
            ->assertJsonPath('data.show_unsubscribe', true)
            ->assertJsonPath('data.enable_open_tracking', true)
            ->assertJsonPath('data.enable_click_tracking', false)
            ->assertJsonPath('data.min_hours_between_emails', 24);

        $messageId = $create->json('data.id');
        $this->assertNotNull($messageId);
        $this->assertDatabaseHas('messages', [
            'id' => $messageId,
            'team_id' => $team->id,
            'name' => 'Spring Campaign',
            'type_id' => 1,
        ]);

        $update = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/message/'.$messageId, [
                'name' => 'Spring Campaign Updated',
                'text' => 'Updated subject line',
            ]);

        $update->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Spring Campaign Updated')
            ->assertJsonPath('data.text', 'Updated subject line');

        $preview = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/message/'.$messageId.'/preview');

        $preview->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['html', 'subject', 'text'],
            ]);

        $delete = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/message/'.$messageId);

        $delete->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('messages', ['id' => $messageId]);
    }

    public function test_start_fails_without_sender_config(): void
    {
        [, $team, $token] = $this->adminWithToken(configureSender: false);

        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'No Sender',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 0,
            'mail_html' => '<p>Hi</p>',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/message/'.$message->id.'/start');

        $response->assertStatus(400)
            ->assertJsonPath('success', false);

        $message->refresh();
        $this->assertFalse((bool) $message->status_id);
    }

    public function test_start_and_pause_with_sender_configured(): void
    {
        [, $team, $token] = $this->adminWithToken(configureSender: true);

        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Ready Campaign',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 0,
            'mail_html' => '<p>Hi</p>',
        ]);

        $start = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/message/'.$message->id.'/start');

        $start->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status.key', 'sending');

        $message->refresh();
        $this->assertTrue((bool) $message->status_id);
        $this->assertNotNull($message->started_at);

        $pause = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/message/'.$message->id.'/pause');

        $pause->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status.key', 'paused');

        $message->refresh();
        $this->assertFalse((bool) $message->status_id);
    }

    public function test_test_send_requires_email_and_sends_mail(): void
    {
        Mail::fake();

        [, $team, $token] = $this->adminWithToken(configureSender: true);

        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Test Campaign',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 0,
            'mail_html' => '<p>Hello</p>',
        ]);

        $missing = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/message/'.$message->id.'/test', []);

        $missing->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $ok = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/message/'.$message->id.'/test', [
                'email' => 'qa@example.test',
            ]);

        $ok->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('email', 'qa@example.test');

        Mail::assertSent(TestMessageMail::class, function (TestMessageMail $mail) use ($message)
        {
            return $mail->hasTo('qa@example.test')
                && (int) $mail->message->id === (int) $message->id;
        });
    }

    public function test_mailer_lookups_return_categories_statuses_and_templates(): void
    {
        [, , $token] = $this->adminWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/lookups');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'categories',
                    'contact_statuses',
                    'templates',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.contact_statuses'));
    }

    public function test_unauthenticated_cannot_write_messages(): void
    {
        $this->postJson('/api/message', [])->assertUnauthorized();
        $this->putJson('/api/message/1', [])->assertUnauthorized();
        $this->deleteJson('/api/message/1')->assertUnauthorized();
    }
}
