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
        $this->assertSame(
            $scaleTimes[0]->format('Y-m-d H:i:s'),
            $scaleTimes[1]->format('Y-m-d H:i:s'),
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
