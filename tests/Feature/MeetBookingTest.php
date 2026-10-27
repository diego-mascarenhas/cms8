<?php

namespace Tests\Feature;

use App\Mail\CalendarEventInvitationMail;
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
use Illuminate\Support\Facades\Mail;
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
        $slots = $booking->slots($host->team_id, Carbon::parse('2026-10-26'));

        $this->assertSame(
            ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'],
            array_column($slots, 'label'),
        );
        $this->assertSame('2026-10-26 09:45', $slots[0]['end']);
        $this->assertSame([], $booking->slots($host->team_id, Carbon::parse('2026-10-27')));
    }

    public function test_slots_require_one_hour_of_notice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-26 08:01:00', 'Europe/Madrid'));
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

        $labels = array_column(
            app(MeetBookingService::class)->slots($host->team_id, Carbon::parse('2026-10-26')),
            'label',
        );

        $this->assertNotContains('09:00', $labels);
        $this->assertSame('10:00', $labels[0]);
    }

    public function test_closed_days_are_left_out_of_the_month(): void
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

        $dates = app(MeetBookingService::class)->openDates($host->team_id, Carbon::parse('2026-11-01'));

        $this->assertNotContains('2026-11-01', $dates);
        $this->assertContains('2026-11-02', $dates);
        $this->assertNotContains('2026-11-03', $dates);
        $this->assertNotContains('2026-11-07', $dates);
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
        $this->assertSame(
            Carbon::parse($booked['start'])->addMinutes(45)->format('Y-m-d H:i'),
            $booked['end'],
        );
        $this->assertMatchesRegularExpression('#https://revisionalpha.test/meet/ra-[a-f0-9]{12}$#', $event->url);
        $this->assertTrue($event->guests()->where('email', 'ana@example.com')->exists());
    }

    public function test_booking_copies_the_confirmation_to_administrators(): void
    {
        Mail::fake();
        config([
            'mail.from.address' => 'agenda@example.test',
            'mail.from.name' => 'Agenda',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-10-26 08:00:00', 'Europe/Madrid'));
        $host = $this->hostContact();
        $host->user->forceFill(['email' => 'owner@example.com'])->save();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $admin->assignRole('admin');
        $host->team->users()->attach($admin->id, ['role' => 'admin']);
        $collaborator = User::factory()->create(['email' => 'colab@example.com']);
        $collaborator->assignRole('collaborator');
        $host->team->users()->attach($collaborator->id, ['role' => 'editor']);
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

        app(MeetBookingService::class)->book(
            $host->team_id,
            'Ana López',
            'ana@example.com',
            '2026-10-26 10:00',
        );

        Mail::assertSent(CalendarEventInvitationMail::class, function (CalendarEventInvitationMail $mail): bool
        {
            return $mail->hasTo('ana@example.com')
                && $mail->hasBcc('owner@example.com')
                && $mail->hasBcc('admin@example.com')
                && ! $mail->hasBcc('colab@example.com');
        });
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
