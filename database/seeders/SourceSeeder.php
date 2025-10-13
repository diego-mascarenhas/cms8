<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sources = [
            [
                'name' => 'Email',
                'base_url' => 'mailto:',
                'icon' => 'fa-envelope',
                'color' => '#D44638',
            ],
            [
                'name' => 'Phone',
                'base_url' => 'tel:',
                'icon' => 'fa-phone',
                'color' => '#118C7E',
            ],
            [
                'name' => 'WhatsApp',
                'base_url' => 'https://wa.me/',
                'icon' => 'fa-whatsapp',
                'color' => '#25D366',
            ],
            [
                'name' => 'Facebook',
                'base_url' => 'https://facebook.com/',
                'icon' => 'fa-facebook',
                'color' => '#1877F2',
            ],
            [
                'name' => 'Instagram',
                'base_url' => 'https://instagram.com/',
                'icon' => 'fa-instagram',
                'color' => '#E4405F',
            ],
            [
                'name' => 'Twitter',
                'base_url' => 'https://twitter.com/',
                'icon' => 'fa-twitter',
                'color' => '#1DA1F2',
            ],
            [
                'name' => 'LinkedIn',
                'base_url' => 'https://linkedin.com/in/',
                'icon' => 'fa-linkedin',
                'color' => '#0A66C2',
            ],
            [
                'name' => 'YouTube',
                'base_url' => 'https://youtube.com/',
                'icon' => 'fa-youtube',
                'color' => '#FF0000',
            ],
            [
                'name' => 'TikTok',
                'base_url' => 'https://tiktok.com/@',
                'icon' => 'fa-tiktok',
                'color' => '#000000',
            ],
            [
                'name' => 'Pinterest',
                'base_url' => 'https://pinterest.com/',
                'icon' => 'fa-pinterest',
                'color' => '#BD081C',
            ],
            [
                'name' => 'Snapchat',
                'base_url' => 'https://snapchat.com/add/',
                'icon' => 'fa-snapchat',
                'color' => '#FFFC00',
            ],
            [
                'name' => 'Telegram',
                'base_url' => 'https://t.me/',
                'icon' => 'fa-telegram',
                'color' => '#0088cc',
            ],
        ];

        foreach ($sources as $source)
        {
            Source::updateOrCreate(
                ['name' => $source['name']],
                $source,
            );
        }
    }
}
