<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\ContactValoration;
use App\Models\Fare;
use App\Models\LanguageVariant;
use App\Models\Software;
use App\Models\Topic;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class ContactSkillsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Get only contacts from team_id 1
        $contacts = Contact::withoutGlobalScope('team')->where('team_id', 1)->get();

        if ($contacts->isEmpty())
        {
            $this->command->info('No contacts found for team_id 1. Please run ContactSeeder first.');

            return;
        }

        // Get all available resources for team_id 1
        $languageVariants = LanguageVariant::all();
        $software = Software::withoutGlobalScope('team')->where('team_id', 1)->get();
        $topics = Topic::withoutGlobalScope('team')->where('team_id', 1)->get();
        $fares = Fare::withoutGlobalScope('team')->where('team_id', 1)->get();
        $valorations = ContactValoration::withoutGlobalScope('team')->where('team_id', 1)->get();
        $projects = \App\Models\Project::withoutGlobalScope('team')->where('team_id', 1)->get();

        if ($languageVariants->isEmpty() || $software->isEmpty() || $topics->isEmpty())
        {
            $this->command->info('Please ensure LanguageVariants, Software, and Topics are seeded first.');

            return;
        }

        if ($projects->isEmpty())
        {
            $this->command->info('Warning: No projects found for team_id 1. Projects will not be assigned.');
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
                'project_probability' => 70, // 70% chance to be assigned to projects
                'min_projects' => 1,
                'max_projects' => 4,
            ],
            'subtitle_specialist' => [
                'software' => ['Aegisub', 'Subtitle Edit', 'Subtitle Workshop', 'EZTitles'],
                'topics' => ['Cine', 'Entretenimiento', 'Subtitulado', 'Técnica'],
                'services' => ['Subtitulado', 'Adaptación', 'Sincronización'],
                'min_languages' => 2,
                'max_languages' => 3,
                'valoration_weights' => [1 => 30, 2 => 35, 3 => 25, 4 => 8, 5 => 2],
                'project_probability' => 85, // High demand for subtitle specialists
                'min_projects' => 2,
                'max_projects' => 6,
            ],
            'dubbing_specialist' => [
                'software' => ['Pro Tools', 'Adobe Audition', 'Logic Pro X', 'REAPER'],
                'topics' => ['Cine', 'Entretenimiento', 'Arte', 'Música'],
                'services' => ['Doblaje', 'Locución', 'Edición de audio'],
                'min_languages' => 1,
                'max_languages' => 3,
                'valoration_weights' => [1 => 20, 2 => 40, 3 => 30, 4 => 8, 5 => 2],
                'project_probability' => 75,
                'min_projects' => 1,
                'max_projects' => 4,
            ],
            'technical_writer' => [
                'software' => ['Adobe Premiere Pro', 'Final Cut Pro', 'MadCap Flare'],
                'topics' => ['Técnica', 'Tecnología', 'Ingeniería', 'Software'],
                'services' => ['Redacción técnica', 'Manuales', 'Documentación'],
                'min_languages' => 1,
                'max_languages' => 2,
                'valoration_weights' => [1 => 15, 2 => 35, 3 => 35, 4 => 12, 5 => 3],
                'project_probability' => 60,
                'min_projects' => 1,
                'max_projects' => 3,
            ],
            'marketing_specialist' => [
                'software' => ['Adobe Creative Suite', 'Canva', 'Figma'],
                'topics' => ['Marketing', 'Comunicación', 'Redes Sociales', 'Diseño'],
                'services' => ['Marketing', 'Copywriting', 'Redes sociales'],
                'min_languages' => 1,
                'max_languages' => 3,
                'valoration_weights' => [1 => 18, 2 => 38, 3 => 30, 4 => 12, 5 => 2],
                'project_probability' => 80,
                'min_projects' => 2,
                'max_projects' => 5,
            ],
            'medical_specialist' => [
                'software' => ['SDL Trados', 'MemoQ', 'Wordfast'],
                'topics' => ['Medicina', 'Salud', 'Farmacéutica', 'Biotecnología'],
                'services' => ['Traducción médica', 'Farmacéutica', 'Científica'],
                'min_languages' => 2,
                'max_languages' => 3,
                'valoration_weights' => [1 => 35, 2 => 40, 3 => 20, 4 => 4, 5 => 1], // High value specialists
                'project_probability' => 90, // High demand and specialized
                'min_projects' => 2,
                'max_projects' => 5,
            ],
            'legal_specialist' => [
                'software' => ['SDL Trados', 'MemoQ', 'Microsoft Office'],
                'topics' => ['Legal', 'Finanzas', 'Negocios', 'Inmobiliario'],
                'services' => ['Traducción legal', 'Jurídica', 'Financiera'],
                'min_languages' => 2,
                'max_languages' => 3,
                'valoration_weights' => [1 => 30, 2 => 38, 3 => 25, 4 => 6, 5 => 1],
                'project_probability' => 85,
                'min_projects' => 1,
                'max_projects' => 4,
            ],
            'generalist' => [
                'software' => ['Microsoft Office', 'Google Workspace', 'Slack'],
                'topics' => ['Comunicación', 'Cultura', 'Turismo', 'Gastronomía'],
                'services' => ['Traducción general', 'Interpretación', 'Corrección'],
                'min_languages' => 1,
                'max_languages' => 2,
                'valoration_weights' => [1 => 10, 2 => 30, 3 => 40, 4 => 15, 5 => 5], // More varied distribution
                'project_probability' => 50, // Lower probability for generalists
                'min_projects' => 1,
                'max_projects' => 2,
            ],
        ];

        $this->command->info('Assigning skills to '.$contacts->count().' contacts...');

        foreach ($contacts as $contact)
        {
            // Assign a random profile type
            $profileType = $faker->randomElement(array_keys($profileTypes));
            $profile = $profileTypes[$profileType];

            $this->command->info("Processing {$contact->name} as {$profileType}...");

            // Assign Language Variants (language pairs)
            $numLanguagePairs = $faker->numberBetween($profile['min_languages'], $profile['max_languages']);

            // Get available language variant codes from the database
            $availableLanguageCodes = $languageVariants->pluck('code')->toArray();

            for ($i = 0; $i < $numLanguagePairs; $i++)
            {
                $sourceLanguage = $faker->randomElement($availableLanguageCodes);
                $targetLanguage = $faker->randomElement($availableLanguageCodes);

                // Ensure source and target are different
                while ($sourceLanguage === $targetLanguage)
                {
                    $targetLanguage = $faker->randomElement($availableLanguageCodes);
                }

                // Check if language pair already exists
                $existingPair = $contact->languageVariants()
                    ->where('source_language_code', $sourceLanguage)
                    ->where('target_language_code', $targetLanguage)
                    ->exists();

                if (! $existingPair)
                {
                    $contact->languageVariants()->create([
                        'source_language_code' => $sourceLanguage,
                        'target_language_code' => $targetLanguage,
                        'proficiency_level' => $faker->numberBetween(2, 5),
                        'is_certified' => $faker->boolean(30),
                        'notes' => $faker->boolean(20) ? $faker->sentence() : null,
                    ]);
                }
            }

            // Assign Software (all software is already filtered for team_id 1)
            $teamSoftware = $software;

            $relevantSoftware = $teamSoftware->filter(function ($soft) use ($profile)
            {
                return collect($profile['software'])->contains(function ($name) use ($soft)
                {
                    return stripos($soft->name, $name) !== false || stripos($name, $soft->name) !== false;
                });
            });

            // If no relevant software found, pick random ones
            if ($relevantSoftware->isEmpty())
            {
                $relevantSoftware = $teamSoftware->random(min(3, $teamSoftware->count()));
            } else
            {
                // Add some random software to the relevant ones
                $additionalSoftware = $teamSoftware->diff($relevantSoftware)->random(min(2, max(0, $teamSoftware->count() - $relevantSoftware->count())));
                $relevantSoftware = $relevantSoftware->merge($additionalSoftware);
            }

            foreach ($relevantSoftware as $soft)
            {
                // Check if relationship already exists
                if (! $contact->softwares()->where('software_id', $soft->id)->exists())
                {
                    $contact->softwares()->attach($soft->id, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Assign Topics (all topics are already filtered for team_id 1)
            $teamTopics = $topics;

            $relevantTopics = $teamTopics->filter(function ($topic) use ($profile)
            {
                return collect($profile['topics'])->contains(function ($name) use ($topic)
                {
                    return stripos($topic->name, $name) !== false || stripos($name, $topic->name) !== false;
                });
            });

            // If no relevant topics found, pick random ones
            if ($relevantTopics->isEmpty())
            {
                $relevantTopics = $teamTopics->random(min(4, $teamTopics->count()));
            } else
            {
                // Add some random topics to the relevant ones
                $additionalTopics = $teamTopics->diff($relevantTopics)->random(min(2, max(0, $teamTopics->count() - $relevantTopics->count())));
                $relevantTopics = $relevantTopics->merge($additionalTopics);
            }

            // Limit to max 6 topics per contact
            $relevantTopics = $relevantTopics->take(6);

            foreach ($relevantTopics as $topic)
            {
                // Check if relationship already exists
                if (! $contact->topics()->where('topic_id', $topic->id)->exists())
                {
                    $contact->topics()->attach($topic->id, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Assign Services/Fares (all fares are already filtered for team_id 1)
            if (! $fares->isEmpty())
            {
                $teamFares = $fares;

                $relevantFares = $teamFares->filter(function ($fare) use ($profile)
                {
                    return collect($profile['services'])->contains(function ($serviceName) use ($fare)
                    {
                        return stripos($fare->name, $serviceName) !== false || stripos($serviceName, $fare->name) !== false;
                    });
                });

                // If no relevant fares found, pick random ones
                if ($relevantFares->isEmpty())
                {
                    $relevantFares = $teamFares->random(min(3, $teamFares->count()));
                } else
                {
                    // Add some random fares to the relevant ones
                    $additionalFares = $teamFares->diff($relevantFares)->random(min(2, max(0, $teamFares->count() - $relevantFares->count())));
                    $relevantFares = $relevantFares->merge($additionalFares);
                }

                // Limit to max 5 services per contact
                $relevantFares = $relevantFares->take(5);

                // Get all language combinations for this contact to assign rates
                $languageCombinations = $contact->languageVariants;

                foreach ($relevantFares as $fare)
                {
                    // Assign fare with realistic rates for each language combination
                    foreach ($languageCombinations as $langCombo)
                    {
                        // Check if relationship already exists for this specific language combination
                        $existingFare = $contact->fares()
                            ->where('fare_id', $fare->id)
                            ->wherePivot('source_language_code', $langCombo->source_language_code)
                            ->wherePivot('target_language_code', $langCombo->target_language_code)
                            ->exists();

                        if (! $existingFare)
                        {
                            // Generate realistic prices based on profile type and valoration
                            $basePrice = $this->generateRealisticPrice($profileType, $fare->name, $contact->valoration_id);

                            // Get available units for this fare
                            $fareWithUnits = \App\Models\Fare::with('units')->find($fare->id);
                            $unitId = $fareWithUnits && $fareWithUnits->units->count() > 0
                                ? $fareWithUnits->units->random()->id
                                : null;

                            // Random currency based on profile (some specialists prefer certain currencies)
                            $currencies = ['EUR', 'USD', 'GBP'];
                            if (in_array($profileType, ['medical_specialist', 'legal_specialist']))
                            {
                                $currency = $faker->randomElement(['EUR', 'USD']); // Premium specialists prefer EUR/USD
                            } else
                            {
                                $currency = $faker->randomElement($currencies);
                            }

                            $contact->fares()->attach($fare->id, [
                                'price' => $basePrice,
                                'unit_id' => $unitId,
                                'currency_code' => $currency,
                                'source_language_code' => $langCombo->source_language_code,
                                'target_language_code' => $langCombo->target_language_code,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }

            // Assign Valoration based on profile weights
            if (! $valorations->isEmpty() && ! $contact->valoration_id)
            {
                $teamValorations = $valorations;

                // Use weighted random selection based on profile
                $weights = $profile['valoration_weights'];
                $valorationIds = $teamValorations->pluck('id')->toArray();

                if (! empty($valorationIds))
                {
                    // Create weighted array
                    $weightedArray = [];
                    foreach ($valorationIds as $valorationId)
                    {
                        $weight = $weights[$valorationId] ?? 10; // Default weight if not specified
                        for ($i = 0; $i < $weight; $i++)
                        {
                            $weightedArray[] = $valorationId;
                        }
                    }

                    if (! empty($weightedArray))
                    {
                        $selectedValorationId = $faker->randomElement($weightedArray);
                        $contact->update(['valoration_id' => $selectedValorationId]);
                    }
                }
            }

            // Assign Projects based on profile probability
            if (isset($projects) && ! $projects->isEmpty())
            {
                $shouldAssignProjects = $faker->boolean($profile['project_probability']);

                if ($shouldAssignProjects)
                {
                    $numProjects = $faker->numberBetween($profile['min_projects'], $profile['max_projects']);

                    // Get random projects from the available ones
                    $selectedProjects = $projects->random(min($numProjects, $projects->count()));

                    foreach ($selectedProjects as $project)
                    {
                        // Check if relationship already exists
                        if (! $contact->projects()->where('project_id', $project->id)->exists())
                        {
                            // Generate realistic status and timing
                            $statuses = ['sent', 'viewed', 'accepted', 'rejected'];
                            $status = $faker->randomElement($statuses);

                            $sentAt = $faker->dateTimeBetween('-6 months', 'now');
                            $viewedAt = null;
                            $respondedAt = null;

                            if (in_array($status, ['viewed', 'accepted', 'rejected']))
                            {
                                // Generate safe datetime avoiding DST transition hours
                                $viewedAt = $this->generateSafeDatetime($sentAt, 'now');
                            }

                            if (in_array($status, ['accepted', 'rejected']))
                            {
                                $respondedAt = $this->generateSafeDatetime($viewedAt ?: $sentAt, 'now');
                            }

                            // Generate a realistic message
                            $messages = [
                                "Hola {$contact->name}, tenemos un nuevo proyecto de traducción que podría interesarte. ¿Podrías confirmarnos tu disponibilidad?",
                                "Buenos días {$contact->name}, te contactamos para un proyecto de {$project->name}. ¿Cuál sería tu tarifa?",
                                "Hola {$contact->name}, tenemos un proyecto urgente. ¿Podrías ayudarnos con {$project->name}?",
                                "Estimado/a {$contact->name}, necesitamos cotización para el proyecto {$project->name}. ¿Tienes disponibilidad?",
                            ];

                            $messageSent = $faker->randomElement($messages);

                            // Generate response message if status requires it
                            $responseMessage = null;
                            if ($status === 'accepted')
                            {
                                $responses = [
                                    'Perfecto, acepto el proyecto. Mi tarifa es de $XX por palabra.',
                                    'Excelente, puedo trabajar en este proyecto. ¿Cuándo necesitan la entrega?',
                                    'Acepto. Envíenme los materiales cuando estén listos.',
                                    'Me parece bien, podemos proceder con el proyecto.',
                                ];
                                $responseMessage = $faker->randomElement($responses);
                            } elseif ($status === 'rejected')
                            {
                                $responses = [
                                    'Lamentablemente no tengo disponibilidad para este proyecto.',
                                    'No puedo aceptar este proyecto en este momento.',
                                    'Gracias por pensar en mí, pero no puedo tomar este trabajo.',
                                    'No es mi especialidad, mejor busquen otro colaborador.',
                                ];
                                $responseMessage = $faker->randomElement($responses);
                            }

                            $contact->projects()->attach($project->id, [
                                'message_sent' => $messageSent,
                                'status' => $status,
                                'sent_at' => $sentAt,
                                'viewed_at' => $viewedAt,
                                'responded_at' => $respondedAt,
                                'response_message' => $responseMessage,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    $this->command->info("  └─ Assigned {$selectedProjects->count()} projects to {$contact->name}");
                }
            }
        }

        $this->command->info('ContactSkillsSeeder completed successfully!');
    }

    /**
     * Generate safe datetime avoiding DST transition hours
     */
    private function generateSafeDatetime($from, $to)
    {
        $faker = Faker::create();
        $maxAttempts = 10;
        $attempts = 0;

        while ($attempts < $maxAttempts)
        {
            try
            {
                $dateTime = $faker->dateTimeBetween($from, $to);

                // Avoid problematic DST transition hours (2:00-3:00 AM on DST days)
                $hour = (int) $dateTime->format('H');
                $month = (int) $dateTime->format('n');
                $day = (int) $dateTime->format('j');

                // Skip 2 AM hour during likely DST transition dates (late March, late October)
                if ($hour === 2 && (($month === 3 && $day >= 25) || ($month === 10 && $day >= 25)))
                {
                    $attempts++;

                    continue;
                }

                return $dateTime;
            } catch (\Exception $e)
            {
                $attempts++;
                if ($attempts >= $maxAttempts)
                {
                    // Fallback: return a safe datetime by adjusting to 3 AM
                    $safeDateTime = $faker->dateTimeBetween($from, $to);
                    $safeDateTime->setTime(3, 0, 0); // Set to 3:00 AM to avoid DST issues

                    return $safeDateTime;
                }
            }
        }

        // Final fallback
        return new \DateTime;
    }

    /**
     * Generate realistic price based on profile type, service, and valoration
     */
    private function generateRealisticPrice($profileType, $serviceName, $valorationId)
    {
        $faker = Faker::create();

        // Base price ranges by profile type (per word/hour/project)
        $priceRanges = [
            'translator' => ['min' => 0.08, 'max' => 0.25],
            'subtitle_specialist' => ['min' => 0.12, 'max' => 0.35],
            'dubbing_specialist' => ['min' => 15, 'max' => 60], // per hour
            'technical_writer' => ['min' => 0.15, 'max' => 0.40],
            'marketing_specialist' => ['min' => 0.10, 'max' => 0.30],
            'medical_specialist' => ['min' => 0.20, 'max' => 0.60], // Premium rates
            'legal_specialist' => ['min' => 0.18, 'max' => 0.50],
            'generalist' => ['min' => 0.06, 'max' => 0.20],
        ];

        // Service multipliers (some services are worth more)
        $serviceMultipliers = [
            'Traducción' => 1.0,
            'Revisión' => 0.8,
            'Corrección' => 0.6,
            'Subtitulado' => 1.3,
            'Doblaje' => 1.5,
            'Locución' => 1.4,
            'Interpretación' => 2.0,
            'Traducción médica' => 1.8,
            'Traducción legal' => 1.6,
            'Marketing' => 1.2,
            'Copywriting' => 1.1,
        ];

        // Valoration multipliers (better valorations = higher rates)
        $valorationMultipliers = [
            1 => 1.5,  // Top
            2 => 1.2,  // Validada
            3 => 1.0,  // Standard
            4 => 0.8,  // En evaluación
            5 => 0.6,  // Blacklist (lower rates)
        ];

        $range = $priceRanges[$profileType] ?? $priceRanges['generalist'];
        $basePrice = $faker->randomFloat(2, $range['min'], $range['max']);

        // Apply service multiplier
        $serviceMultiplier = 1.0;
        foreach ($serviceMultipliers as $service => $multiplier)
        {
            if (stripos($serviceName, $service) !== false)
            {
                $serviceMultiplier = $multiplier;
                break;
            }
        }

        // Apply valoration multiplier
        $valorationMultiplier = $valorationMultipliers[$valorationId] ?? 1.0;

        // Calculate final price with some randomness
        $finalPrice = $basePrice * $serviceMultiplier * $valorationMultiplier;

        // Add some variation (±15%)
        $variation = $faker->randomFloat(2, 0.85, 1.15);
        $finalPrice *= $variation;

        // Round to 2 decimal places and ensure minimum price
        return round(max($finalPrice, 0.05), 2);
    }
}
