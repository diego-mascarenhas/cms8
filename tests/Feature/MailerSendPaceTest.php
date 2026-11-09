<?php

namespace Tests\Feature;

use App\Enums\EmailPlan;
use App\Jobs\SendMessageCampaignJob;
use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\MessageTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class MailerSendPaceTest extends TestCase
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

        config([
            'services.email.delay.random_seconds' => 0,
            'services.email.delay.base_minutes' => 1,
            'services.email.delay.fast_spacing_seconds' => 0,
            'services.email.delay.cold_spacing_seconds' => 30,
            'services.email.delay.opening_spacing_seconds' => 60,
            'services.email.delay.opening_sends' => 10,
        ]);
    }

    public function test_each_plan_schedules_at_its_own_pace(): void
    {
        $basic = $this->messageWithContacts(EmailPlan::BASIC, 'basic');
        $foundation = $this->messageWithContacts(EmailPlan::FOUNDATION, 'foundation');
        $scale = $this->messageWithContacts(EmailPlan::SCALE, 'scale');

        Artisan::call('campaigns:process-active', ['--message' => $basic->id]);
        Artisan::call('campaigns:process-active', ['--message' => $foundation->id]);
        Artisan::call('campaigns:process-active', ['--message' => $scale->id]);

        $basicTimes = $this->scheduledTimes($basic->id);
        $foundationTimes = $this->scheduledTimes($foundation->id);
        $scaleTimes = $this->scheduledTimes($scale->id);

        $this->assertCount(2, $basicTimes);
        $this->assertSame(29, (int) $basicTimes[0]->diffInSeconds($basicTimes[1]));

        $this->assertCount(2, $foundationTimes);
        $this->assertSame(2, (int) $foundationTimes[0]->diffInSeconds($foundationTimes[1]));

        $this->assertCount(2, $scaleTimes);
        $this->assertSame(60, (int) $scaleTimes[0]->diffInSeconds($scaleTimes[1]));

        $this->assertSame(
            '124 envíos por hora',
            $basic->team->mailerSendPaceText(),
        );
        $this->assertSame(
            '1800 envíos por hora',
            $foundation->team->mailerSendPaceText(),
        );
        $this->assertSame(
            '120 envíos por hora',
            $scale->team->mailerSendPaceText(),
        );
    }

    public function test_the_pace_line_follows_the_latest_sends(): void
    {
        $message = $this->messageWithContacts(EmailPlan::SCALE, 'live');
        $contacts = Contact::query()->where('team_id', $message->team_id)->orderBy('id')->get();

        $this->assertNull($message->currentSendPaceText());

        foreach ($contacts as $index => $contact)
        {
            MessageDelivery::query()->create([
                'team_id' => $message->team_id,
                'message_id' => $message->id,
                'contact_id' => $contact->id,
                'status_id' => 3,
                'sent_at' => now()->subSeconds(4 - ($index * 2)),
                'scheduled_for' => now()->subSeconds(4 - ($index * 2)),
            ]);
        }

        $this->assertSame('1800 envíos por hora', $message->currentSendPaceText());

        MessageDelivery::query()->where('message_id', $message->id)->update([
            'sent_at' => now()->subSeconds(20),
        ]);

        $this->assertSame('Inmediata', $message->currentSendPaceText());

        $message->update(['status_id' => 0]);

        $this->assertNull($message->fresh()->currentSendPaceText());
    }

    public function test_scale_starts_known_contacts_slowly_and_keeps_the_gap(): void
    {
        $message = $this->messageWithContacts(EmailPlan::SCALE, 'known');
        $contacts = Contact::query()->where('team_id', $message->team_id)->orderBy('id')->get();
        $known = $contacts[0];
        $cold = $contacts[1];
        $prior = Message::withoutGlobalScopes()->create([
            'team_id' => $message->team_id,
            'name' => 'Prior',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 0,
            'mail_html' => '<p>Hi</p>',
        ]);
        MessageDelivery::query()->create([
            'team_id' => $message->team_id,
            'message_id' => $prior->id,
            'contact_id' => $known->id,
            'status_id' => 3,
            'sent_at' => now()->subDay(),
            'delivered_at' => now()->subDay(),
            'scheduled_for' => now()->subDay(),
        ]);

        Artisan::call('campaigns:process-active', ['--message' => $message->id]);

        $knownTime = MessageDelivery::query()
            ->where('message_id', $message->id)
            ->where('contact_id', $known->id)
            ->first()
            ->scheduled_for;
        $coldTime = MessageDelivery::query()
            ->where('message_id', $message->id)
            ->where('contact_id', $cold->id)
            ->first()
            ->scheduled_for;

        $this->assertTrue($knownTime->greaterThan($message->started_at));
        $this->assertSame(60, (int) $message->started_at->diffInSeconds($knownTime));
        $this->assertSame(60, (int) $knownTime->diffInSeconds($coldTime));
    }

    public function test_a_whitelisted_team_sends_without_the_per_minute_drip(): void
    {
        $message = $this->messageWithContacts(EmailPlan::FREE, 'listed');
        config(['humano_pricing.plan_access_team_ids' => [(int) $message->team_id]]);
        $known = Contact::query()->where('team_id', $message->team_id)->orderBy('id')->first();
        $prior = Message::withoutGlobalScopes()->create([
            'team_id' => $message->team_id,
            'name' => 'Prior',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 0,
            'mail_html' => '<p>Hi</p>',
        ]);
        MessageDelivery::query()->create([
            'team_id' => $message->team_id,
            'message_id' => $prior->id,
            'contact_id' => $known->id,
            'status_id' => 2,
            'opened_at' => now()->subDay(),
            'scheduled_for' => now()->subDay(),
        ]);

        Artisan::call('campaigns:process-active', ['--message' => $message->id]);

        $times = $this->scheduledTimes($message->id);

        $this->assertCount(2, $times);
        $this->assertSame(30, (int) $times[0]->diffInSeconds($times[1]));
        $this->assertSame(30, (int) $message->started_at->diffInSeconds($times[0]));
        $this->assertSame(2, $message->team->fresh()->mailerDispatchBatch());
        $this->assertSame('120 envíos por hora', $message->team->fresh()->mailerSendPaceText());
    }

    public function test_a_bounce_lengthens_the_pause_and_accepted_sends_recover_up_to_the_cap(): void
    {
        config([
            'services.email.delay.adaptive_start_seconds' => 300,
            'services.email.delay.adaptive_min_seconds' => 120,
            'services.email.delay.adaptive_max_seconds' => 900,
            'services.email.delay.adaptive_success_factor' => 0.8,
            'services.email.delay.adaptive_success_streak' => 1,
        ]);

        $message = $this->messageWithContacts(EmailPlan::FREE, 'adaptive');
        config(['humano_pricing.plan_access_team_ids' => [(int) $message->team_id]]);
        $contacts = Contact::query()->where('team_id', $message->team_id)->orderBy('id')->get();
        $bounce = MessageDelivery::query()->create([
            'team_id' => $message->team_id,
            'message_id' => $message->id,
            'contact_id' => $contacts[0]->id,
            'status_id' => 2,
            'scheduled_for' => now()->subMinute(),
        ]);
        $pending = MessageDelivery::query()->create([
            'team_id' => $message->team_id,
            'message_id' => $message->id,
            'contact_id' => $contacts[1]->id,
            'status_id' => 1,
            'scheduled_for' => now()->subMinute(),
        ]);

        $this->assertSame(300, $message->team->mailerAdaptiveSpacingSeconds());

        $bounce->markAsError('550 5.1.1 The email account that you tried to reach does not exist.');

        $this->assertSame(600, $message->team->fresh()->mailerAdaptiveSpacingSeconds());
        $this->assertTrue((bool) $message->fresh()->status_id);
        $this->assertTrue($pending->fresh()->scheduled_for->greaterThan(now()->addSeconds(580)));

        $bounce->fresh()->markAsError('550 5.1.1 The email account that you tried to reach does not exist.');
        $this->assertSame(600, $message->team->fresh()->mailerAdaptiveSpacingSeconds());

        $message->team->fresh()->noteAdaptiveMailerBounce();
        $this->assertSame(900, $message->team->fresh()->mailerAdaptiveSpacingSeconds());
        $message->team->fresh()->noteAdaptiveMailerBounce();
        $this->assertSame(900, $message->team->fresh()->mailerAdaptiveSpacingSeconds());

        $pending->fresh()->reactMailerPace('success');
        $this->assertSame(720, $message->team->fresh()->mailerAdaptiveSpacingSeconds());

        $team = $message->team->fresh();
        while ($team->mailerAdaptiveSpacingSeconds() > 120)
        {
            $team->noteAdaptiveMailerSuccess();
            $team = $message->team->fresh();
        }

        $this->assertSame(120, $team->mailerAdaptiveSpacingSeconds());
        $this->assertSame('30 envíos por hora', $team->mailerSendPaceText());

        $message->team->fresh()->noteAdaptiveMailerSuccess();
        $this->assertSame(120, $message->team->fresh()->mailerAdaptiveSpacingSeconds());
    }

    public function test_a_recipient_bounce_does_not_change_a_plan_pace(): void
    {
        $message = $this->messageWithContacts(EmailPlan::BASIC, 'plan-bounce');
        $contact = Contact::query()->where('team_id', $message->team_id)->first();
        $delivery = MessageDelivery::query()->create([
            'team_id' => $message->team_id,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'status_id' => 2,
            'scheduled_for' => now()->subMinute(),
        ]);

        $delivery->markAsError('550 5.1.1 The email account that you tried to reach does not exist.');

        $this->assertNull($message->team->fresh()->getSetting('mailer_adaptive_spacing_seconds'));
        $this->assertSame('124 envíos por hora', $message->team->fresh()->mailerSendPaceText());
    }

    public function test_provider_spam_lengthens_the_complimentary_pause(): void
    {
        config([
            'services.email.delay.adaptive_start_seconds' => 300,
            'services.email.delay.adaptive_max_seconds' => 900,
        ]);
        $message = $this->messageWithContacts(EmailPlan::FREE, 'spam-pace');
        config(['humano_pricing.plan_access_team_ids' => [(int) $message->team_id]]);
        $contact = Contact::query()->where('team_id', $message->team_id)->first();
        $delivery = MessageDelivery::query()->create([
            'team_id' => $message->team_id,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'status_id' => 2,
            'scheduled_for' => now()->subMinute(),
        ]);

        $delivery->markAsError('550 This message was classified as rSPAM and may not be delivered');

        $this->assertSame(600, $message->team->fresh()->mailerAdaptiveSpacingSeconds());
        $this->assertFalse((bool) $message->fresh()->status_id);
    }

    public function test_a_connection_timeout_does_not_lengthen_the_adaptive_pause(): void
    {
        config(['services.email.delay.adaptive_start_seconds' => 300]);
        $message = $this->messageWithContacts(EmailPlan::FREE, 'timeout-pace');
        config(['humano_pricing.plan_access_team_ids' => [(int) $message->team_id]]);
        $contact = Contact::query()->where('team_id', $message->team_id)->first();
        $delivery = MessageDelivery::query()->create([
            'team_id' => $message->team_id,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'status_id' => 2,
            'scheduled_for' => now()->subMinute(),
        ]);

        $delivery->markAsError('Connection to "relay.outboundspamprotection.com:587" timed out.');

        $this->assertSame(300, $message->team->fresh()->mailerAdaptiveSpacingSeconds());
        $this->assertTrue((bool) $message->fresh()->status_id);
    }

    public function test_resume_recalculates_pending_sends_for_new_addresses(): void
    {
        $message = $this->messageWithContacts(EmailPlan::SCALE, 'resume');
        $contacts = Contact::query()->where('team_id', $message->team_id)->orderBy('id')->get();
        $bunch = now()->startOfSecond();

        foreach ($contacts as $index => $contact)
        {
            MessageDelivery::query()->create([
                'team_id' => $message->team_id,
                'message_id' => $message->id,
                'contact_id' => $contact->id,
                'status_id' => 1,
                'scheduled_for' => $bunch->copy()->addSeconds($index * 2),
            ]);
        }

        $message->pauseWithReason(Message::PAUSE_REASON_MANUAL);
        $message->update(['status_id' => 1, 'pause_reason' => null]);
        $message->reschedulePendingDeliveries();

        $times = $this->scheduledTimes($message->id);

        $this->assertCount(2, $times);
        $this->assertSame(60, (int) $times[0]->diffInSeconds($times[1]));
        $this->assertTrue($times[0]->greaterThan($bunch));
    }

    public function test_process_active_queues_every_contact_and_waits_out_the_gap(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->assignEmailPlan(EmailPlan::BASIC);

        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'all',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 1,
            'mail_html' => '<p>Hi</p>',
            'min_hours_between_emails' => 48,
            'started_at' => now(),
        ]);

        $contacts = collect([1, 2, 3])->map(fn (int $index): Contact => Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'all'.$index.'@example.test',
        ]));

        $keptFor = now()->addHour()->startOfSecond();
        MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $message->id,
            'contact_id' => $contacts[0]->id,
            'status_id' => 1,
            'scheduled_for' => $keptFor,
        ]);

        $prior = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Prior',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 0,
            'mail_html' => '<p>Hi</p>',
        ]);
        $sentAt = now()->subHour()->startOfSecond();
        MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $prior->id,
            'contact_id' => $contacts[1]->id,
            'status_id' => 2,
            'sent_at' => $sentAt,
            'scheduled_for' => $sentAt,
        ]);

        Artisan::call('campaigns:process-active', ['--message' => $message->id]);

        $this->assertSame(3, MessageDelivery::query()->where('message_id', $message->id)->count());

        $kept = MessageDelivery::query()
            ->where('message_id', $message->id)
            ->where('contact_id', $contacts[0]->id)
            ->first();
        $this->assertSame($keptFor->format('Y-m-d H:i:s'), $kept->scheduled_for->format('Y-m-d H:i:s'));

        $waiting = MessageDelivery::query()
            ->where('message_id', $message->id)
            ->where('contact_id', $contacts[1]->id)
            ->first();
        $this->assertTrue($waiting->scheduled_for->greaterThanOrEqualTo($sentAt->copy()->addHours(48)));
    }

    public function test_a_later_message_chains_after_the_one_already_scheduled(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->assignEmailPlan(EmailPlan::BASIC);

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'chain@example.test',
        ]);

        $sentAt = now()->subHour()->startOfSecond();
        $prior = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Prior',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 0,
            'mail_html' => '<p>Hi</p>',
        ]);
        MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $prior->id,
            'contact_id' => $contact->id,
            'status_id' => 2,
            'sent_at' => $sentAt,
            'scheduled_for' => $sentAt,
        ]);

        $first = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'First',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 1,
            'mail_html' => '<p>Hi</p>',
            'min_hours_between_emails' => 48,
            'started_at' => now(),
        ]);
        $second = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Second',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 1,
            'mail_html' => '<p>Hi</p>',
            'min_hours_between_emails' => 48,
            'started_at' => now(),
        ]);

        Artisan::call('campaigns:process-active', ['--message' => $first->id]);
        Artisan::call('campaigns:process-active', ['--message' => $second->id]);

        $firstTime = MessageDelivery::query()
            ->where('message_id', $first->id)
            ->where('contact_id', $contact->id)
            ->first()
            ->scheduled_for;
        $secondTime = MessageDelivery::query()
            ->where('message_id', $second->id)
            ->where('contact_id', $contact->id)
            ->first()
            ->scheduled_for;

        $this->assertTrue($firstTime->greaterThanOrEqualTo($sentAt->copy()->addHours(48)));
        $this->assertTrue($secondTime->greaterThanOrEqualTo($firstTime->copy()->addHours(48)));
    }

    public function test_two_due_deliveries_for_one_contact_do_not_send_together(): void
    {
        Bus::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->assignEmailPlan(EmailPlan::BASIC);
        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'due@example.test',
        ]);

        $earlierMessage = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Earlier',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 1,
            'mail_html' => '<p>Hi</p>',
            'min_hours_between_emails' => 48,
            'started_at' => now(),
        ]);
        $laterMessage = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Later',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 1,
            'mail_html' => '<p>Hi</p>',
            'min_hours_between_emails' => 48,
            'started_at' => now(),
        ]);

        $earlierAt = now()->subMinutes(2)->startOfSecond();
        $laterAt = now()->subMinute()->startOfSecond();
        $earlier = MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $earlierMessage->id,
            'contact_id' => $contact->id,
            'status_id' => 1,
            'scheduled_for' => $earlierAt,
        ]);
        $later = MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $laterMessage->id,
            'contact_id' => $contact->id,
            'status_id' => 1,
            'scheduled_for' => $laterAt,
        ]);

        Artisan::call('campaigns:send-scheduled');

        $this->assertSame($earlierAt->format('Y-m-d H:i:s'), $earlier->fresh()->scheduled_for->format('Y-m-d H:i:s'));
        $this->assertTrue($later->fresh()->scheduled_for->greaterThanOrEqualTo($earlierAt->copy()->addHours(48)));
        Bus::assertDispatched(SendMessageCampaignJob::class, 1);
    }

    public function test_a_paused_message_does_not_postpone_a_live_send(): void
    {
        Bus::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->assignEmailPlan(EmailPlan::BASIC);
        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'paused-gap@example.test',
        ]);

        $paused = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Paused',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 0,
            'mail_html' => '<p>Hi</p>',
            'min_hours_between_emails' => 72,
            'started_at' => now()->subDays(6),
        ]);
        $live = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Live',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 1,
            'mail_html' => '<p>Hi</p>',
            'min_hours_between_emails' => 72,
            'started_at' => now()->subDays(6),
        ]);

        $sentAt = now()->subDays(8)->startOfSecond();
        MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $paused->id,
            'contact_id' => $contact->id,
            'status_id' => 2,
            'sent_at' => $sentAt,
            'scheduled_for' => $sentAt,
        ]);

        $staleSlot = now()->subDay()->startOfSecond();
        MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $paused->id,
            'contact_id' => $contact->id,
            'status_id' => 1,
            'scheduled_for' => $staleSlot,
        ]);

        $dueAt = now()->subMinute()->startOfSecond();
        $liveDelivery = MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $live->id,
            'contact_id' => $contact->id,
            'status_id' => 1,
            'scheduled_for' => $dueAt,
        ]);

        Artisan::call('campaigns:send-scheduled');

        $this->assertSame($dueAt->format('Y-m-d H:i:s'), $liveDelivery->fresh()->scheduled_for->format('Y-m-d H:i:s'));
        Bus::assertDispatched(SendMessageCampaignJob::class, 1);
    }

    public function test_the_send_cron_skips_a_delivery_whose_contact_has_no_email(): void
    {
        Bus::fake();
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => null,
        ]);
        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'No address',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 1,
            'mail_html' => '<p>Hi</p>',
            'min_hours_between_emails' => 0,
            'started_at' => now(),
        ]);
        MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'status_id' => 1,
            'scheduled_for' => now()->subMinute(),
        ]);

        Artisan::call('campaigns:send-scheduled');

        Bus::assertNothingDispatched();
    }

    public function test_the_send_cron_does_not_dump_a_fast_backlog(): void
    {
        Bus::fake();
        $message = $this->messageWithContacts(EmailPlan::SCALE, 'backlog');
        $ownerId = (int) Contact::query()->where('team_id', $message->team_id)->value('creator_id');

        foreach ([1, 2, 3] as $index)
        {
            Contact::factory()->create([
                'team_id' => $message->team_id,
                'creator_id' => $ownerId,
                'responsible_id' => $ownerId,
                'email' => 'backlog-'.$index.'@example.test',
            ]);
        }

        foreach (Contact::query()->where('team_id', $message->team_id)->get() as $contact)
        {
            MessageDelivery::query()->create([
                'team_id' => $message->team_id,
                'message_id' => $message->id,
                'contact_id' => $contact->id,
                'status_id' => 1,
                'scheduled_for' => now()->subMinute(),
            ]);
        }

        Artisan::call('campaigns:send-scheduled');

        Bus::assertDispatched(SendMessageCampaignJob::class, 2);
    }

    public function test_urgent_send_skips_the_hours_between_news(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->assignEmailPlan(EmailPlan::BASIC);
        $team->setSetting('mailer_min_hours_between_emails', 48, ['type' => 'integer', 'group' => 'mailer']);

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'urgent@example.test',
        ]);
        $sentAt = now()->subHour()->startOfSecond();
        $prior = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Prior',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 0,
            'mail_html' => '<p>Hi</p>',
        ]);
        MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $prior->id,
            'contact_id' => $contact->id,
            'status_id' => 2,
            'sent_at' => $sentAt,
            'scheduled_for' => $sentAt,
        ]);

        $waiting = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Waiting',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 1,
            'mail_html' => '<p>Hi</p>',
            'min_hours_between_emails' => 48,
            'urgent' => false,
            'started_at' => now(),
        ]);
        $urgent = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Urgent',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 1,
            'mail_html' => '<p>Hi</p>',
            'min_hours_between_emails' => 48,
            'urgent' => true,
            'started_at' => now(),
        ]);

        Artisan::call('campaigns:process-active', ['--message' => $waiting->id]);
        Artisan::call('campaigns:process-active', ['--message' => $urgent->id]);

        $waitingAt = MessageDelivery::query()
            ->where('message_id', $waiting->id)
            ->where('contact_id', $contact->id)
            ->first()
            ->scheduled_for;
        $urgentAt = MessageDelivery::query()
            ->where('message_id', $urgent->id)
            ->where('contact_id', $contact->id)
            ->first()
            ->scheduled_for;

        $this->assertTrue($waitingAt->greaterThanOrEqualTo($sentAt->copy()->addHours(48)));
        $this->assertTrue($urgentAt->lessThan($sentAt->copy()->addHours(2)));
    }

    public function test_a_two_hour_gap_is_pulled_up_to_about_five_minutes(): void
    {
        config([
            'services.email.delay.cold_spacing_seconds' => 7200,
            'services.email.delay.opening_spacing_seconds' => 7200,
            'services.email.delay.spacing_jitter_ratio' => 0.4,
        ]);

        $message = $this->messageWithContacts(EmailPlan::SCALE, 'capped');
        $team = $message->team;
        $samples = [];
        for ($i = 0; $i < 30; $i++)
        {
            $samples[] = $team->mailerNextGapSeconds(7200);
        }

        $this->assertGreaterThanOrEqual(180, min($samples));
        $this->assertLessThanOrEqual(420, max($samples));
        $this->assertNotContains(7200, $samples);
        $average = array_sum($samples) / count($samples);
        $this->assertGreaterThan(240, $average);
        $this->assertLessThan(360, $average);

        Artisan::call('campaigns:process-active', ['--message' => $message->id]);
        $times = $this->scheduledTimes($message->id);
        $this->assertCount(2, $times);
        $gap = (int) $times[0]->diffInSeconds($times[1]);
        $this->assertGreaterThanOrEqual(180, $gap);
        $this->assertLessThanOrEqual(420, $gap);
    }

    public function test_a_ready_contact_is_not_queued_behind_someone_still_waiting(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->assignEmailPlan(EmailPlan::SCALE);
        $team->setSetting('mailer_min_hours_between_emails', 72, ['type' => 'integer', 'group' => 'mailer']);

        $waitingContact = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'waiting@example.test',
        ]);
        $readyContact = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'ready@example.test',
        ]);
        $sentAt = now()->subHour()->startOfSecond();
        $prior = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Prior',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 0,
            'mail_html' => '<p>Hi</p>',
        ]);
        MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $prior->id,
            'contact_id' => $waitingContact->id,
            'status_id' => 2,
            'sent_at' => $sentAt,
            'scheduled_for' => $sentAt,
        ]);
        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Live',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 1,
            'mail_html' => '<p>Hi</p>',
            'min_hours_between_emails' => 48,
            'started_at' => now(),
        ]);

        Artisan::call('campaigns:process-active', ['--message' => $message->id]);

        $readyAt = MessageDelivery::query()
            ->where('message_id', $message->id)
            ->where('contact_id', $readyContact->id)
            ->first()
            ->scheduled_for;
        $waitingAt = MessageDelivery::query()
            ->where('message_id', $message->id)
            ->where('contact_id', $waitingContact->id)
            ->first()
            ->scheduled_for;

        $this->assertTrue($readyAt->lt($waitingAt));
        $this->assertTrue($readyAt->lessThan(now()->addMinutes(5)));
        $this->assertTrue($waitingAt->greaterThanOrEqualTo($sentAt->copy()->addHours(72)));
    }

    public function test_an_eligible_contact_parked_days_out_rejoins_the_live_drip(): void
    {
        $message = $this->messageWithContacts(EmailPlan::SCALE, 'parked');
        Artisan::call('campaigns:process-active', ['--message' => $message->id]);

        MessageDelivery::query()
            ->where('message_id', $message->id)
            ->update(['scheduled_for' => now()->addDays(3)]);

        Artisan::call('campaigns:process-active', ['--message' => $message->id]);

        $times = $this->scheduledTimes($message->id);
        $this->assertCount(2, $times);
        $this->assertTrue($times[0]->lessThan(now()->addMinutes(5)));
        $this->assertSame(60, (int) $times[0]->diffInSeconds($times[1]));

        $kept = $times[0]->format('Y-m-d H:i:s');
        Artisan::call('campaigns:process-active', ['--message' => $message->id]);

        $this->assertSame($kept, $this->scheduledTimes($message->id)->first()->format('Y-m-d H:i:s'));
    }

    public function test_the_pace_line_is_zero_when_the_next_sends_are_not_due_yet(): void
    {
        $message = $this->messageWithContacts(EmailPlan::SCALE, 'idle');
        $contacts = Contact::query()->where('team_id', $message->team_id)->orderBy('id')->get();
        $later = now()->addDays(2)->startOfSecond();

        foreach ($contacts as $index => $contact)
        {
            MessageDelivery::query()->create([
                'team_id' => $message->team_id,
                'message_id' => $message->id,
                'contact_id' => $contact->id,
                'status_id' => 1,
                'scheduled_for' => $later->copy()->addSeconds($index * 65),
            ]);
        }

        $this->assertSame('0 envíos por hora', $message->currentSendPaceText());
    }

    /**
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Carbon>
     */
    private function scheduledTimes(int $messageId)
    {
        return MessageDelivery::query()
            ->where('message_id', $messageId)
            ->orderBy('scheduled_for')
            ->pluck('scheduled_for');
    }

    private function messageWithContacts(EmailPlan $plan, string $prefix): Message
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->assignEmailPlan($plan);

        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => $prefix.' pace',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 1,
            'mail_html' => '<p>Hi</p>',
            'min_hours_between_emails' => 0,
            'started_at' => now(),
        ]);

        foreach ([1, 2] as $index)
        {
            Contact::factory()->create([
                'team_id' => $team->id,
                'creator_id' => $user->id,
                'responsible_id' => $user->id,
                'email' => $prefix.$index.'@example.test',
            ]);
        }

        return $message;
    }
}
