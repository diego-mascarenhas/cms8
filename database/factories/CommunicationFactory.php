<?php

namespace Database\Factories;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Models\Communication;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Communication>
 */
class CommunicationFactory extends Factory
{
    protected $model = Communication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => null,
            'contact_id' => null,
            'channel' => CommunicationChannel::Email,
            'recipient_email' => $this->faker->unique()->safeEmail(),
            'recipient_phone' => null,
            'recipient_name' => $this->faker->name(),
            'subject' => $this->faker->sentence(4),
            'message' => $this->faker->paragraph(),
            'status' => CommunicationStatus::Pending,
            'sent_at' => null,
            'error_message' => null,
            'metadata' => null,
        ];
    }

    public function forTeamAndUser(Team $team, ?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $team->id,
            'user_id' => $user?->id,
        ]);
    }

    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => CommunicationChannel::Email,
            'recipient_email' => $attributes['recipient_email'] ?? $this->faker->unique()->safeEmail(),
            'subject' => $attributes['subject'] ?? $this->faker->sentence(4),
        ]);
    }

    public function whatsapp(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => CommunicationChannel::WhatsApp,
            'recipient_email' => null,
            'recipient_phone' => '34600111222',
            'subject' => null,
        ]);
    }

    public function sms(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => CommunicationChannel::Sms,
            'recipient_email' => null,
            'recipient_phone' => '34600111222',
            'subject' => null,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CommunicationStatus::Sent,
            'sent_at' => now(),
            'error_message' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CommunicationStatus::Failed,
            'error_message' => 'Delivery failed',
        ]);
    }
}
