<?php

namespace Tests\Feature;

use App\Enums\EmailPlan;
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
