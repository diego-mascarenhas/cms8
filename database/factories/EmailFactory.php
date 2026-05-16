<?php

namespace Database\Factories;

use App\Enums\EmailFolder;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Email>
 */
class EmailFactory extends Factory
{
    protected $model = Email::class;

    public function definition(): array
    {
        return [
            'mailbox_id' => Mailbox::factory(),
            'team_id' => Team::factory(),
            'message_id' => $this->faker->unique()->uuid(),
            'subject' => $this->faker->sentence(),
            'body_text' => $this->faker->paragraph(),
            'body_html' => null,
            'from_address' => $this->faker->safeEmail(),
            'to_address' => $this->faker->safeEmail(),
            'message_date' => now(),
            'seen' => false,
            'flagged' => false,
            'folder' => EmailFolder::Inbox->value,
        ];
    }

    public function unread(): static
    {
        return $this->state(fn () => ['seen' => false]);
    }

    public function read(): static
    {
        return $this->state(fn () => ['seen' => true]);
    }

    public function starred(): static
    {
        return $this->state(fn () => ['flagged' => true]);
    }

    public function inFolder(EmailFolder $folder): static
    {
        return $this->state(fn () => ['folder' => $folder->value]);
    }
}
