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
        $this->assertSame(2, (int) $scaleTimes[0]->diffInSeconds($scaleTimes[1]));

        $this->assertSame(
            'Cada 29 segundos',
            $basic->team->mailerSendPaceText(),
        );
        $this->assertSame(
            'Cada 2 segundos',
            $foundation->team->mailerSendPaceText(),
        );
        $this->assertSame(
            'Inmediata para quienes ya recibieron o abrieron un correo. Cada 2 segundos para el resto.',
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

        $this->assertSame('Cada 2 segundos', $message->currentSendPaceText());

        MessageDelivery::query()->where('message_id', $message->id)->update([
            'sent_at' => now()->subSeconds(20),
        ]);

        $this->assertSame('Inmediata', $message->currentSendPaceText());

        $message->update(['status_id' => 0]);

        $this->assertNull($message->fresh()->currentSendPaceText());
    }

    public function test_scale_sends_reached_contacts_immediately_and_paces_the_rest(): void
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

        $this->assertSame($message->started_at->format('Y-m-d H:i:s'), $knownTime->format('Y-m-d H:i:s'));
        $this->assertTrue($coldTime->greaterThan($knownTime));
        $this->assertSame(2, (int) $knownTime->diffInSeconds($coldTime));
    }

    public function test_a_whitelisted_team_paces_new_addresses_and_sends_known_ones_immediately(): void
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
        $this->assertSame(2, (int) $times[0]->diffInSeconds($times[1]));
        $this->assertSame(
            $message->started_at->format('Y-m-d H:i:s'),
            $times[0]->format('Y-m-d H:i:s'),
        );
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
