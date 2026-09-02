<?php

namespace Database\Factories;

use App\Enums\TeamBillingFrequency;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TeamUsageInvoiceAdjustment>
 */
class TeamUsageInvoiceAdjustmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $from = now()->copy()->startOfMonth();

        return [
            'team_id' => Team::factory(),
            'frequency' => TeamBillingFrequency::Monthly,
            'period_from' => $from,
            'period_to' => $from->copy()->addWeeks(3)->startOfWeek(Carbon::MONDAY),
            'invoiced_at' => null,
        ];
    }
}
