<?php

namespace Tests\Feature;

use App\Mail\MeetKnockMail;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use App\Services\MeetKnockService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MeetKnockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.revisionalpha.meet_url' => 'https://revisionalpha.test/meet',
            'mail.from.address' => 'hola@revisionalpha.test',
            'mail.from.name' => 'REVISION ALPHA',
        ]);
        $this->seed([ContactStatusSeeder::class, CountrySeeder::class, LanguageSeeder::class]);
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Mail::fake();
    }

    public function test_a_walk_in_request_emails_team_managers_until_someone_joins(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30 11:00:00', 'Europe/Madrid'));
        $host = $this->hostContact();
        $host->user->forceFill(['email' => 'owner@example.com'])->save();
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $admin->assignRole('admin');
        $host->team->users()->attach($admin->id, ['role' => 'admin']);
        $collaborator = User::factory()->create(['email' => 'colab@example.com']);
        $collaborator->assignRole('collaborator');
        $host->team->users()->attach($collaborator->id, ['role' => 'editor']);

        $knocks = app(MeetKnockService::class);
        $created = $knocks->request($host->team_id, '  Ana   López ', 'ana@example.com');

        $this->assertSame('waiting', $created['status']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $created['id']);
        $this->assertMatchesRegularExpression('/^ra-[a-f0-9]{12}$/', $created['room']);
        Mail::assertSent(MeetKnockMail::class, function (MeetKnockMail $mail) use ($created): bool
        {
            return $mail->guestName === 'Ana López'
                && $mail->hasTo('owner@example.com')
                && $mail->hasTo('admin@example.com')
                && ! $mail->hasTo('colab@example.com')
                && $mail->joinUrl === 'https://revisionalpha.test/meet/'.$created['room'].'?entrar='.$created['token'];
        });

        $this->assertSame(['status' => 'waiting'], $knocks->status($host->team_id, $created['id']));
        Mail::assertSent(MeetKnockMail::class, 1);

        Carbon::setTestNow(Carbon::parse('2026-10-30 11:03:00', 'Europe/Madrid'));
        $this->assertSame(['status' => 'waiting'], $knocks->status($host->team_id, $created['id']));
        Mail::assertSent(MeetKnockMail::class, 2);

        $admitted = $knocks->admit($host->team_id, $created['token']);
        $this->assertSame([
            'status' => 'joined',
            'room' => $created['room'],
            'name' => 'Ana López',
        ], $admitted);

        Carbon::setTestNow(Carbon::parse('2026-10-30 11:06:00', 'Europe/Madrid'));
        $this->assertSame([
            'status' => 'joined',
            'room' => $created['room'],
        ], $knocks->status($host->team_id, $created['id']));
        Mail::assertSent(MeetKnockMail::class, 2);
    }

    public function test_a_request_expires_and_stops_emailing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30 11:00:00', 'Europe/Madrid'));
        $host = $this->hostContact();
        $knocks = app(MeetKnockService::class);
        $created = $knocks->request($host->team_id, 'Ana', 'ana@example.com');

        Carbon::setTestNow(Carbon::parse('2026-10-30 11:12:00', 'Europe/Madrid'));

        $this->assertSame(['status' => 'expired'], $knocks->status($host->team_id, $created['id']));
        $this->assertNull($knocks->admit($host->team_id, $created['token']));
        Mail::assertSent(MeetKnockMail::class, 1);
    }

    public function test_knock_email_uses_the_revision_alpha_app_url(): void
    {
        config([
            'services.revisionalpha.url' => 'https://revisionalpha.com',
            'services.revisionalpha.meet_url' => 'https://revisionalpha.com/meet',
        ]);
        $host = $this->hostContact();

        $created = app(MeetKnockService::class)->request($host->team_id, 'Ana', 'ana@example.com', 'https://revisionalpha.com');

        Mail::assertSent(MeetKnockMail::class, function (MeetKnockMail $mail) use ($created): bool
        {
            $html = $mail->render();

            return $mail->joinUrl === 'https://revisionalpha.com/meet/'.$created['room'].'?entrar='.$created['token']
                && str_contains($html, 'https://revisionalpha.com/assets/img/logo-header-email.png')
                && str_contains($html, 'https://revisionalpha.com/assets/img/logo-footer-email.png')
                && ! str_contains($html, 'revisionalpha.test/assets/img/');
        });

        $event = CalendarEvent::withoutGlobalScopes()
            ->where('url', 'https://revisionalpha.com/meet/'.$created['room'])
            ->first();
        $this->assertNotNull($event);
        $this->assertSame('Reunión ahora Ana', $event->title);
        $this->assertTrue($event->guests()->withoutGlobalScopes()->where('contacts.email', 'ana@example.com')->exists());

        $again = app(MeetKnockService::class)->request($host->team_id, 'Ana', 'ana@example.com', 'https://revisionalpha.com');
        $this->assertNotSame($created['room'], $again['room']);
        $this->assertSame(1, Contact::withoutGlobalScopes()->where('team_id', $host->team_id)->where('email', 'ana@example.com')->count());
    }

    public function test_team_token_can_request_and_admit_a_walk_in_meeting(): void
    {
        $host = $this->hostContact();
        $host->user->forceFill(['email' => 'owner@example.com'])->save();
        $token = bin2hex(random_bytes(16));
        $host->team->setSetting('api_token_hash', hash('sha256', $token), [
            'group' => 'api',
            'is_encrypted' => true,
        ]);
        $headers = ['Authorization' => 'Bearer '.$token];

        $created = $this->postJson('/api/team/meet/knocks', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('status', 'waiting');

        $id = $created->json('id');
        $this->assertIsString($id);
        $this->assertArrayNotHasKey('token', $created->json());
        $this->assertArrayNotHasKey('room', $created->json());

        $this->getJson('/api/team/meet/knocks/'.$id, $headers)
            ->assertOk()
            ->assertExactJson(['status' => 'waiting']);

        $knock = Mail::sent(MeetKnockMail::class)->first();
        $this->assertInstanceOf(MeetKnockMail::class, $knock);
        parse_str((string) parse_url($knock->joinUrl, PHP_URL_QUERY), $query);

        $this->postJson('/api/team/meet/knocks/admit', [
            'token' => $query['entrar'],
        ], $headers)->assertOk()
            ->assertJsonPath('status', 'joined')
            ->assertJsonPath('name', 'Ana');

        $this->getJson('/api/team/meet/knocks/'.$id, $headers)
            ->assertOk()
            ->assertJsonPath('status', 'joined');
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
