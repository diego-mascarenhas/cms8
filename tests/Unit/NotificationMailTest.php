<?php

namespace Tests\Unit;

use App\Mail\NotificationMail;
use App\Models\Contact;
use App\Models\Notification;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\NotificationTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationMailTest extends TestCase
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

    public function test_default_notification_uses_html_template(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $contact = Contact::factory()->create(['team_id' => $team->id]);

        $notification = Notification::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 3,
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'subject' => 'Aviso',
            'message' => 'Contenido HTML',
        ]);

        $notification->load(['contact', 'user', 'team']);

        $mailable = new NotificationMail($notification);
        $rendered = $mailable->render();

        $this->assertStringContainsString('<html', strtolower($rendered));
    }
}
