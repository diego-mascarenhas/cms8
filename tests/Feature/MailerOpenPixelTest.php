<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\MessageTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailerOpenPixelTest extends TestCase
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
    }

    public function test_smtp_html_includes_the_open_pixel_and_the_api_html_does_not(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Newsletter',
            'type_id' => 1,
            'text' => 'Hello',
            'team_id' => $team->id,
            'status_id' => 1,
            'enable_open_tracking' => true,
        ]);
        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'reader@acme.test',
        ]);
        $delivery = MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'status_id' => 1,
        ]);

        $pixel = $delivery->getTrackingUrl();

        $this->assertStringContainsString($pixel, $delivery->getHtmlForContact());
        $this->assertStringNotContainsString($pixel, $delivery->getHtmlForContact(false));
    }
}
