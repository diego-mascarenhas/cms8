<?php

namespace Database\Factories;

use App\Models\Automation;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Automation>
 */
class AutomationFactory extends Factory
{
    protected $model = Automation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'team_id' => Team::factory(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'kind' => \App\Enums\AutomationKind::Action,
            'is_active' => true,
            'entry_prompt_key' => null,
            'channels' => Automation::defaultChannels(),
            'public_token' => bin2hex(random_bytes(32)),
            'settings' => [],
        ];
    }

    public function funnel(): static
    {
        return $this->state(fn () => ['kind' => \App\Enums\AutomationKind::Funnel]);
    }

    public function action(): static
    {
        return $this->state(fn () => ['kind' => \App\Enums\AutomationKind::Action]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withPrompt(string $key): static
    {
        return $this->state(fn () => ['entry_prompt_key' => $key]);
    }

    public function sendFunnelSummaryEmail(): static
    {
        return $this->state(fn () => [
            'kind' => \App\Enums\AutomationKind::Action,
            'entry_prompt_key' => null,
            'settings' => [
                'action_type' => \App\Services\AssistantAutomationRunner::ACTION_TYPE_SEND_FUNNEL_SUMMARY_EMAIL,
            ],
        ]);
    }

    public function withChannels(array $channels): static
    {
        return $this->state(fn () => [
            'channels' => Automation::normalizeChannels($channels),
        ]);
    }
}
