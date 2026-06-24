<?php

namespace Tests\Unit;

use App\Jobs\SendNotificationJob;
use App\Mail\NotificationMail;
use App\Models\Contact;
use App\Models\Notification;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\NotificationTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            NotificationTypesSeeder::class,
        ]);
    }

    public function test_plain_text_notification_is_sent_without_html_mailable(): void
    {
        Mail::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'email' => 'cliente@example.com',
        ]);

        $notification = Notification::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 3,
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'subject' => 'Otro',
            'message' => "A ver si llega como texto plano,\n\nSaludos,\nDiego",
            'metadata' => ['format' => 'plain_text'],
        ]);

        (new SendNotificationJob($notification))->handle();

        Mail::assertNothingSent();
        Mail::assertSent(NotificationMail::class, 0);

        Mail::assertQueued(NotificationMail::class, 0);
    }

    public function test_html_notification_uses_notification_mailable(): void
    {
        Mail::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'email' => 'cliente@example.com',
        ]);

        $notification = Notification::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 3,
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'subject' => 'Aviso',
            'message' => 'Contenido HTML',
        ]);

        (new SendNotificationJob($notification))->handle();

        Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) use ($notification): bool
        {
            return $mail->notification->id === $notification->id;
        });
    }
}
