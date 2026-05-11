<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\MessageDelivery;
use App\Models\Team;
use Database\Seeders\DemoMailCampaignData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoMailCampaignDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_mail_seeds_twelve_recipients_per_campaign_and_newsletter_deliveries(): void
    {
        $this->seed();

        $team = Team::withoutGlobalScopes()->where('name', 'Demo')->first();
        $this->assertNotNull($team);

        $broadcast = Campaign::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('name', '[Demo] Campaña difusión')
            ->first();
        $this->assertNotNull($broadcast);

        $sequence = Campaign::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('name', '[Demo] Campaña secuencia (2 pasos)')
            ->first();
        $this->assertNotNull($sequence);

        $broadcastStats = $broadcast->deliveryStatistics();
        $this->assertSame(DemoMailCampaignData::DEMO_NEWSLETTER_CONTACT_COUNT, $broadcastStats['unique_recipients']);
        $this->assertSame(DemoMailCampaignData::DEMO_NEWSLETTER_CONTACT_COUNT, $broadcastStats['total']);

        $sequenceStats = $sequence->deliveryStatistics();
        $this->assertSame(DemoMailCampaignData::DEMO_NEWSLETTER_CONTACT_COUNT, $sequenceStats['unique_recipients']);
        $this->assertSame(
            DemoMailCampaignData::DEMO_NEWSLETTER_CONTACT_COUNT * 2,
            $sequenceStats['total'],
        );

        $standalone = \App\Models\Message::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('name', '[Demo] Mensaje suelto (newsletter)')
            ->first();
        $this->assertNotNull($standalone);

        $standaloneDeliveriesCount = MessageDelivery::query()
            ->where('message_id', $standalone->id)
            ->whereNull('campaign_id')
            ->count();
        $this->assertSame(DemoMailCampaignData::DEMO_NEWSLETTER_CONTACT_COUNT, $standaloneDeliveriesCount);
    }
}
