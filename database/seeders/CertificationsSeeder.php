<?php

namespace Database\Seeders;

use App\Models\Certification;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
            ['certification' => 'ATA Certification', 'language' => 'EN', 'team_id' => 1],
            ['certification' => 'CIOL Diploma in Translation', 'language' => 'EN', 'team_id' => 1],
            ['certification' => 'ISO 17100:2015', 'language' => 'EN', 'team_id' => 1],
            ['certification' => 'ProZ Certified PRO', 'language' => 'EN', 'team_id' => 1],
            ['certification' => 'SDL Trados Certification', 'language' => 'EN', 'team_id' => 1],
            
            // Spanish certifications
            ['certification' => 'DELE C2', 'language' => 'ES', 'team_id' => 1],
            ['certification' => 'SIELE Global', 'language' => 'ES', 'team_id' => 1],
            
            // English certifications
            ['certification' => 'TOEFL iBT', 'language' => 'EN', 'team_id' => 1],
            ['certification' => 'IELTS Academic', 'language' => 'EN', 'team_id' => 1],
            ['certification' => 'Cambridge C2 Proficiency', 'language' => 'EN', 'team_id' => 1],
            ['certification' => 'TOEIC', 'language' => 'EN', 'team_id' => 1],
            
            // French certifications
            ['certification' => 'DELF B2', 'language' => 'FR', 'team_id' => 1],
            ['certification' => 'DALF C1', 'language' => 'FR', 'team_id' => 1],
            ['certification' => 'TCF', 'language' => 'FR', 'team_id' => 1],
            
            // German certifications
            ['certification' => 'Goethe-Zertifikat C1', 'language' => 'DE', 'team_id' => 1],
            ['certification' => 'TestDaF', 'language' => 'DE', 'team_id' => 1],
            
            // Other language certifications
            ['certification' => 'JLPT N1', 'language' => 'JA', 'team_id' => 1],
            ['certification' => 'HSK Level 6', 'language' => 'ZH', 'team_id' => 1],
            ['certification' => 'TOPIK Level 6', 'language' => 'KO', 'team_id' => 1],
            
            // Audiovisual translation certifications
            ['certification' => 'ATRAE Professional Certification', 'language' => 'ES', 'team_id' => 1],
            ['certification' => 'Subtitling Diploma ESIT', 'language' => 'FR', 'team_id' => 1],
            ['certification' => 'EZTitles Certification', 'language' => 'EN', 'team_id' => 1],
        ];

        foreach ($certifications as $certification) {
            Certification::create($certification);
        }
    }
}
