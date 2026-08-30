<?php

namespace Database\Factories;

use App\Models\Automation;
use App\Models\SiteAssistantMessage;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SiteAssistantMessage>
 */
class SiteAssistantMessageFactory extends Factory
{
    protected $model = SiteAssistantMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'automation_id' => Automation::factory(),
            'session_id' => null,
            'session_key' => (string) Str::uuid(),
            'contact_id' => null,
            'user_id' => null,
            'role' => SiteAssistantMessage::ROLE_VISITOR,
            'channel' => SiteAssistantMessage::CHANNEL_WEB,
            'body' => fake()->sentence(),
        ];
    }
}
