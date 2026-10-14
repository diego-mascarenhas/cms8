<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\MessageTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NewsContactStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            MessageTypeSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->user = User::factory()->withPersonalTeam()->create();
        $team = $this->user->ownedTeams()->first();
        $this->user->forceFill(['current_team_id' => $team->id])->save();
        $this->user->assignRole('admin');
    }

    public function test_a_link_click_moves_a_lead_to_follow_up_and_keeps_the_news_variable(): void
    {
        $contact = $this->contact('Lead');
        $delivery = $this->delivery($contact);
        $token = $delivery->getTrackingToken();

        $this->get('/message/track/click/'.$token.'?url='.urlencode('https://example.com/oferta'))
            ->assertRedirect('https://example.com/oferta?news='.$token);

        $this->assertSame($this->statusId('En seguimiento'), (int) $contact->fresh()->status_id);
    }

    public function test_a_link_click_does_not_change_a_client(): void
    {
        $contact = $this->contact('Cliente');
        $delivery = $this->delivery($contact);

        $this->get('/message/track/click/'.$delivery->getTrackingToken().'?url='.urlencode('https://example.com/oferta'))
            ->assertRedirect();

        $this->assertSame($this->statusId('Cliente'), (int) $contact->fresh()->status_id);
    }

    public function test_a_later_click_does_not_move_a_conversion_back_to_follow_up(): void
    {
        $contact = $this->contact('Conversión');
        $delivery = $this->delivery($contact);

        $this->get('/message/track/click/'.$delivery->getTrackingToken().'?url='.urlencode('https://example.com/oferta'));

        $this->assertSame($this->statusId('Conversión'), (int) $contact->fresh()->status_id);
    }

    public function test_completing_the_cta_marks_a_conversion(): void
    {
        $contact = $this->contact('En seguimiento');
        $delivery = $this->delivery($contact);
        $token = $delivery->getTrackingToken();

        $this->get('/message/track/conversion?news='.$token)
            ->assertOk()
            ->assertHeader('content-type', 'image/gif');

        $this->assertSame($this->statusId('Conversión'), (int) $contact->fresh()->status_id);
    }

    public function test_a_form_post_with_the_news_variable_marks_a_conversion(): void
    {
        $contact = $this->contact('Lead');
        $delivery = $this->delivery($contact);

        $this->post('/message/track/conversion', ['news' => $delivery->getTrackingToken()])
            ->assertNoContent();

        $this->assertSame($this->statusId('Conversión'), (int) $contact->fresh()->status_id);
    }

    public function test_unsubscribe_marks_the_contact_as_lost_and_leaves_clients(): void
    {
        $lead = $this->contact('Lead', 'baja@example.test');
        $client = $this->contact('Cliente', 'cliente@example.test');

        $this->get('/unsubscribe/'.$lead->email)->assertOk();
        $this->get('/unsubscribe/'.$client->email)->assertOk();

        $this->assertSame($this->statusId('Perdido'), (int) $lead->fresh()->status_id);
        $this->assertSame($this->statusId('Cliente'), (int) $client->fresh()->status_id);
    }

    private function contact(string $status, string $email = 'lead@example.test'): Contact
    {
        return Contact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'creator_id' => $this->user->id,
            'responsible_id' => $this->user->id,
            'email' => $email,
            'status_id' => $this->statusId($status),
        ]);
    }

    private function delivery(Contact $contact): MessageDelivery
    {
        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'News',
            'text' => 'Una novedad',
            'type_id' => 1,
            'status_id' => 1,
            'mail_html' => '<p>Hola</p>',
            'enable_click_tracking' => true,
            'enable_open_tracking' => false,
        ]);

        return MessageDelivery::query()->create([
            'team_id' => $this->user->currentTeam->id,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'status_id' => 2,
            'sent_at' => now(),
        ]);
    }

    private function statusId(string $name): int
    {
        return (int) ContactStatus::query()->where('name', $name)->value('id');
    }
}
