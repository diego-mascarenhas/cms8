<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Source;

class SourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sources = [
            [
                'name' => 'Phone',
                'base_url' => 'tel:',
                'icon' => 'phone',
            ],
            [
                'name' => 'Email',
                'base_url' => 'mailto:',
                'icon' => 'envelope',
            ],
            [
                'name' => 'WhatsApp',
                'base_url' => 'https://wa.me/',
                'icon' => 'whatsapp',
            ],
            [
                'name' => 'Facebook',
                'base_url' => 'https://facebook.com/',
                'icon' => 'facebook',
            ],
            [
                'name' => 'Instagram',
                'base_url' => 'https://instagram.com/',
                'icon' => 'instagram',
            ],
            [
                'name' => 'Twitter',
                'base_url' => 'https://twitter.com/',
                'icon' => 'twitter',
            ],
            [
                'name' => 'LinkedIn',
                'base_url' => 'https://linkedin.com/in/',
                'icon' => 'linkedin',
            ],
            [
                'name' => 'YouTube',
                'base_url' => 'https://youtube.com/',
                'icon' => 'youtube',
            ],
            [
                'name' => 'TikTok',
                'base_url' => 'https://tiktok.com/@',
                'icon' => 'tiktok',
            ],
            [
                'name' => 'Pinterest',
                'base_url' => 'https://pinterest.com/',
                'icon' => 'pinterest',
            ],
            [
                'name' => 'Snapchat',
                'base_url' => 'https://snapchat.com/add/',
                'icon' => 'snapchat',
            ],
            [
                'name' => 'Telegram',
                'base_url' => 'https://t.me/',
                'icon' => 'telegram',
            ],
        ];

        foreach ($sources as $source) {
            Source::create($source);
        }
    }
}