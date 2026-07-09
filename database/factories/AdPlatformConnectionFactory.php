<?php

namespace Database\Factories;

use App\Enums\AdConnectionStatus;
use App\Enums\AdPlatform;
use App\Models\AdPlatformConnection;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdPlatformConnection>
 */
class AdPlatformConnectionFactory extends Factory
{
    protected $model = AdPlatformConnection::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'platform' => AdPlatform::GoogleAds,
            'external_account_id' => (string) $this->faker->numerify('########'),
            'ad_account_id' => (string) $this->faker->numerify('##########'),
            'ad_account_name' => $this->faker->company(),
            'access_token' => 'token-'.$this->faker->uuid(),
            'refresh_token' => 'refresh-'.$this->faker->uuid(),
            'access_token_expires_at' => now()->addHour(),
            'scopes' => ['https://www.googleapis.com/auth/adwords'],
            'metadata' => [],
            'status' => AdConnectionStatus::Active,
        ];
    }

    public function platform(AdPlatform $platform): static
    {
        return $this->state(fn () => ['platform' => $platform]);
    }

    public function pendingAccount(): static
    {
        return $this->state(fn () => [
            'ad_account_id' => null,
            'ad_account_name' => null,
            'status' => AdConnectionStatus::PendingAccount,
        ]);
    }
}
