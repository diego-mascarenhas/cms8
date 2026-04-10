<?php

namespace Tests\Unit;

use App\Models\Team;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TeamWhatsAppDemoStoreTest extends TestCase
{
    public function test_resolve_returns_webhook_team_when_demo_store_not_configured(): void
    {
        Config::set('humano-core.whatsapp_demo_store_team_id', null);

        $this->assertSame(7, Team::resolveAssistantTeamIdForWhatsAppWebhook(7));
        $this->assertSame(1, Team::resolveAssistantTeamIdForWhatsAppWebhook(1));
    }

    public function test_resolve_maps_demo_line_to_store_team(): void
    {
        Config::set('humano-core.whatsapp_demo_line_team_id', 1);
        Config::set('humano-core.whatsapp_demo_store_team_id', 9);

        $this->assertSame(9, Team::resolveAssistantTeamIdForWhatsAppWebhook(1));
        $this->assertSame(5, Team::resolveAssistantTeamIdForWhatsAppWebhook(5));
    }

    /**
     * Changing WHATSAPP_DEMO_STORE_TEAM_ID only affects inbound on the demo line team.
     * Every other team's WhatsApp webhook keeps using that team's own data (e.g. after QR onboarding).
     */
    public function test_non_demo_line_teams_always_use_own_team_even_when_demo_store_is_set(): void
    {
        Config::set('humano-core.whatsapp_demo_line_team_id', 1);
        Config::set('humano-core.whatsapp_demo_store_team_id', 99);

        foreach ([2, 5, 42, 100] as $clientTeamId)
        {
            $this->assertSame(
                $clientTeamId,
                Team::resolveAssistantTeamIdForWhatsAppWebhook($clientTeamId),
                "Client team {$clientTeamId} must not follow demo store override",
            );
        }
    }

    public function test_custom_demo_line_team_id(): void
    {
        Config::set('humano-core.whatsapp_demo_line_team_id', 2);
        Config::set('humano-core.whatsapp_demo_store_team_id', 8);

        $this->assertSame(8, Team::resolveAssistantTeamIdForWhatsAppWebhook(2));
        $this->assertSame(1, Team::resolveAssistantTeamIdForWhatsAppWebhook(1));
    }
}
