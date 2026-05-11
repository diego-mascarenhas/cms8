<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Models\Campaign;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->sentence(4),
            'type' => CampaignType::Broadcasts->value,
            'status' => CampaignStatus::Active->value,
            'summary' => null,
            'sends_count' => null,
            'opened_rate' => null,
            'clicked_rate' => null,
            'unsubscribed_rate' => null,
            'scheduled_at' => null,
            'sent_at' => null,
        ];
    }

    public function sequenceSummary(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CampaignType::Sequences->value,
            'summary' => '7 correos en 150 días',
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CampaignType::Broadcasts->value,
            'status' => CampaignStatus::Scheduled->value,
            'summary' => 'Programado para 07 mayo 2026 19:00',
            'scheduled_at' => now()->addWeek(),
            'sent_at' => null,
            'sends_count' => null,
        ]);
    }

    public function sentWithMetrics(
        ?int $sendsCount = 2381,
        ?float $openedRate = 20,
        ?float $clickedRate = 0,
        ?float $unsubscribedRate = 0,
    ): static {
        return $this->state(fn (array $attributes): array => [
            'type' => CampaignType::Broadcasts->value,
            'status' => CampaignStatus::Sent->value,
            'summary' => $attributes['summary'] ?? ('Enviado el '.now()->subDays(7)->translatedFormat('d F Y').' 19:03'),
            'scheduled_at' => null,
            'sent_at' => now()->subDays(7),
            'sends_count' => $sendsCount,
            'opened_rate' => $openedRate,
            'clicked_rate' => $clickedRate,
            'unsubscribed_rate' => $unsubscribedRate,
        ]);
    }
}
