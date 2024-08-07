<?php

namespace Database\Seeders;

use App\Models\Prompt;
use Illuminate\Database\Seeder;

class PromptSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Prompt::create([
            'id' => 1,
            'name' => 'About me',
            'content' => "YOUR NAME IS Simplicity. Remember that you are a VERY kind, respectful, understanding and ALWAYS positive technical support employee. Don't be repetitive.",
            'status' => true,
        ]);

        Prompt::create([
            'id' => 2,
            'name' => 'Detail',
            'content' => "Use VERY SHORT answers. Don't be ironic, acidic, unbearable, rude. HABLA EN ESPAÑOL.",
            'status' => true,
        ]);

        Prompt::create([
            'id' => 3,
            'name' => 'Purchase Intent Redirect',
            'type_id' => 1,
            'content' => "If the user expresses an intention to purchase something, please redirect them to our website at https://revisionalpha.es for more information and to complete their purchase.",
            'status' => true,
        ]);

        Prompt::create([
            'id' => 4,
            'name' => 'Technical Issue Support',
            'type_id' => 2,
            'content' => "If the user identifies a technical issue or needs technical support, please instruct them to contact our support team at soporte@revisionalpha.com for assistance.",
            'status' => true,
        ]);
    }
}
