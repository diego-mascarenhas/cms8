<?php

namespace Tests\Feature;

use App\Enums\EmailPlan;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class MailerPaygBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }
    }

    public function test_paid_plan_caps_subscribers_and_allows_email_overage(): void
    {
        $team = Team::factory()->create();
        $team->assignEmailPlan(EmailPlan::BASIC, null);
        $team->setSetting('email_monthly_used', 10000, ['type' => 'integer', 'group' => 'email']);
        $team->setSetting('email_daily_used', 500, ['type' => 'integer', 'group' => 'email']);

        $this->assertTrue($team->fresh()->allowsMailerOverage());
        $this->assertTrue($team->fresh()->canSendEmails(1));
        $this->assertTrue($team->fresh()->recordSuccessfulMailerSend(1));

        $usage = $team->fresh()->getMailerUsageSummary();
        $this->assertSame(3000, $usage['subscribers_limit']);
        $this->assertSame(10000, $usage['emails_included']);
        $this->assertTrue($usage['allows_overage']);
    }

    public function test_free_plan_blocks_when_email_quota_is_exhausted(): void
    {
        $team = Team::factory()->create();
        $team->assignEmailPlan(EmailPlan::FREE, null);
        $team->setSetting('email_monthly_used', 2000, ['type' => 'integer', 'group' => 'email']);
        $team->setSetting('email_daily_used', 100, ['type' => 'integer', 'group' => 'email']);

        $this->assertFalse($team->fresh()->allowsMailerOverage());
        $this->assertFalse($team->fresh()->canSendEmails(1));
        $this->assertSame(0, $team->fresh()->getMailerOverageEmails());
    }
}
