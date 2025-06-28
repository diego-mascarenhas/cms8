<?php

namespace Database\Seeders;

use App\Models\Certification;
use Illuminate\Database\Seeder;

class CertificationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $certifications = [
            // Translation certifications
            ['certification' => 'ATA Certification', 'language' => 'en', 'team_id' => 1],
            ['certification' => 'CIOL Diploma in Translation', 'language' => 'en', 'team_id' => 1],
            ['certification' => 'ISO 17100:2015', 'language' => 'en', 'team_id' => 1],
            ['certification' => 'ProZ Certified PRO', 'language' => 'en', 'team_id' => 1],
            ['certification' => 'SDL Trados Certification', 'language' => 'en', 'team_id' => 1],

            // Spanish certifications
            ['certification' => 'DELE C2', 'language' => 'es', 'team_id' => 1],
            ['certification' => 'SIELE Global', 'language' => 'es', 'team_id' => 1],

            // English certifications
            ['certification' => 'TOEFL iBT', 'language' => 'en', 'team_id' => 1],
            ['certification' => 'IELTS Academic', 'language' => 'en', 'team_id' => 1],
            ['certification' => 'Cambridge C2 Proficiency', 'language' => 'en', 'team_id' => 1],
            ['certification' => 'TOEIC', 'language' => 'en', 'team_id' => 1],

            // French certifications
            ['certification' => 'DELF B2', 'language' => 'fr', 'team_id' => 1],
            ['certification' => 'DALF C1', 'language' => 'fr', 'team_id' => 1],
            ['certification' => 'TCF', 'language' => 'fr', 'team_id' => 1],

            // German certifications
            ['certification' => 'Goethe-Zertifikat C1', 'language' => 'de', 'team_id' => 1],
            ['certification' => 'TestDaF', 'language' => 'de', 'team_id' => 1],

            // Other language certifications
            ['certification' => 'JLPT N1', 'language' => 'ja', 'team_id' => 1],
            ['certification' => 'HSK Level 6', 'language' => 'zh', 'team_id' => 1],
            ['certification' => 'TOPIK Level 6', 'language' => 'ko', 'team_id' => 1],

            // Audiovisual translation certifications
            ['certification' => 'ATRAE Professional Certification', 'language' => 'es', 'team_id' => 1],
            ['certification' => 'Subtitling Diploma ESIT', 'language' => 'fr', 'team_id' => 1],
            ['certification' => 'EZTitles Certification', 'language' => 'en', 'team_id' => 1],
        ];

        foreach ($certifications as $certification) {
            Certification::create($certification);
        }
    }
}
