<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageUnsubscribeTest extends TestCase
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
    }

    public function test_get_unsubscribe_marks_contact_as_lost_and_shows_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $lostId = (int) ContactStatus::query()->where('name', 'Perdido')->value('id');
        $leadId = (int) ContactStatus::query()->where('name', 'Lead')->value('id');

        $contact = Contact::factory()->create([
            'team_id' => $user->currentTeam->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'reader@example.test',
            'status_id' => $leadId,
        ]);

        $this->get('/unsubscribe/'.rawurlencode('reader@example.test'))
            ->assertOk()
            ->assertViewIs('message.unsubscribe');

        $this->assertSame($lostId, (int) $contact->fresh()->status_id);
    }

    public function test_post_one_click_unsubscribe_marks_contact_as_lost(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $lostId = (int) ContactStatus::query()->where('name', 'Perdido')->value('id');
        $leadId = (int) ContactStatus::query()->where('name', 'Lead')->value('id');

        $contact = Contact::factory()->create([
            'team_id' => $user->currentTeam->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'reader@example.test',
            'status_id' => $leadId,
        ]);

        $this->post('/unsubscribe/'.rawurlencode('reader@example.test'), [
            'List-Unsubscribe' => 'One-Click',
        ])->assertOk();

        $this->assertSame($lostId, (int) $contact->fresh()->status_id);
    }

    public function test_unsubscribe_does_not_change_client_status(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $clientId = (int) ContactStatus::query()->where('name', 'Cliente')->value('id');

        $contact = Contact::factory()->create([
            'team_id' => $user->currentTeam->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'client@example.test',
            'status_id' => $clientId,
        ]);

        $this->post('/unsubscribe/'.rawurlencode('client@example.test'))->assertOk();

        $this->assertSame($clientId, (int) $contact->fresh()->status_id);
    }
}
