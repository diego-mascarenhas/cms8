<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use App\Services\MeetBookingService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MeetBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'calendar.wall_clock_timezone' => 'Europe/Madrid',
            'services.revisionalpha.meet_url' => 'https://revisionalpha.test/meet',
        ]);
        $this->seed([ContactStatusSeeder::class, CountrySeeder::class, LanguageSeeder::class]);
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);
    }

    public function test_slots_follow_admin_and_collaborator_weekly_availability(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-26 08:00:00', 'Europe/Madrid'));
        $host = $this->hostContact();
        $host->weeklyAvailability()->create([
            'team_id' => $host->team_id,
            'monday' => true,
            'tuesday' => false,
            'wednesday' => true,
            'thursday' => true,
            'friday' => true,
            'saturday' => false,
            'sunday' => false,
        ]);

        $booking = app(MeetBookingService::class);

        $this->assertNotEmpty($booking->slots($host->team_id, Carbon::parse('2026-10-26')));
        $this->assertSame([], $booking->slots($host->team_id, Carbon::parse('2026-10-27')));
    }

    public function test_booking_creates_the_room_and_pastes_the_link_on_the_event(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-26 08:00:00', 'Europe/Madrid'));
        $host = $this->hostContact();
        $host->weeklyAvailability()->create([
            'team_id' => $host->team_id,
            'monday' => true,
            'tuesday' => true,
            'wednesday' => true,
            'thursday' => true,
            'friday' => true,
            'saturday' => false,
            'sunday' => false,
        ]);

        $booked = app(MeetBookingService::class)->book(
            $host->team_id,
            'Ana López',
            'ana@example.com',
            '2026-10-26 10:00',
        );

        $event = CalendarEvent::withoutGlobalScopes()->findOrFail($booked['event_id']);

        $this->assertSame($booked['url'], $event->url);
        $this->assertMatchesRegularExpression('#https://revisionalpha.test/meet/ra-[a-f0-9]{12}$#', $event->url);
        $this->assertTrue($event->guests()->where('email', 'ana@example.com')->exists());
    }

    public function test_team_token_can_list_slots(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-26 08:00:00', 'Europe/Madrid'));
        $host = $this->hostContact();
        $token = bin2hex(random_bytes(16));
        $host->team->setSetting('api_token_hash', hash('sha256', $token), [
            'group' => 'api',
            'is_encrypted' => true,
        ]);

        $this->getJson('/api/team/meet/slots?date=2026-10-26', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('date', '2026-10-26')
            ->assertJsonStructure(['slots']);
    }

    private function hostContact(): Contact
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->assignRole('collaborator');

        return Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'creator_id' => $user->id,
            'name' => 'Host',
            'email' => 'host@example.com',
            'status_id' => 1,
        ]);
    }
}
