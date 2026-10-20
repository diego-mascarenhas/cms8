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

class ClearRejectedEmailsCommandTest extends TestCase
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

    public function test_it_clears_emails_already_rejected_and_leaves_temporary_bounces(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Ya enviado',
            'text' => 'Subject line here',
            'type_id' => 1,
            'status_id' => 0,
            'mail_html' => '<p>Hi</p>',
        ]);
        $missing = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'name' => 'Sin buzón',
            'email' => 'gone@example.test',
        ]);
        $full = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'name' => 'Lleno',
            'email' => 'full@example.test',
        ]);
        $bounced = MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $message->id,
            'contact_id' => $missing->id,
            'status_id' => 4,
            'sent_at' => now()->subDay(),
            'error_message' => '550 No Such User Here',
        ]);
        $pending = MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $message->id,
            'contact_id' => $missing->id,
            'status_id' => 1,
            'scheduled_for' => now()->addHour(),
        ]);
        MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $message->id,
            'contact_id' => $full->id,
            'status_id' => 4,
            'sent_at' => now()->subDay(),
            'bounce_reason' => '452 4.2.2 Mailbox full',
        ]);

        $this->artisan('mailer:clear-rejected-emails', ['--dry-run' => true])
            ->expectsOutput('Would clear 1 rejected email.')
            ->assertSuccessful();

        $this->assertSame('gone@example.test', $missing->fresh()->email);

        $this->artisan('mailer:clear-rejected-emails')
            ->expectsOutput('Cleared 1 rejected email.')
            ->assertSuccessful();

        $missing->refresh();
        $this->assertNull($missing->email);
        $this->assertSame('gone@example.test', data_get($missing->data, 'removed_email'));
        $this->assertNull(MessageDelivery::query()->find($pending->id));
        $this->assertNotNull(MessageDelivery::query()->find($bounced->id));
        $this->assertSame('full@example.test', $full->fresh()->email);
    }
}
