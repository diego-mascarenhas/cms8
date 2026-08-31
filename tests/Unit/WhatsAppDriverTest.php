<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\WhatsAppDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class WhatsAppDriverTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_defaults_to_local_for_empty_or_unknown_values(): void
    {
        $this->assertSame(WhatsAppDriver::LOCAL, WhatsAppDriver::normalize(null));
        $this->assertSame(WhatsAppDriver::LOCAL, WhatsAppDriver::normalize(''));
        $this->assertSame(WhatsAppDriver::LOCAL, WhatsAppDriver::normalize('unknown'));
        $this->assertSame(WhatsAppDriver::META, WhatsAppDriver::normalize('META'));
        $this->assertSame(WhatsAppDriver::DIALOG360, WhatsAppDriver::normalize('360dialog'));
        $this->assertSame(WhatsAppDriver::MESSAGEBIRD, WhatsAppDriver::normalize('messagebird'));
        $this->assertSame(WhatsAppDriver::TWILIO, WhatsAppDriver::normalize('TWILIO'));
        $this->assertSame(WhatsAppDriver::LOCAL, WhatsAppDriver::normalize(' local '));
    }

    public function test_team_defaults_to_baileys_even_when_global_config_is_twilio(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        config(['whatsapp.driver' => 'twilio']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $this->assertSame(WhatsAppDriver::LOCAL, $team->getWhatsAppDriver());
        $this->assertTrue($team->usesLocalWhatsApp());
        $this->assertTrue(WhatsAppDriver::isLocal($team));
    }

    public function test_team_setting_overrides_default_to_twilio(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->setSetting(WhatsAppDriver::SETTING_KEY, WhatsAppDriver::TWILIO, ['group' => 'chat']);

        $this->assertSame(WhatsAppDriver::TWILIO, $team->fresh()->getWhatsAppDriver());
        $this->assertFalse($team->fresh()->usesLocalWhatsApp());
        $this->assertFalse(WhatsAppDriver::isLocal($team->fresh()));
    }

    public function test_meta_cloud_api_is_an_official_channel_not_baileys(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->setSetting(WhatsAppDriver::SETTING_KEY, WhatsAppDriver::META, ['group' => 'chat']);

        $this->assertSame(WhatsAppDriver::META, $team->fresh()->getWhatsAppDriver());
        $this->assertFalse($team->fresh()->usesLocalWhatsApp());
        $this->assertFalse(WhatsAppDriver::isImplemented($team->fresh()));
        $this->assertArrayHasKey(WhatsAppDriver::META, WhatsAppDriver::selectOptions());
        $this->assertArrayHasKey(WhatsAppDriver::DIALOG360, WhatsAppDriver::selectOptions());
        $this->assertArrayHasKey(WhatsAppDriver::MESSAGEBIRD, WhatsAppDriver::selectOptions());
    }
}
