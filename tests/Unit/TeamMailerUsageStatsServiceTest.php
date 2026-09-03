<?php

namespace Tests\Unit;

use App\Models\MailerUsageLog;
use App\Models\MessageDelivery;
use App\Models\User;
use App\Services\TeamMailerUsageStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class TeamMailerUsageStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_sent_emails_in_the_billing_window(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        config(['emailer.payg.price_per_email' => 0.01]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $this->assertNotNull($team);

        $this->createDelivery((int) $team->id, 1, now());
        $this->createDelivery((int) $team->id, 2, now());
        $this->createDelivery((int) $team->id, 3, null);
        $this->createDelivery((int) $team->id, 4, now()->subMonth());
        MailerUsageLog::factory()->create([
            'team_id' => $team->id,
            'count' => 2,
            'sent_at' => now(),
        ]);

        $all = TeamMailerUsageStatsService::forTeam($team);
        $this->assertSame(5, $all['emails_sent']);
        $this->assertSame(5, $all['amount_due_cents']);
        $this->assertSame(0.01, $all['our_rate']);
        $this->assertSame('EUR', $all['currency']);

        $period = TeamMailerUsageStatsService::forTeam($team, now()->startOfMonth(), now()->endOfMonth());
        $this->assertSame(4, $period['emails_sent']);
        $this->assertSame(4, $period['amount_due_cents']);
    }

    public function test_returns_zero_when_the_team_has_no_sent_emails(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $this->assertNotNull($team);

        $stats = TeamMailerUsageStatsService::forTeam($team);
        $this->assertSame(0, $stats['emails_sent']);
        $this->assertSame(0, $stats['amount_due_cents']);
    }

    private function createDelivery(int $teamId, int $contactId, mixed $sentAt): MessageDelivery
    {
        return MessageDelivery::create([
            'team_id' => $teamId,
            'message_id' => 1,
            'contact_id' => $contactId,
            'sent_at' => $sentAt,
            'status_id' => $sentAt ? 2 : 1,
            'delivery_status' => $sentAt ? 'sent' : 'pending',
        ]);
    }
}
