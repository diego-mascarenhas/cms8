<?php

namespace Tests\Unit;

use App\Models\Team;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_resolve_inbound_webhook_uses_route_team_when_present(): void
    {
        Config::set('services.twilio.whatsapp_from', '+5490000000000');

        $this->assertSame(42, Team::resolveInboundWebhookTeamId(42, '5491111111111'));
    }

    public function test_resolve_inbound_webhook_matches_demo_line_via_whatsapp_inbound_number(): void
    {
        Config::set('humano-core.whatsapp_demo_line_team_id', 1);
        Config::set('humano-core.whatsapp_inbound_number_digits', '5491112223333');
        Config::set('services.twilio.whatsapp_from', null);

        $this->assertSame(1, Team::resolveInboundWebhookTeamId(null, '5491112223333'));
    }

    public function test_resolve_inbound_webhook_falls_back_to_twilio_from_when_inbound_not_set(): void
    {
        Config::set('humano-core.whatsapp_demo_line_team_id', 1);
        Config::set('humano-core.whatsapp_inbound_number_digits', null);
        Config::set('services.twilio.whatsapp_from', '+5491112223333');

        $this->assertSame(1, Team::resolveInboundWebhookTeamId(null, '5491112223333'));
    }

    #[DataProvider('invalidToProvider')]
    public function test_resolve_inbound_webhook_returns_null_without_match(?int $route, string $to): void
    {
        Config::set('services.twilio.whatsapp_from', '+5499999999999');

        $this->assertNull(Team::resolveInboundWebhookTeamId($route, $to));
    }

    /**
     * @return array<string, array{0: int|null, 1: string}>
     */
    public static function invalidToProvider(): array
    {
        return [
            'empty to' => [null, ''],
            'short to' => [null, '123'],
            'no route and wrong to' => [null, '5488776655443'],
        ];
    }
}
