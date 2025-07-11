<?php

namespace Database\Seeders;

use App\Models\Message;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Message::create([
            'id' => 1,
            'name' => 'Test',
            'text' => 'Test Message',
            'type_id' => 2,
            'status_id' => 1,
        ]);

        Message::create([
            'id' => 2,
            'name' => 'Second Test',
            'text' => 'Test Message Two',
            'type_id' => 1,
            'template_id' => 1,
            'status_id' => 2,
        ]);
    }
}
