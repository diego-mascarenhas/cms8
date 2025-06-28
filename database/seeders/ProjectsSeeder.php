<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\Enterprise;
use App\Models\User;
use App\Models\Category;
use Faker\Factory as Faker;

class ProjectsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        
        // Get all resources for team_id 1
        $enterprises = Enterprise::withoutGlobalScope('team')->where('team_id', 1)->get();
        $users = User::whereHas('teams', function($q) { $q->where('team_id', 1); })->get();
        $categories = Category::withoutGlobalScope('team')->where('team_id', 1)->get();
        
        if ($enterprises->isEmpty() || $users->isEmpty()) {
            $this->command->info('No enterprises or users found for team_id 1. Please seed them first.');
            return;
        }

        // Define realistic project types that match our collaborator profiles
        $projectTypes = [
            'medical_translation' => [
                'names' => [
                    'Traducción de ensayos clínicos fase III',
                    'Manual de dispositivo médico',
                    'Traducción de prospecto farmacéutico',
                    'Documentación regulatoria FDA',
                    'Protocolo de investigación clínica',
                ],
                'real_names' => [
                    'CARDIOTECH - Ensayos clínicos',
                    'MEDITECH - Manual usuario',
                    'PHARMA PLUS - Prospecto Ibuprofeno',
                    'BIOTECH Corp - Registro FDA',
                    'RESEARCH LAB - Protocolo cardio',
                ],
                'descriptions' => [
                    'Traducción especializada de documentación médica con terminología específica cardiovascular.',
                    'Manual técnico de dispositivo médico clase III para registro europeo.',
                    'Prospecto de medicamento con especial atención a efectos adversos.',
                    'Documentación regulatoria para aprobación de nuevo fármaco.',
                    'Protocolo de investigación clínica multicéntrico internacional.',
                ],
                'category_keywords' => ['Medicina', 'Salud', 'Farmacia'],
                'price_range' => [800, 3500],
                'duration_weeks' => [1, 4],
            ],
            'subtitle_project' => [
                'names' => [
                    'Subtitulado serie documental',
                    'Subtítulos para webinar corporativo',
                    'Serie de formación empresarial',
                    'Documental de naturaleza',
                    'Contenido educativo online',
                ],
                'real_names' => [
                    'NETFLIX - "Ocean Mysteries"',
                    'MICROSOFT - Webinar AI 2024',
                    'LINKEDIN - Curso Liderazgo',
                    'BBC - "Wildlife Chronicles"',
                    'COURSERA - Marketing Digital',
                ],
                'descriptions' => [
                    'Subtitulado de serie documental de 6 episodios con sincronización precisa.',
                    'Subtítulos para webinar corporativo sobre inteligencia artificial.',
                    'Serie de 12 videos de formación empresarial con timing específico.',
                    'Documental de naturaleza con terminología especializada.',
                    'Contenido educativo online con múltiples idiomas objetivo.',
                ],
                'category_keywords' => ['Audiovisual', 'Entretenimiento', 'Educación'],
                'price_range' => [600, 2200],
                'duration_weeks' => [1, 3],
            ],
            'dubbing_project' => [
                'names' => [
                    'Doblaje comercial publicitario',
                    'Doblaje de serie animada',
                    'Locución para audiolibro',
                    'Doblaje documental corporativo',
                    'Campaña publicitaria radio',
                ],
                'real_names' => [
                    'COCA-COLA - Campaña verano',
                    'DISNEY - "Adventures Club"',
                    'AUDIBLE - "El Quijote"',
                    'TELEFÓNICA - Memoria anual',
                    'RADIO MARCA - Jingles',
                ],
                'descriptions' => [
                    'Doblaje de campaña publicitaria con 3 versiones de duración.',
                    'Doblaje de serie animada infantil con múltiples personajes.',
                    'Locución profesional para audiolibro clásico de 8 horas.',
                    'Doblaje de documental corporativo para memoria anual.',
                    'Campaña de radio con múltiples jingles y variaciones.',
                ],
                'category_keywords' => ['Audio', 'Entretenimiento', 'Publicidad'],
                'price_range' => [400, 1800],
                'duration_weeks' => [1, 2],
            ],
            'legal_translation' => [
                'names' => [
                    'Traducción de contrato internacional',
                    'Documentación proceso judicial',
                    'Traducción certificada notarial',
                    'Contrato de fusión empresarial',
                    'Documentos de propiedad intelectual',
                ],
                'real_names' => [
                    'BAKER & McKenzie - Contrato M&A',
                    'Juzgado Madrid - Proceso civil',
                    'Notaría García - Poderes',
                    'SANTANDER - Fusión filiales',
                    'PATENTES SA - Registro marca',
                ],
                'descriptions' => [
                    'Traducción jurídica de contrato de adquisición internacional.',
                    'Documentación completa de proceso judicial civil.',
                    'Traducción certificada de documentos notariales.',
                    'Contrato de fusión entre empresas multinacionales.',
                    'Documentación de propiedad intelectual y marcas.',
                ],
                'category_keywords' => ['Legal', 'Jurídico', 'Notarial'],
                'price_range' => [900, 4000],
                'duration_weeks' => [1, 5],
            ],
            'marketing_project' => [
                'names' => [
                    'Campaña de marketing multiidioma',
                    'Localización web corporativa',
                    'Material promocional ferias',
                    'Contenido redes sociales',
                    'Catálogo de productos B2B',
                ],
                'real_names' => [
                    'ZARA - Campaña primavera',
                    'IBERDROLA - Web sostenibilidad',
                    'IFEMA - Material MWC 2024',
                    'MANGO - Social Media Kit',
                    'INDRA - Catálogo soluciones',
                ],
                'descriptions' => [
                    'Campaña de marketing integral para 8 mercados internacionales.',
                    'Localización completa de website corporativo con SEO.',
                    'Material promocional para feria tecnológica internacional.',
                    'Kit de contenidos para redes sociales en 5 idiomas.',
                    'Catálogo técnico de soluciones B2B con especificaciones.',
                ],
                'category_keywords' => ['Marketing', 'Comunicación', 'Digital'],
                'price_range' => [700, 2800],
                'duration_weeks' => [2, 4],
            ],
            'technical_project' => [
                'names' => [
                    'Manual técnico industrial',
                    'Documentación software ERP',
                    'Especificaciones técnicas',
                    'Manual de instalación',
                    'Documentación API técnica',
                ],
                'real_names' => [
                    'SIEMENS - Manual turbinas',
                    'SAP - Documentación S/4HANA',
                    'AIRBUS - Especificaciones A350',
                    'SCHNEIDER - Manual instalación',
                    'MICROSOFT - API Azure',
                ],
                'descriptions' => [
                    'Manual técnico de turbinas industriales con diagramas.',
                    'Documentación completa de sistema ERP empresarial.',
                    'Especificaciones técnicas de componentes aeronáuticos.',
                    'Manual de instalación de sistemas eléctricos.',
                    'Documentación técnica de API con ejemplos de código.',
                ],
                'category_keywords' => ['Técnica', 'Tecnología', 'Ingeniería'],
                'price_range' => [1000, 3200],
                'duration_weeks' => [2, 6],
            ],
        ];

        // Project statuses (from what I saw in the migration)
        $statusIds = [8, 9, 10, 11]; // Common statuses: WAITING_FOR_RESPONSE, IN_PROGRESS, FINISHED, TO_INVOICE

        $this->command->info('Creating 30 example projects for team_id 1...');

        for ($i = 0; $i < 30; $i++) {
            $projectType = $faker->randomElement(array_keys($projectTypes));
            $typeConfig = $projectTypes[$projectType];
            
            $nameIndex = $faker->numberBetween(0, count($typeConfig['names']) - 1);
            $name = $typeConfig['names'][$nameIndex];
            $realName = $typeConfig['real_names'][$nameIndex];
            $description = $typeConfig['descriptions'][$nameIndex];
            
            // Find a relevant category
            $relevantCategory = null;
            if (!$categories->isEmpty()) {
                foreach ($typeConfig['category_keywords'] as $keyword) {
                    $found = $categories->filter(function ($cat) use ($keyword) {
                        return stripos($cat->name, $keyword) !== false;
                    })->first();
                    
                    if ($found) {
                        $relevantCategory = $found;
                        break;
                    }
                }
                
                // If no relevant category found, pick random one
                if (!$relevantCategory) {
                    $relevantCategory = $categories->random();
                }
            }
            
            // Generate realistic dates with proper sequence
            $startDate = $faker->dateTimeBetween('-3 months', 'now');
            $materialDate = $faker->dateTimeBetween($startDate, '+1 week');
            $durationWeeks = $faker->numberBetween($typeConfig['duration_weeks'][0], $typeConfig['duration_weeks'][1]);
            $endDate = (clone $materialDate)->modify("+{$durationWeeks} weeks");
            
            // Generate realistic pricing
            $price = $faker->numberBetween($typeConfig['price_range'][0], $typeConfig['price_range'][1]);
            $cost = $price * $faker->randomFloat(2, 0.6, 0.8); // Cost is 60-80% of price
            $discount = $faker->boolean(20) ? $faker->numberBetween(5, 15) : 0; // 20% chance of discount
            
            Project::create([
                'team_id' => 1,
                'enterprise_id' => $enterprises->random()->id,
                'category_id' => $relevantCategory ? $relevantCategory->id : null,
                'responsible_id' => $users->random()->id,
                'name' => $name,
                'real_name' => $realName,
                'description' => $description,
                'date_material' => $materialDate,
                'date_start' => $startDate,
                'date_end' => $endDate,
                'price' => $price,
                'cost' => round($cost, 2),
                'discount' => $discount,
                'status_id' => $faker->randomElement($statusIds),
                'created_at' => $faker->dateTimeBetween('-2 months', 'now'),
                'updated_at' => now(),
            ]);
            
            if (($i + 1) % 10 == 0) {
                $this->command->info("Created " . ($i + 1) . " projects...");
            }
        }

        $this->command->info('ProjectsSeeder completed successfully! Created 30 projects for team_id 1.');
    }
} 