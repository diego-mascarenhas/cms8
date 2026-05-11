<?php

namespace Tests\Feature;

use App\Models\BillingAffiliateCommission;
use App\Models\Enterprise;
use App\Models\Team;
use App\Models\User;
use App\Services\AffiliateCommissionRecorder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingAffiliateEnterpriseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
        ]);
    }

    public function test_commission_recorded_when_referred_by_matches_referrer_enterprise_code(): void
    {
        $referrerOwner = User::factory()->create();
        $referrerTeam = Team::factory()->create(['user_id' => $referrerOwner->id]);
        $referrerOwner->forceFill(['current_team_id' => $referrerTeam->id])->save();
        $referrerTeam->setSetting('affiliate_commission_percent', '15', ['group' => 'affiliates', 'type' => 'string']);

        Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($referrerTeam->id)->create([
            'type_id' => 1,
            'code' => 'REF-PARTNER-1',
            'referred_by' => null,
            'payment_type_id' => null,
            'invoice_type_id' => null,
            'status_id' => 1,
        ]));

        $payingOwner = User::factory()->create();
        $payingTeam = Team::factory()->create(['user_id' => $payingOwner->id]);
        $payingOwner->forceFill(['current_team_id' => $payingTeam->id])->save();

        $customerId = 'cus_testpaying123';
        Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($payingTeam->id)->create([
            'type_id' => 1,
            'code' => $customerId,
            'referred_by' => 'REF-PARTNER-1',
            'payment_type_id' => null,
            'invoice_type_id' => null,
            'status_id' => 1,
        ]));

        app(AffiliateCommissionRecorder::class)->recordFromInvoice($payingTeam, [
            'id' => 'in_test_aff_1',
            'customer' => $customerId,
            'amount_paid' => 10000,
            'currency' => 'eur',
        ]);

        $this->assertDatabaseHas('billing_affiliate_commissions', [
            'stripe_invoice_id' => 'in_test_aff_1',
            'paying_team_id' => $payingTeam->id,
            'referrer_team_id' => $referrerTeam->id,
            'commission_amount_cents' => 1500,
        ]);
    }

    public function test_no_commission_when_referrer_is_same_team(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->forceFill(['current_team_id' => $team->id])->save();
        $team->setSetting('affiliate_commission_percent', '20', ['group' => 'affiliates', 'type' => 'string']);

        Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($team->id)->create([
            'type_id' => 1,
            'code' => 'INTERNAL-REF',
            'referred_by' => null,
            'payment_type_id' => null,
            'invoice_type_id' => null,
            'status_id' => 1,
        ]));

        $customerId = 'cus_same_team_1';
        Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($team->id)->create([
            'type_id' => 1,
            'code' => $customerId,
            'referred_by' => 'INTERNAL-REF',
            'payment_type_id' => null,
            'invoice_type_id' => null,
            'status_id' => 1,
        ]));

        app(AffiliateCommissionRecorder::class)->recordFromInvoice($team, [
            'id' => 'in_test_same_team',
            'customer' => $customerId,
            'amount_paid' => 5000,
            'currency' => 'usd',
        ]);

        $this->assertSame(0, BillingAffiliateCommission::query()->count());
    }

    public function test_commission_recorded_when_referred_by_is_referrer_enterprise_id(): void
    {
        $referrerOwner = User::factory()->create();
        $referrerTeam = Team::factory()->create(['user_id' => $referrerOwner->id]);
        $referrerOwner->forceFill(['current_team_id' => $referrerTeam->id])->save();
        $referrerTeam->setSetting('affiliate_commission_percent', '12', ['group' => 'affiliates', 'type' => 'string']);

        $referrerEnterprise = Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($referrerTeam->id)->create([
            'type_id' => 1,
            'code' => 'REF-BY-ID-1',
            'referred_by' => null,
            'payment_type_id' => null,
            'invoice_type_id' => null,
            'status_id' => 1,
        ]));

        $payingOwner = User::factory()->create();
        $payingTeam = Team::factory()->create(['user_id' => $payingOwner->id]);
        $payingOwner->forceFill(['current_team_id' => $payingTeam->id])->save();

        $customerId = 'cus_testpaying_by_id';
        Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($payingTeam->id)->create([
            'type_id' => 1,
            'code' => $customerId,
            'referred_by' => (string) $referrerEnterprise->id,
            'payment_type_id' => null,
            'invoice_type_id' => null,
            'status_id' => 1,
        ]));

        app(AffiliateCommissionRecorder::class)->recordFromInvoice($payingTeam, [
            'id' => 'in_test_aff_by_ent_id',
            'customer' => $customerId,
            'amount_paid' => 10000,
            'currency' => 'eur',
        ]);

        $this->assertDatabaseHas('billing_affiliate_commissions', [
            'stripe_invoice_id' => 'in_test_aff_by_ent_id',
            'paying_team_id' => $payingTeam->id,
            'referrer_team_id' => $referrerTeam->id,
            'commission_amount_cents' => 1200,
        ]);
    }
}
