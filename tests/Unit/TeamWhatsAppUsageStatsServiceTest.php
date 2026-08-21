<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\User;
use App\Services\TeamWhatsAppUsageStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class TeamWhatsAppUsageStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_billable_outbound_whatsapp_and_compares_to_reference_rate(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        config([
            'humano_pricing.whatsapp_message_billing.our_amount' => 0.10,
            'humano_pricing.whatsapp_message_billing.reference_amount' => 0.25,
            'humano_pricing.whatsapp_message_billing.currency' => 'EUR',
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $this->assertNotNull($team);
        $teamNumber = '34999000111';
        $team->setSetting('whatsapp_from', $teamNumber);

        $this->createMessage('SM_out_1', $teamNumber, '34600111222', 'outbound', 'sent');
        $this->createMessage('SM_out_2', $teamNumber, '34600111222', 'outbound', 'delivered');
        $this->createMessage('SM_out_failed', $teamNumber, '34600111222', 'outbound', 'failed');
        $this->createMessage('SM_in_1', '34600111222', $teamNumber, 'inbound', 'received');
        $this->createMessage('SM_other', '34999000999', '34600111222', 'outbound', 'sent');

        $previous = $this->createMessage('SM_out_old', $teamNumber, '34600111222', 'outbound', 'sent');
        $previous->forceFill(['created_at' => now()->subMonth()])->save();

        $all = TeamWhatsAppUsageStatsService::forTeam($team);
        $this->assertSame(3, $all['messages_sent']);
        $this->assertSame(30, $all['our_amount_cents']);
        $this->assertSame(75, $all['reference_amount_cents']);
        $this->assertSame(45, $all['saved_amount_cents']);
        $this->assertSame(60.0, $all['average_savings']);

        $period = TeamWhatsAppUsageStatsService::forTeam($team, now()->startOfMonth(), now()->endOfMonth());
        $this->assertSame(2, $period['messages_sent']);
        $this->assertSame(20, $period['our_amount_cents']);
    }

    public function test_returns_zero_when_team_has_no_whatsapp_number(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $this->assertNotNull($team);

        $this->createMessage('SM_orphan', '34999000111', '34600111222', 'outbound', 'sent');

        $stats = TeamWhatsAppUsageStatsService::forTeam($team);
        $this->assertSame(0, $stats['messages_sent']);
        $this->assertSame(0, $stats['our_amount_cents']);
    }

    private function createMessage(
        string $sid,
        string $from,
        string $to,
        string $direction,
        string $status,
    ): Conversation {
        return Conversation::create([
            'message_sid' => $sid,
            'channel' => 'whatsapp',
            'from' => $from,
            'to' => $to,
            'body' => 'Hola',
            'status' => $status,
            'direction' => $direction,
        ]);
    }
}
