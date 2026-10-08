<?php

namespace Tests\Feature\Api;

use App\Enums\EmailPlan;
use App\Mail\TestMessageMail;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\MessageTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
            CountrySeeder::class,
            LanguageSeeder::class,
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
                    'merge_fields',
                    'merge_sample',
                    'sender' => ['from_name', 'from_address', 'configured', 'can_update'],
                    'usage',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.contact_statuses'));
        $this->assertFalse($response->json('data.sender.configured'));
    }

    public function test_can_read_and_update_mailer_sender(): void
    {
        [, $team, $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/sender')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.required_include', 'include:spf.revisionalpha.com')
            ->assertJsonPath('data.example_txt', 'v=spf1 include:spf.revisionalpha.com -all')
            ->assertJsonPath('data.spf', null);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/mailer/sender', [
                'mail_from_name' => 'Campaña Idoneo',
                'mail_from_address' => 'news@example.test',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.from_name', 'Campaña Idoneo')
            ->assertJsonPath('data.from_address', 'news@example.test')
            ->assertJsonPath('data.spf.domain', 'example.test');

        $this->assertSame('Campaña Idoneo', $team->fresh()->getSetting('mailer_from_name'));
        $this->assertSame('news@example.test', $team->fresh()->getSetting('mailer_from_address'));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/sender')
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.spf.domain', 'example.test');
    }

    public function test_save_send_fails_without_sender_and_succeeds_when_configured(): void
    {
        [, , $token] = $this->adminWithToken();

        $blocked = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/message', [
                'name' => 'News campaign',
                'text' => 'Preview text here',
                'mail_html' => '<p>Hello</p>',
                'save_intent' => 'save_send',
            ]);

        $blocked->assertStatus(400)
            ->assertJsonPath('success', false);
        $this->assertNotNull($blocked->json('data.id'));
        $this->assertFalse((bool) Message::withoutGlobalScopes()->find($blocked->json('data.id'))?->status_id);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/mailer/sender', [
                'mail_from_name' => 'Campaña Idoneo',
                'mail_from_address' => 'news@example.test',
            ])
            ->assertOk()
            ->assertJsonPath('data.configured', true);

        $ok = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/message', [
                'name' => 'Ready news campaign',
                'text' => 'Preview text here',
                'mail_html' => '<p>Hello</p>',
                'save_intent' => 'save_send',
            ]);

        $ok->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status.key', 'sending');
    }

    public function test_save_schedule_requires_datetime_and_stores_it(): void
    {
        [, , $token] = $this->adminWithToken();

        $missing = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/message', [
                'name' => 'Scheduled news',
                'text' => 'Preview text here',
                'save_intent' => 'save_schedule',
            ]);

        $missing->assertStatus(422)
            ->assertJsonValidationErrors(['scheduled_send_at']);

        $when = now()->addDay()->startOfMinute()->format('Y-m-d H:i');

        $ok = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/message', [
                'name' => 'Scheduled news',
                'text' => 'Preview text here',
                'save_intent' => 'save_schedule',
                'schedule_send_at' => $when,
            ]);

        $ok->assertCreated()
            ->assertJsonPath('success', true);
        $this->assertNotNull($ok->json('data.scheduled_send_at'));
    }

    public function test_can_list_message_deliveries(): void
    {
        [, $team, $token] = $this->adminWithToken();

        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'With deliveries',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 0,
            'mail_html' => '<p>Hi</p>',
        ]);

        $empty = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/message/'.$message->id.'/deliveries');

        $empty->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.total', 0);
    }

    public function test_delivery_log_reads_mailbaby_by_id_or_recipient(): void
    {
        [$user, $team, $token] = $this->adminWithToken();

        config([
            'services.mailbaby.api_key' => 'test-key',
            'services.mailbaby.api_url' => 'https://api.mailbaby.net',
            'services.mailbaby.order_id' => 80474,
        ]);

        Http::fake(function ($request)
        {
            if (str_contains($request->url(), 'mailid='))
            {
                return Http::response([
                    'total' => 1,
                    'emails' => [[
                        'id' => '1a11ae9be07000e6f4',
                        'delivered' => 0,
                        'code' => 550,
                        'response' => '550 5.1.1 User unknown',
                        'created' => '2026-10-08 05:48:08',
                        'user' => 'mb80474',
                        'subject' => 'With a log',
                        'from' => 'news@idoneo.dev',
                        'to' => 'logged@example.test',
                    ]],
                ]);
            }

            return Http::response([
                'total' => 1,
                'emails' => [[
                    'id' => '1a11ae9be07000e6f5',
                    'delivered' => 1,
                    'code' => 250,
                    'response' => '250 2.0.0 Ok',
                    'user' => 'mb80474',
                    'subject' => 'With a log',
                    'to' => 'smtp@example.test',
                ]],
            ]);
        });

        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'With a log',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 0,
            'mail_html' => '<p>Hi</p>',
        ]);

        $logged = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'logged@example.test',
        ]);
        $byId = MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $message->id,
            'contact_id' => $logged->id,
            'status_id' => 4,
            'email_provider' => 'mailbaby',
            'provider_message_id' => '1a11ae9be07000e6f4',
            'sent_at' => now(),
        ]);

        $smtpContact = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'smtp@example.test',
        ]);
        $byRecipient = MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $message->id,
            'contact_id' => $smtpContact->id,
            'status_id' => 3,
            'email_provider' => 'smtp',
            'sent_at' => now(),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/message/'.$message->id.'/deliveries/'.$byId->id.'/log')
            ->assertOk()
            ->assertJsonPath('data.found', true)
            ->assertJsonPath('data.code', 550)
            ->assertJsonPath('data.user', 'mb80474')
            ->assertJsonPath('data.email_provider', 'mailbaby');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/message/'.$message->id.'/deliveries/'.$byRecipient->id.'/log')
            ->assertOk()
            ->assertJsonPath('data.found', true)
            ->assertJsonPath('data.code', 250)
            ->assertJsonPath('data.email_provider', 'smtp');

        Http::assertSent(function ($request): bool
        {
            return str_contains($request->url(), 'id=80474');
        });
    }

    public function test_delivery_status_filter_finds_failed_rows_outside_the_first_page(): void
    {
        [$user, $team, $token] = $this->adminWithToken();

        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'With a failure',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 0,
            'mail_html' => '<p>Hi</p>',
        ]);

        $failedContact = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'name' => 'Fallido Unico',
            'email' => 'fallido@example.test',
        ]);

        MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $message->id,
            'contact_id' => $failedContact->id,
            'status_id' => 4,
            'sent_at' => now()->subHour(),
        ])->forceFill([
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ])->save();

        $resentContact = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'reenviado@example.test',
        ]);

        MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $message->id,
            'contact_id' => $resentContact->id,
            'status_id' => 4,
            'sent_at' => now()->subHours(2),
        ])->forceFill([
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ])->save();

        MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $message->id,
            'contact_id' => $resentContact->id,
            'status_id' => 1,
            'sent_at' => now(),
            'delivered_at' => now(),
        ])->forceFill([
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ])->save();

        foreach (range(1, 11) as $index)
        {
            $contact = Contact::factory()->create([
                'team_id' => $team->id,
                'creator_id' => $user->id,
                'responsible_id' => $user->id,
                'email' => "ok{$index}@example.test",
            ]);

            MessageDelivery::query()->create([
                'team_id' => $team->id,
                'message_id' => $message->id,
                'contact_id' => $contact->id,
                'status_id' => 2,
                'sent_at' => now(),
                'delivered_at' => now(),
            ]);
        }

        $page = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/message/'.$message->id.'/deliveries');

        $page->assertOk()
            ->assertJsonPath('pagination.total', 14)
            ->assertJsonPath('pagination.current_page', 1);
        $this->assertNotContains(
            'fallido@example.test',
            collect($page->json('data'))->pluck('contact_email')->all(),
        );

        $failed = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/message/'.$message->id.'/deliveries?status=failed');

        $failed->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('pagination.last_page', 1)
            ->assertJsonPath('data.0.contact_email', 'fallido@example.test')
            ->assertJsonPath('data.0.status_key', 'failed');
    }

    public function test_can_update_target_when_message_has_deliveries(): void
    {
        [$user, $team, $token] = $this->adminWithToken();

        $contactsModule = Module::query()->where('key', 'contacts')->firstOrFail();
        $category = Category::query()->create([
            'name' => 'Mayoristas',
            'module_id' => $contactsModule->id,
            'team_id' => $team->id,
            'status' => 1,
        ]);

        $followStatusId = (int) ContactStatus::query()->where('name', 'En seguimiento')->value('id');

        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Sent Campaign',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 0,
            'mail_html' => '<p>Original</p>',
        ]);

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'campaign_id' => null,
            'status_id' => 1,
            'sent_at' => now(),
        ]);

        $update = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/message/'.$message->id, [
                'name' => 'Sent Campaign',
                'text' => 'Subject line here',
                'mail_html' => '<p>Tampered</p>',
                'message_category_ids' => [$category->id],
                'contact_status_id' => $followStatusId,
            ]);

        $update->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.contact_status.id', $followStatusId)
            ->assertJsonPath('data.contact_categories.0.id', $category->id)
            ->assertJsonPath('data.mail_html', '<p>Original</p>');

        $message->refresh();
        $this->assertSame('<p>Original</p>', $message->mail_html);
        $this->assertSame($followStatusId, (int) $message->contact_status_id);
        $this->assertEquals([$category->id], $message->contactCategories()->pluck('categories.id')->all());
    }

    public function test_custom_sender_is_limited_to_foundation_and_scale(): void
    {
        [, $team, $token] = $this->adminWithToken(configureSender: true);
        $team->assignEmailPlan(EmailPlan::BASIC);

        $denied = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/message', [
                'name' => 'Basic Campaign',
                'text' => 'Newsletter subject line',
                'from_name' => 'Otra marca',
                'from_address' => 'otra@example.test',
            ]);

        $denied->assertStatus(422)
            ->assertJsonValidationErrors(['from_address']);

        $team->assignEmailPlan(EmailPlan::FOUNDATION);

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/message', [
                'name' => 'Foundation Campaign',
                'text' => 'Newsletter subject line',
                'from_name' => 'Otra marca',
                'from_address' => 'otra@example.test',
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.from_name', 'Otra marca')
            ->assertJsonPath('data.from_address', 'otra@example.test')
            ->assertJsonPath('data.custom_sender_allowed', true)
            ->assertJsonPath('data.sender.from_name', 'Otra marca')
            ->assertJsonPath('data.sender.from_address', 'otra@example.test');

        $messageId = $create->json('data.id');

        $cleared = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/message/'.$messageId, [
                'from_name' => '',
                'from_address' => '',
            ]);

        $cleared->assertOk()
            ->assertJsonPath('data.from_name', null)
            ->assertJsonPath('data.sender.from_name', 'Mailer Team')
            ->assertJsonPath('data.sender.from_address', 'mailer@example.test');

        $team->assignEmailPlan(EmailPlan::SCALE);

        $scale = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/message/'.$messageId, [
                'from_name' => 'Scale marca',
                'from_address' => 'scale@example.test',
            ]);

        $scale->assertOk()
            ->assertJsonPath('data.sender.from_address', 'scale@example.test');

        $team->unsetRelation('settings');
        $team->assignEmailPlan(EmailPlan::BASIC);
        // The test client can reuse the authenticated user with settings already loaded.
        $this->app['auth']->forgetGuards();

        $ignored = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/message/'.$messageId);

        $ignored->assertOk()
            ->assertJsonPath('data.custom_sender_allowed', false)
            ->assertJsonPath('data.from_address', 'scale@example.test')
            ->assertJsonPath('data.sender.from_address', 'mailer@example.test');
    }

    public function test_whitelisted_team_can_set_a_custom_sender_on_any_plan(): void
    {
        [, $team, $token] = $this->adminWithToken(configureSender: true);
        $team->assignEmailPlan(EmailPlan::FREE);

        $denied = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/message', [
                'name' => 'Free Campaign',
                'text' => 'Newsletter subject line',
                'from_name' => 'Otra marca',
                'from_address' => 'otra@example.test',
            ]);

        $denied->assertStatus(422)
            ->assertJsonValidationErrors(['from_address']);

        config(['humano_pricing.plan_access_team_ids' => [(int) $team->id]]);

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/message', [
                'name' => 'Whitelisted Campaign',
                'text' => 'Newsletter subject line',
                'from_name' => 'Otra marca',
                'from_address' => 'otra@example.test',
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.custom_sender_allowed', true)
            ->assertJsonPath('data.from_name', 'Otra marca')
            ->assertJsonPath('data.from_address', 'otra@example.test')
            ->assertJsonPath('data.sender.from_name', 'Otra marca')
            ->assertJsonPath('data.sender.from_address', 'otra@example.test');
    }

    public function test_unauthenticated_cannot_write_messages(): void
    {
        $this->postJson('/api/message', [])->assertUnauthorized();
        $this->putJson('/api/message/1', [])->assertUnauthorized();
        $this->deleteJson('/api/message/1')->assertUnauthorized();
    }

    public function test_permanent_email_failure_is_stored_on_the_contact_and_left_out_of_later_sends(): void
    {
        [$user, $team] = $this->adminWithToken();

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'roto@example.test',
            'data' => ['notes' => 'Nota previa'],
        ]);
        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Aviso',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 1,
            'mail_html' => '<p>Hi</p>',
        ]);
        $delivery = MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'status_id' => 2,
        ]);

        $delivery->markAsError('Mailbox full, try again');
        $contact->refresh();
        $this->assertNull($contact->storedChannelValid('email'));
        $this->assertSame('Nota previa', data_get($contact->data, 'notes'));

        $delivery->markAsError('Invalid email address');
        $contact->refresh();
        $this->assertFalse($contact->storedChannelValid('email'));
        $this->assertSame('Invalid email address', $contact->storedChannelLastError('email'));
        $this->assertSame('failed', $contact->lastOutboundMessage()['status'] ?? null);
        $this->assertSame('Invalid email address', $contact->lastOutboundMessage()['summary'] ?? null);

        $contact->recordOutboundChannel('email', 'roto@example.test', null, 'sent', 'Aviso');
        $contact->refresh();
        $this->assertFalse($contact->storedChannelValid('email'));
        $this->assertSame('Invalid email address', $contact->storedChannelLastError('email'));
        $this->assertFalse(
            $message->audienceContactsQuery()->whereKey($contact->id)->exists(),
        );

        $contact->email = 'nuevo@example.test';
        $contact->save();
        $contact->refresh();
        $this->assertNull($contact->storedChannelValid('email'));
        $this->assertTrue(
            $message->audienceContactsQuery()->whereKey($contact->id)->exists(),
        );

        $contact->recordOutboundChannel('whatsapp', (string) $contact->phone, false, 'failed', 'Cannot resolve WhatsApp JID', 'Cannot resolve WhatsApp JID');
        $contact->refresh();
        $this->assertFalse($contact->storedChannelValid('whatsapp'));
        $this->assertSame('whatsapp', $contact->lastOutboundMessage()['channel'] ?? null);
        $this->assertSame('Nota previa', data_get($contact->data, 'notes'));
    }
}
