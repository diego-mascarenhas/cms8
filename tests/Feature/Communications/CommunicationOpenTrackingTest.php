<?php

namespace Tests\Feature\Communications;

use App\Models\Communication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class CommunicationOpenTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pixel_marks_communication_opened_once(): void
    {
        $communication = $this->sentCommunication();

        $this->get(route('communications.track.open', ['token' => $communication->trackingToken()]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif');

        $communication->refresh();
        $this->assertTrue($communication->hasOpened());
        $this->assertSame(1, collect($communication->events())->where('type', 'opened')->count());

        $this->get(route('communications.track.open', ['token' => $communication->trackingToken()]))
            ->assertOk();

        $this->assertSame(1, collect($communication->fresh()->events())->where('type', 'opened')->count());
    }

    public function test_invalid_token_still_returns_pixel(): void
    {
        $this->get(route('communications.track.open', ['token' => '9.'.str_repeat('a', 32)]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif');
    }

    public function test_mailbaby_open_webhook_marks_communication(): void
    {
        $communication = $this->sentCommunication();
        $communication->storeProviderTracking('mailbaby', '1a0b9f105f6000dfc3');

        $this->postJson('/webhooks/mailbaby', [
            'event' => 'opened',
            'id' => '1a0b9f105f6000dfc3',
        ])->assertOk();

        $this->assertTrue($communication->fresh()->hasOpened());
    }

    private function sentCommunication(): Communication
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        return Communication::factory()->forTeamAndUser($team, $user)->email()->sent()->create([
            'subject' => 'Test 3',
            'recipient_email' => 'administracion@revisionalpha.com',
        ]);
    }
}
