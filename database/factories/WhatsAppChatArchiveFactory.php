<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\WhatsAppChatArchive;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppChatArchive>
 */
class WhatsAppChatArchiveFactory extends Factory
{
    protected $model = WhatsAppChatArchive::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'phone' => '34600'.$this->faker->numerify('######'),
            'archived_at' => now(),
        ];
    }
}
