<?php

namespace Tests\Feature;

use App\Enums\TeamBillingProduct;
use App\Models\Conversation;
use App\Models\Team;
use App\Models\TeamBillingRate;
use App\Models\User;
use App\Services\TeamWhatsAppUsageStatsService;
use App\Services\TokenBillingRateService;
use App\Support\MailerPaygPricing;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class TeamBillingRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_by_team_overrides_the_platform_default(): void
    {
        $team = $this->team();
        config(['humano_pricing.token_billing.client_token_multiplier_by_team' => [$team->id => 5]]);

        $this->assertSame(5.0, TokenBillingRateService::clientTokenMultiplier($team));
        $this->assertSame(10.0, TokenBillingRateService::clientTokenMultiplier());
    }

    public function test_changing_a_rate_does_not_reprice_usage_before_the_change(): void
    {
        $team = $this->team();
        $changedAt = Carbon::parse('2026-09-01 12:00:00');

        TeamBillingRate::setAmount((int) $team->id, TeamBillingProduct::TokensMultiplier, 5, $changedAt);

        $this->assertSame(10.0, TokenBillingRateService::clientTokenMultiplier($team, $changedAt->copy()->subDay()));
        $this->assertSame(5.0, TokenBillingRateService::clientTokenMultiplier($team, $changedAt));
        $this->assertSame(5.0, TokenBillingRateService::clientTokenMultiplier($team, $changedAt->copy()->addDay()));
        $this->assertSame(2, TeamBillingRate::query()->where('team_id', $team->id)->count());
    }

    public function test_whatsapp_windows_keep_the_old_send_price_on_earlier_messages(): void
    {
        $team = $this->team();
        $team->setSetting('whatsapp_from', '34999000111');
        $changedAt = now()->subDay();

        $old = Conversation::query()->create([
            'message_sid' => 'SM_rate_old',
            'channel' => 'whatsapp',
            'from' => '34999000111',
            'to' => '34600111222',
            'body' => 'Antes',
            'status' => 'sent',
            'direction' => 'outbound',
        ]);
        $old->forceFill(['created_at' => $changedAt->copy()->subHour()])->save();
        $new = Conversation::query()->create([
            'message_sid' => 'SM_rate_new',
            'channel' => 'whatsapp',
            'from' => '34999000111',
            'to' => '34600111222',
            'body' => 'Después',
            'status' => 'sent',
            'direction' => 'outbound',
        ]);
        $new->forceFill(['created_at' => $changedAt->copy()->addHour()])->save();

        TeamBillingRate::setAmount((int) $team->id, TeamBillingProduct::WhatsappSend, 0.01, $changedAt);

        $stats = TeamWhatsAppUsageStatsService::forTeam($team, now()->subDays(2), now());

        $this->assertSame(2, $stats['messages_sent']);
        $this->assertSame(1, $stats['our_amount_cents']);
        $this->assertEqualsWithDelta(0.01, $stats['our_rate'], 0.000001);
    }

    public function test_format_amount_is_the_shared_display_string(): void
    {
        $this->assertSame('10', TeamBillingRate::formatAmount(10.0));
        $this->assertSame('8', TeamBillingRate::formatAmount(8.000000));
        $this->assertSame('0.003', TeamBillingRate::formatAmount(0.003));
        $this->assertSame('0.01', TeamBillingRate::formatAmount(0.01));
        $this->assertSame('0', TeamBillingRate::formatAmount(0.0));

        $team = $this->team();
        $rate = TeamBillingRate::setAmount((int) $team->id, TeamBillingProduct::WhatsappSend, 0.003);

        $this->assertSame('0.003', $rate->formattedAmount());
        $this->assertSame('0.003', TeamBillingRate::formattedAmountOn((int) $team->id, TeamBillingProduct::WhatsappSend));
        $this->assertSame('10', TeamBillingRate::formattedAmountOn((int) $team->id, TeamBillingProduct::TokensMultiplier));
        $this->assertSame('0.002', TeamBillingRate::formattedAmountOn((int) $team->id, TeamBillingProduct::MailerSend));
    }

    public function test_mailer_price_follows_the_team_rate_in_effect(): void
    {
        $team = $this->team();
        config(['emailer.payg.price_per_email_by_team' => [$team->id => '0.02']]);

        $this->assertSame('0.02', MailerPaygPricing::pricePerEmail($team));
        $this->assertSame(400, MailerPaygPricing::overageDueCents(200, $team));

        TeamBillingRate::setAmount((int) $team->id, TeamBillingProduct::MailerSend, 0.005, now()->subHour());

        $this->assertSame('0.005', MailerPaygPricing::pricePerEmail($team));
        $this->assertSame('0.02', MailerPaygPricing::pricePerEmail($team, now()->subDay()));
    }

    private function team(): Team
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();

        return $user->ownedTeams()->first();
    }
}
