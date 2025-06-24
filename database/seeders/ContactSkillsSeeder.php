<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Contact;
use App\Models\LanguageVariant;
use App\Models\Software;
use App\Models\Topic;
use App\Models\Fare;
use App\Models\ContactValoration;
use Faker\Factory as Faker;

class ContactSkillsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        
        // Get all contacts without global scope
        $contacts = Contact::withoutGlobalScope('team')->get();
        
        if ($contacts->isEmpty()) {
            $this->command->info('No contacts found. Please run ContactSeeder first.');
            return;
        }

        // Get all available resources
        $languageVariants = LanguageVariant::all();
        $software = Software::all();
        $topics = Topic::all();
        $fares = Fare::all();
        $valorations = ContactValoration::all();

        if ($languageVariants->isEmpty() || $software->isEmpty() || $topics->isEmpty()) {
            $this->command->info('Please ensure LanguageVariants, Software, and Topics are seeded first.');
            return;
        }

        // Define realistic skill combinations by profile type
        $profileTypes = [
            'translator' => [
                'software' => ['Aegisub', 'Subtitle Edit', 'SDL Trados', 'MemoQ'],
                'topics' => ['Medicina', 'Letras', 'Técnica', 'Legal', 'Traducción'],
                'services' => ['Traducción', 'Revisión', 'Corrección'],
                'min_languages' => 2,
                'max_languages' => 4,
                'valoration_weights' => [1 => 25, 2 => 40, 3 => 25, 4 => 8, 5 => 2], // More likely to be Top/Validada
            ],
            'subtitle_specialist' => [
                'software' => ['Aegisub', 'Subtitle Edit', 'Subtitle Workshop', 'EZTitles'],
                'topics' => ['Cine', 'Entretenimiento', 'Subtitulado', 'Técnica'],
                'services' => ['Subtitulado', 'Adaptación', 'Sincronización'],
                'min_languages' => 2,
                'max_languages' => 3,
                'valoration_weights' => [1 => 30, 2 => 35, 3 => 25, 4 => 8, 5 => 2],
            ],
            'dubbing_specialist' => [
                'software' => ['Pro Tools', 'Adobe Audition', 'Logic Pro X', 'REAPER'],
                'topics' => ['Cine', 'Entretenimiento', 'Arte', 'Música'],
                'services' => ['Doblaje', 'Locución', 'Edición de audio'],
                'min_languages' => 1,
                'max_languages' => 3,
                'valoration_weights' => [1 => 20, 2 => 40, 3 => 30, 4 => 8, 5 => 2],
            ],
            'technical_writer' => [
                'software' => ['Adobe Premiere Pro', 'Final Cut Pro', 'MadCap Flare'],
                'topics' => ['Técnica', 'Tecnología', 'Ingeniería', 'Software'],
                'services' => ['Redacción técnica', 'Manuales', 'Documentación'],
                'min_languages' => 1,
                'max_languages' => 2,
                'valoration_weights' => [1 => 15, 2 => 35, 3 => 35, 4 => 12, 5 => 3],
            ],
            'marketing_specialist' => [
                'software' => ['Adobe Creative Suite', 'Canva', 'Figma'],
                'topics' => ['Marketing', 'Comunicación', 'Redes Sociales', 'Diseño'],
                'services' => ['Marketing', 'Copywriting', 'Redes sociales'],
                'min_languages' => 1,
                'max_languages' => 3,
                'valoration_weights' => [1 => 18, 2 => 38, 3 => 30, 4 => 12, 5 => 2],
            ],
            'medical_specialist' => [
                'software' => ['SDL Trados', 'MemoQ', 'Wordfast'],
                'topics' => ['Medicina', 'Salud', 'Farmacéutica', 'Biotecnología'],
                'services' => ['Traducción médica', 'Farmacéutica', 'Científica'],
                'min_languages' => 2,
                'max_languages' => 3,
                'valoration_weights' => [1 => 35, 2 => 40, 3 => 20, 4 => 4, 5 => 1], // High value specialists
            ],
            'legal_specialist' => [
                'software' => ['SDL Trados', 'MemoQ', 'Microsoft Office'],
                'topics' => ['Legal', 'Finanzas', 'Negocios', 'Inmobiliario'],
                'services' => ['Traducción legal', 'Jurídica', 'Financiera'],
                'min_languages' => 2,
                'max_languages' => 3,
                'valoration_weights' => [1 => 30, 2 => 38, 3 => 25, 4 => 6, 5 => 1],
            ],
            'generalist' => [
                'software' => ['Microsoft Office', 'Google Workspace', 'Slack'],
                'topics' => ['Comunicación', 'Cultura', 'Turismo', 'Gastronomía'],
                'services' => ['Traducción general', 'Interpretación', 'Corrección'],
                'min_languages' => 1,
                'max_languages' => 2,
                'valoration_weights' => [1 => 10, 2 => 30, 3 => 40, 4 => 15, 5 => 5], // More varied distribution
            ],
        ];

        $this->command->info('Assigning skills to ' . $contacts->count() . ' contacts...');

        foreach ($contacts as $contact) {
            // Assign a random profile type
            $profileType = $faker->randomElement(array_keys($profileTypes));
            $profile = $profileTypes[$profileType];

            $this->command->info("Processing {$contact->name} as {$profileType}...");

            // Assign Language Variants (language pairs)
            $numLanguagePairs = $faker->numberBetween($profile['min_languages'], $profile['max_languages']);
            
            // Get available language variant codes from the database
            $availableLanguageCodes = $languageVariants->pluck('code')->toArray();
            
            for ($i = 0; $i < $numLanguagePairs; $i++) {
                $sourceLanguage = $faker->randomElement($availableLanguageCodes);
                $targetLanguage = $faker->randomElement($availableLanguageCodes);
                
                // Ensure source and target are different
                while ($sourceLanguage === $targetLanguage) {
                    $targetLanguage = $faker->randomElement($availableLanguageCodes);
                }
                
                // Check if language pair already exists
                $existingPair = $contact->languageVariants()
                    ->where('source_language_code', $sourceLanguage)
                    ->where('target_language_code', $targetLanguage)
                    ->exists();
                    
                if (!$existingPair) {
                    $contact->languageVariants()->create([
                        'source_language_code' => $sourceLanguage,
                        'target_language_code' => $targetLanguage,
                        'proficiency_level' => $faker->numberBetween(2, 5),
                        'is_certified' => $faker->boolean(30),
                        'notes' => $faker->boolean(20) ? $faker->sentence() : null,
                    ]);
                }
            }

            // Assign Software (filter by team)
            $teamSoftware = $software->where('team_id', $contact->team_id);
            if ($teamSoftware->isEmpty()) {
                $teamSoftware = $software; // Fallback to all software if team has none
            }

            $relevantSoftware = $teamSoftware->filter(function ($soft) use ($profile) {
                return collect($profile['software'])->contains(function ($name) use ($soft) {
                    return stripos($soft->name, $name) !== false || stripos($name, $soft->name) !== false;
                });
            });

            // If no relevant software found, pick random ones
            if ($relevantSoftware->isEmpty()) {
                $relevantSoftware = $teamSoftware->random(min(3, $teamSoftware->count()));
            } else {
                // Add some random software to the relevant ones
                $additionalSoftware = $teamSoftware->diff($relevantSoftware)->random(min(2, max(0, $teamSoftware->count() - $relevantSoftware->count())));
                $relevantSoftware = $relevantSoftware->merge($additionalSoftware);
            }

            foreach ($relevantSoftware as $soft) {
                // Check if relationship already exists
                if (!$contact->softwares()->where('software_id', $soft->id)->exists()) {
                    $contact->softwares()->attach($soft->id, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Assign Topics (filter by team)
            $teamTopics = $topics->where('team_id', $contact->team_id);
            if ($teamTopics->isEmpty()) {
                $teamTopics = $topics; // Fallback to all topics if team has none
            }

            $relevantTopics = $teamTopics->filter(function ($topic) use ($profile) {
                return collect($profile['topics'])->contains(function ($name) use ($topic) {
                    return stripos($topic->name, $name) !== false || stripos($name, $topic->name) !== false;
                });
            });

            // If no relevant topics found, pick random ones
            if ($relevantTopics->isEmpty()) {
                $relevantTopics = $teamTopics->random(min(4, $teamTopics->count()));
            } else {
                // Add some random topics to the relevant ones
                $additionalTopics = $teamTopics->diff($relevantTopics)->random(min(2, max(0, $teamTopics->count() - $relevantTopics->count())));
                $relevantTopics = $relevantTopics->merge($additionalTopics);
            }

            // Limit to max 6 topics per contact
            $relevantTopics = $relevantTopics->take(6);

            foreach ($relevantTopics as $topic) {
                // Check if relationship already exists
                if (!$contact->topics()->where('topic_id', $topic->id)->exists()) {
                    $contact->topics()->attach($topic->id, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Assign Services/Fares (filter by team)
            if (!$fares->isEmpty()) {
                $teamFares = $fares->where('team_id', $contact->team_id);
                if ($teamFares->isEmpty()) {
                    $teamFares = $fares; // Fallback to all fares if team has none
                }

                $relevantFares = $teamFares->filter(function ($fare) use ($profile) {
                    return collect($profile['services'])->contains(function ($serviceName) use ($fare) {
                        return stripos($fare->name, $serviceName) !== false || stripos($serviceName, $fare->name) !== false;
                    });
                });

                // If no relevant fares found, pick random ones
                if ($relevantFares->isEmpty()) {
                    $relevantFares = $teamFares->random(min(3, $teamFares->count()));
                } else {
                    // Add some random fares to the relevant ones
                    $additionalFares = $teamFares->diff($relevantFares)->random(min(2, max(0, $teamFares->count() - $relevantFares->count())));
                    $relevantFares = $relevantFares->merge($additionalFares);
                }

                // Limit to max 5 services per contact
                $relevantFares = $relevantFares->take(5);

                foreach ($relevantFares as $fare) {
                    // Check if relationship already exists
                    if (!$contact->fares()->where('fare_id', $fare->id)->exists()) {
                        $contact->fares()->attach($fare->id, [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Assign Valoration based on profile weights
            if (!$valorations->isEmpty() && !$contact->valoration_id) {
                $teamValorations = $valorations->where('team_id', $contact->team_id);
                if ($teamValorations->isEmpty()) {
                    $teamValorations = $valorations; // Fallback to all valorations if team has none
                }

                // Use weighted random selection based on profile
                $weights = $profile['valoration_weights'];
                $valorationIds = $teamValorations->pluck('id')->toArray();
                
                if (!empty($valorationIds)) {
                    // Create weighted array
                    $weightedArray = [];
                    foreach ($valorationIds as $valorationId) {
                        $weight = $weights[$valorationId] ?? 10; // Default weight if not specified
                        for ($i = 0; $i < $weight; $i++) {
                            $weightedArray[] = $valorationId;
                        }
                    }
                    
                    if (!empty($weightedArray)) {
                        $selectedValorationId = $faker->randomElement($weightedArray);
                        $contact->update(['valoration_id' => $selectedValorationId]);
                    }
                }
            }
        }

        $this->command->info('ContactSkillsSeeder completed successfully!');
    }
}
