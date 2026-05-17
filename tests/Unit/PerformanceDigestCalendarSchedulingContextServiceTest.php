<?php

namespace Tests\Unit;

use App\Jobs\PushCalendarEventToGoogleJob;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use App\Services\PerformanceDigestUnreadMessageDetailService;
use Carbon\Carbon;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PerformanceDigestCalendarSchedulingContextServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        Queue::fake();
    }

    public function test_scheduling_message_books_first_free_slot_and_suggests_times(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-13 08:00:00'));

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34999000111');

        Module::firstOrCreate(['key' => 'chat'], ['name' => 'Chat', 'is_core' => false]);
        Module::firstOrCreate(['key' => 'calendar'], ['name' => 'Calendar', 'is_core' => false]);
        $team->enableModule('chat');
        $team->enableModule('calendar');
        $team = $team->fresh();

        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Ana',
            'surname' => 'García',
            'phone' => '34600111222',
            'email' => 'ana@example.com',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => 1,
            'country' => 724,
            'language' => 'es',
            'engagment' => 'temperate',
            'user_id' => $user->id,
        ]);

        $tomorrow = now()->addDay()->setTime(9, 0);
        CalendarEvent::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'title' => 'Reunión interna',
            'start' => $tomorrow->copy(),
            'end' => $tomorrow->copy()->addMinutes(30),
            'all_day' => false,
            'label' => 'Business',
        ]);

        Conversation::create([
            'message_sid' => 'SM_digest_schedule_1',
            'channel' => 'whatsapp',
            'from' => '34600111222',
            'to' => '34999000111',
            'body' => '¿Podemos agendar una llamada mañana?',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        $details = app(PerformanceDigestUnreadMessageDetailService::class)->forHighlightKey('whatsapp_unread', $team);

        $this->assertCount(1, $details);
        $this->assertStringContainsString('09:30', $details[0]['suggestion']);
        $this->assertStringContainsString('reservado', mb_strtolower($details[0]['suggestion']));
        $this->assertStringContainsString('agenda', mb_strtolower($details[0]['response_hint']));

        $booked = CalendarEvent::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereDate('start', $tomorrow->toDateString())
            ->where('start', $tomorrow->copy()->setTime(9, 30))
            ->first();

        $this->assertNotNull($booked);
        $this->assertStringContainsString('Ana', $booked->title);
        $this->assertTrue($booked->guests()->where('contacts.id', $contact->id)->exists());

        Queue::assertPushed(PushCalendarEventToGoogleJob::class);

        Carbon::setTestNow();
    }

    public function test_reuses_existing_call_with_contact_on_same_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-13 08:00:00'));

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34999000111');

        Module::firstOrCreate(['key' => 'chat'], ['name' => 'Chat', 'is_core' => false]);
        Module::firstOrCreate(['key' => 'calendar'], ['name' => 'Calendar', 'is_core' => false]);
        $team->enableModule('chat');
        $team->enableModule('calendar');
        $team = $team->fresh();

        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Ana',
            'surname' => 'García',
            'phone' => '34600111222',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => 1,
            'country' => 724,
            'language' => 'es',
            'engagment' => 'temperate',
            'user_id' => $user->id,
        ]);

        $tomorrow = now()->addDay()->setTime(10, 0);
        $existing = CalendarEvent::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'title' => 'Llamada - Ana',
            'start' => $tomorrow->copy(),
            'end' => $tomorrow->copy()->addMinutes(30),
            'all_day' => false,
            'label' => 'Business',
        ]);
        $existing->guests()->sync([$contact->id]);

        Conversation::create([
            'message_sid' => 'SM_digest_schedule_2',
            'channel' => 'whatsapp',
            'from' => '34600111222',
            'to' => '34999000111',
            'body' => '¿Podemos agendar una llamada mañana?',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        app(PerformanceDigestUnreadMessageDetailService::class)->forHighlightKey('whatsapp_unread', $team);

        $this->assertSame(
            1,
            CalendarEvent::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->whereDate('start', $tomorrow->toDateString())
                ->count(),
        );

        Carbon::setTestNow();
    }
}
