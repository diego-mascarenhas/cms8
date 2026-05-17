<?php

namespace Database\Factories;

use App\Models\Mailbox;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mailbox>
 */
class MailboxFactory extends Factory
{
    protected $model = Mailbox::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => 'Inbox',
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => $this->faker->safeEmail(),
            'password' => 'secret',
            'protocol' => 'imap',
            'folder' => 'INBOX',
        ];
    }
}
