<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Enterprise;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition()
    {
        $projectType = $this->faker->randomElement([
            'medical_translation',
            'subtitle_project',
            'dubbing_project',
            'legal_translation',
            'marketing_project',
            'technical_project',
        ]);

        $projectConfig = $this->getProjectConfig($projectType);

        $nameIndex = $this->faker->numberBetween(0, count($projectConfig['names']) - 1);
        $name = $projectConfig['names'][$nameIndex];
        $realName = $projectConfig['real_names'][$nameIndex];
        $description = $projectConfig['descriptions'][$nameIndex];

        // Generate realistic dates with proper sequence
        $startDate = $this->faker->dateTimeBetween('-3 months', 'now');
        $materialDate = $this->faker->dateTimeBetween($startDate, '+1 week');
        $durationWeeks = $this->faker->numberBetween($projectConfig['duration_weeks'][0], $projectConfig['duration_weeks'][1]);
        $endDate = (clone $materialDate)->modify("+{$durationWeeks} weeks");

        // Generate realistic pricing
        $price = $this->faker->numberBetween($projectConfig['price_range'][0], $projectConfig['price_range'][1]);
        $cost = $price * $this->faker->randomFloat(2, 0.6, 0.8); // Cost is 60-80% of price
        $discount = $this->faker->boolean(20) ? $this->faker->numberBetween(5, 15) : 0; // 20% chance of discount

        return [
            'team_id' => 1,
            'enterprise_id' => function () {
                $enterprises = Enterprise::withoutGlobalScope('team')->where('team_id', 1)->get();

                return $enterprises->isNotEmpty() ? $enterprises->random()->id : null;
            },
            'category_id' => function () use ($projectConfig) {
                $categories = Category::withoutGlobalScope('team')->where('team_id', 1)->get();

                if ($categories->isEmpty()) {
                    return null;
                }

                // Try to find relevant category
                foreach ($projectConfig['category_keywords'] as $keyword) {
                    $found = $categories->filter(function ($cat) use ($keyword) {
                        return stripos($cat->name, $keyword) !== false;
                    })->first();

                    if ($found) {
                        return $found->id;
                    }
                }

                // If no relevant category found, pick random one
                return $categories->random()->id;
            },
            'responsible_id' => function () {
                $users = User::whereHas('teams', function ($q) {
                    $q->where('team_id', 1);
                })->get();

                return $users->isNotEmpty() ? $users->random()->id : null;
            },
            'name' => $name,
            'real_name' => $realName,
            'description' => $description,
            'date_material' => $materialDate,
            'date_start' => $startDate,
            'date_end' => $endDate,
            'price' => $price,
            'cost' => round($cost, 2),
            'discount' => $discount,
            'status_id' => $this->faker->randomElement([1, 2, 3, 4, 5, 7, 8, 9, 10, 11, 12, 13]), // All valid status IDs
            'created_at' => $this->faker->dateTimeBetween('-2 months', 'now'),
            'updated_at' => now(),
        ];
    }

    /**
     * State for medical translation projects
     */
    public function medical()
    {
        return $this->state(function (array $attributes) {
            $config = $this->getProjectConfig('medical_translation');
            $nameIndex = $this->faker->numberBetween(0, count($config['names']) - 1);

            return [
                'name' => $config['names'][$nameIndex],
                'real_name' => $config['real_names'][$nameIndex],
                'description' => $config['descriptions'][$nameIndex],
                'price' => $this->faker->numberBetween($config['price_range'][0], $config['price_range'][1]),
            ];
        });
    }

    /**
     * State for subtitle projects
     */
    public function subtitle()
    {
        return $this->state(function (array $attributes) {
            $config = $this->getProjectConfig('subtitle_project');
            $nameIndex = $this->faker->numberBetween(0, count($config['names']) - 1);

            return [
                'name' => $config['names'][$nameIndex],
                'real_name' => $config['real_names'][$nameIndex],
                'description' => $config['descriptions'][$nameIndex],
                'price' => $this->faker->numberBetween($config['price_range'][0], $config['price_range'][1]),
            ];
        });
    }

    /**
     * State for dubbing projects
     */
    public function dubbing()
    {
        return $this->state(function (array $attributes) {
            $config = $this->getProjectConfig('dubbing_project');
            $nameIndex = $this->faker->numberBetween(0, count($config['names']) - 1);

            return [
                'name' => $config['names'][$nameIndex],
                'real_name' => $config['real_names'][$nameIndex],
                'description' => $config['descriptions'][$nameIndex],
                'price' => $this->faker->numberBetween($config['price_range'][0], $config['price_range'][1]),
            ];
        });
    }

    /**
     * State for legal translation projects
     */
    public function legal()
    {
        return $this->state(function (array $attributes) {
            $config = $this->getProjectConfig('legal_translation');
            $nameIndex = $this->faker->numberBetween(0, count($config['names']) - 1);

            return [
                'name' => $config['names'][$nameIndex],
                'real_name' => $config['real_names'][$nameIndex],
                'description' => $config['descriptions'][$nameIndex],
                'price' => $this->faker->numberBetween($config['price_range'][0], $config['price_range'][1]),
            ];
        });
    }

    /**
     * State for marketing projects
     */
    public function marketing()
    {
        return $this->state(function (array $attributes) {
            $config = $this->getProjectConfig('marketing_project');
            $nameIndex = $this->faker->numberBetween(0, count($config['names']) - 1);

            return [
                'name' => $config['names'][$nameIndex],
                'real_name' => $config['real_names'][$nameIndex],
                'description' => $config['descriptions'][$nameIndex],
                'price' => $this->faker->numberBetween($config['price_range'][0], $config['price_range'][1]),
            ];
        });
    }

    /**
     * State for technical projects
     */
    public function technical()
    {
        return $this->state(function (array $attributes) {
            $config = $this->getProjectConfig('technical_project');
            $nameIndex = $this->faker->numberBetween(0, count($config['names']) - 1);

            return [
                'name' => $config['names'][$nameIndex],
                'real_name' => $config['real_names'][$nameIndex],
                'description' => $config['descriptions'][$nameIndex],
                'price' => $this->faker->numberBetween($config['price_range'][0], $config['price_range'][1]),
            ];
        });
    }

    /**
     * State for a specific team
     */
    public function forTeam($teamId)
    {
        return $this->state(function (array $attributes) use ($teamId) {
            return [
                'team_id' => $teamId,
                'enterprise_id' => function () use ($teamId) {
                    $enterprises = Enterprise::withoutGlobalScope('team')->where('team_id', $teamId)->get();

                    return $enterprises->isNotEmpty() ? $enterprises->random()->id : null;
                },
                'responsible_id' => function () use ($teamId) {
                    $users = User::whereHas('teams', function ($q) use ($teamId) {
                        $q->where('team_id', $teamId);
                    })->get();

                    return $users->isNotEmpty() ? $users->random()->id : null;
                },
                'category_id' => function () use ($teamId) {
                    $categories = Category::withoutGlobalScope('team')->where('team_id', $teamId)->get();

                    return $categories->isNotEmpty() ? $categories->random()->id : null;
                },
            ];
        });
    }

    /**
     * State for finished projects
     */
    public function finished()
    {
        return $this->state(function (array $attributes) {
            return [
                'status_id' => 10, // FINISHED
            ];
        });
    }

    /**
     * State for in-progress projects
     */
    public function inProgress()
    {
        return $this->state(function (array $attributes) {
            return [
                'status_id' => 9, // IN_PROGRESS
            ];
        });
    }

    private function getProjectConfig($projectType)
    {
        $configs = [
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
                    'Subtitulado de serie dramática',
                    'Subtítulos para documental',
                    'Subtítulos descriptivos accesibles',
                    'Subtitulado de curso online',
                    'Subtítulos para película independiente',
                ],
                'real_names' => [
                    'NETFLIX - "Casa de Papel T5"',
                    'DISCOVERY - "Planeta Tierra"',
                    'ONCE - "Curso accesibilidad"',
                    'UDEMY - "Curso JavaScript"',
                    'FILMIN - "El Último Verano"',
                ],
                'descriptions' => [
                    'Subtitulado de temporada completa con adaptación cultural.',
                    'Subtítulos de documental de naturaleza con terminología específica.',
                    'Subtítulos descriptivos para personas sordas con información sonora.',
                    'Subtitulado de curso técnico con terminología especializada.',
                    'Subtítulos para película independiente con diálogos complejos.',
                ],
                'category_keywords' => ['Entretenimiento', 'Subtítulos', 'Audiovisual'],
                'price_range' => [600, 2500],
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
                    'Documentos constitución empresa',
                    'Traducción sentencia judicial',
                    'Contrato de adquisición M&A',
                    'Documentación regulatoria',
                ],
                'real_names' => [
                    'CUATRECASAS - Contrato BMW',
                    'GARRIGUES - Constitución Tech',
                    'URÍA - Sentencia Madrid',
                    'BAKER - Adquisición Fintech',
                    'CLIFFORD - Regulación EU',
                ],
                'descriptions' => [
                    'Traducción de contrato internacional con cláusulas específicas.',
                    'Documentación para constitución de empresa tecnológica.',
                    'Traducción jurada de sentencia judicial compleja.',
                    'Contrato de adquisición con due diligence completo.',
                    'Documentación para cumplimiento regulatorio europeo.',
                ],
                'category_keywords' => ['Legal', 'Jurídico', 'Finanzas'],
                'price_range' => [900, 4000],
                'duration_weeks' => [1, 4],
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

        return $configs[$projectType];
    }
}
