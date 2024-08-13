<?php

namespace Database\Seeders;

use App\Models\MessageType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MessageTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MessageType::create([
            'id' => 1,
            'name' => 'Mailer',
            'status' => 1
        ]);
        
        MessageType::create([
            'id' => 2,
            'name' => 'WhatsApp',
            'status' => 1
        ]);

        
    }
}
