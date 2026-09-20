<?php

namespace Tests\Feature\Communications;

use App\Models\Communication;
use App\Models\User;
use App\Services\Communications\CommunicationLinkTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class CommunicationClickTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_plain_urls_are_wrapped_with_signed_click_links(): void
    {
        $communication = $this->sentCommunication(
            "Hola,\nAbrí https://idoneo.dev para ver el detalle.",
        );

        $html = $communication->trackedMessageHtml();

        $this->assertStringContainsString('/communications/track/'.$communication->trackingToken().'/click', $html);
        $this->assertStringContainsString('url='.urlencode('https://idoneo.dev'), $html);
        $this->assertStringContainsString('sig='.$communication->clickSignature('https://idoneo.dev'), $html);
        $this->assertStringNotContainsString('href="https://idoneo.dev"', $html);
    }

    public function test_each_click_is_recorded_and_redirects(): void
    {
        $communication = $this->sentCommunication('https://idoneo.dev');
        $url = $communication->clickTrackingUrl('https://idoneo.dev');

        $this->get($url)->assertRedirect('https://idoneo.dev');
        $this->get($url)->assertRedirect('https://idoneo.dev');

        $communication->refresh();
        $this->assertTrue($communication->hasOpened());
        $this->assertSame(
            ['https://idoneo.dev', 'https://idoneo.dev'],
            collect($communication->events())->where('type', 'clicked')->pluck('message')->all(),
        );
    }

    public function test_invalid_click_signature_is_rejected(): void
    {
        $communication = $this->sentCommunication('https://idoneo.dev');

        $this->get(route('communications.track.click', [
            'token' => $communication->trackingToken(),
            'url' => 'https://idoneo.dev',
            'sig' => str_repeat('a', 32),
        ]))->assertNotFound();

        $this->assertSame(0, collect($communication->fresh()->events())->where('type', 'clicked')->count());
    }

    public function test_javascript_urls_are_not_rewritten(): void
    {
        $tracker = app(CommunicationLinkTracker::class);

        $this->assertFalse($tracker->isSafeHttpUrl('javascript:alert(1)'));
        $this->assertFalse($tracker->isSafeHttpUrl('/relative'));
    }

    private function sentCommunication(string $message): Communication
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        return Communication::factory()->forTeamAndUser($team, $user)->email()->sent()->create([
            'subject' => 'Test de clics',
            'message' => $message,
            'recipient_email' => 'administracion@revisionalpha.com',
        ]);
    }
}
