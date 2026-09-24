<?php

namespace Database\Factories;

use App\Enums\TeamBillingFrequency;
use App\Models\Team;
use App\Models\TeamUsageInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamUsageInvoice>
 */
class TeamUsageInvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $from = now()->copy()->subMonth()->startOfMonth();

        return [
            'team_id' => Team::factory(),
            'kind' => TeamUsageInvoice::KIND_CYCLE,
            'frequency' => TeamBillingFrequency::Monthly,
            'period_from' => $from,
            'period_to' => $from->copy()->addMonth(),
            'billed_cents' => 0,
            'currency' => 'EUR',
            'stripe_invoice_id' => null,
            'status' => TeamUsageInvoice::STATUS_DRAFT,
            'adjustment_id' => null,
            'issued_at' => now(),
        ];
    }
}
