<?php

namespace Tests\Feature;

use App\Mail\CalendarEventInvitationMail;
use App\Models\Contact;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CalendarEventInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'calendar.wall_clock_timezone' => 'Europe/Madrid',
            'services.revisionalpha.meet_url' => 'https://revisionalpha.test/meet',
            'mail.from.address' => 'agenda@example.test',
            'mail.from.name' => 'Agenda',
        ]);
        $this->seed([ContactStatusSeeder::class, CountrySeeder::class, LanguageSeeder::class]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_creating_a_meeting_emails_the_room_link_to_guests(): void
    {
        Mail::fake();
        $user = $this->calendarUser();
        $guest = $this->guest($user, 'ana@example.com', 'Ana');
        $this->guest($user, null, 'Sin correo');

        $response = $this->actingAs($user)->postJson(route('app-calendar-events-store'), [
            'title' => 'Revisión',
            'start' => '2026-10-26 10:00:00',
            'end' => '2026-10-26 11:00:00',
            'all_day' => false,
            'guests' => Contact::query()->pluck('id')->all(),
        ]);

        $response->assertCreated();
        $url = (string) $response->json('url');
        $this->assertMatchesRegularExpression('#https://revisionalpha.test/meet/ra-[a-f0-9]{12}$#', $url);

        Mail::assertSent(CalendarEventInvitationMail::class, function (CalendarEventInvitationMail $mail) use ($guest, $url)
        {
            $html = $mail->render();

            return $mail->hasTo($guest->email)
                && $mail->kind === 'created'
                && $mail->url === $url
                && str_contains($mail->when, '10:00')
                && str_contains($html, $url)
                && str_contains($html, 'Entrar a la sala')
                && str_contains($html, 'logo-header-email.png')
                && str_contains($html, '<em>Reunión</em>')
                && ! str_contains($html, '.svg');
        });
        Mail::assertSentCount(1);
    }

    public function test_changing_the_schedule_emails_the_new_time(): void
    {
        Mail::fake();
        $user = $this->calendarUser();
        $guest = $this->guest($user, 'ana@example.com', 'Ana');

        $created = $this->actingAs($user)->postJson(route('app-calendar-events-store'), [
            'title' => 'Revisión',
            'start' => '2026-10-26 10:00:00',
            'end' => '2026-10-26 11:00:00',
            'all_day' => false,
            'guests' => [$guest->id],
        ]);
        $created->assertCreated();

        $this->putJson(route('app-calendar-events-update', $created->json('id')), [
            'title' => 'Revisión comercial',
            'start' => '2026-10-26 10:00:00',
            'end' => '2026-10-26 11:00:00',
            'url' => $created->json('url'),
            'guests' => [$guest->id],
        ])->assertOk();

        Mail::assertSentCount(1);

        $this->putJson(route('app-calendar-events-update', $created->json('id')), [
            'title' => 'Revisión comercial',
            'start' => '2026-10-26 12:00:00',
            'end' => '2026-10-26 13:00:00',
            'url' => '',
            'guests' => [$guest->id],
        ])->assertOk();

        Mail::assertSent(CalendarEventInvitationMail::class, function (CalendarEventInvitationMail $mail) use ($created)
        {
            $html = $mail->render();

            return $mail->kind === 'updated'
                && $mail->hasTo('ana@example.com')
                && $mail->url === $created->json('url')
                && str_contains($mail->when, '12:00')
                && str_contains($mail->when, '13:00')
                && str_contains($html, 'Nuevo horario');
        });
        Mail::assertSentCount(2);
    }

    private function calendarUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');

        return $user->refresh();
    }

    private function guest(User $user, ?string $email, string $name): Contact
    {
        return Contact::factory()->create([
            'team_id' => $user->currentTeam->id,
            'creator_id' => $user->id,
            'name' => $name,
            'email' => $email,
            'status_id' => 1,
        ]);
    }
}
