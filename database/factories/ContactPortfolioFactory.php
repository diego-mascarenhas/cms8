<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\ContactPortfolio;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactPortfolioFactory extends Factory
{
    protected $model = ContactPortfolio::class;

    public function definition(): array
    {
        $portfolioItems = [
            // Translation projects
            [
                'title' => 'Technical Manual Translation',
                'description' => 'Comprehensive translation of technical documentation for industrial machinery, including user manuals, safety guidelines, and maintenance procedures.',
                'position' => 'Technical Translator',
                'languages' => [['source' => 'en', 'target' => 'es']],
            ],
            [
                'title' => 'Medical Document Translation',
                'description' => 'Specialized translation of clinical trial documentation, medical reports, and pharmaceutical information with strict adherence to medical terminology.',
                'position' => 'Medical Translator',
                'languages' => [['source' => 'en', 'target' => 'es'], ['source' => 'fr', 'target' => 'es']],
            ],
            [
                'title' => 'Legal Contract Translation',
                'description' => 'Professional translation of legal contracts, agreements, and corporate documentation ensuring legal accuracy and terminology consistency.',
                'position' => 'Legal Translator',
                'languages' => [['source' => 'en', 'target' => 'es']],
            ],
            [
                'title' => 'Marketing Campaign Localization',
                'description' => 'Complete localization of marketing campaigns for international markets, including cultural adaptation and brand voice consistency.',
                'position' => 'Marketing Translator',
                'languages' => [['source' => 'en', 'target' => 'es'], ['source' => 'en', 'target' => 'fr']],
            ],

            // Subtitling projects
            [
                'title' => 'Netflix Series Subtitling',
                'description' => 'Professional subtitling for streaming platform content, including timing synchronization and cultural adaptation for Spanish audiences.',
                'position' => 'Subtitler',
                'languages' => [['source' => 'en', 'target' => 'es']],
            ],
            [
                'title' => 'Documentary Subtitling',
                'description' => 'Subtitling of educational and nature documentaries with specialized terminology and accessibility features for hearing-impaired viewers.',
                'position' => 'Subtitler',
                'languages' => [['source' => 'en', 'target' => 'es'], ['source' => 'fr', 'target' => 'es']],
            ],
            [
                'title' => 'Corporate Video Subtitling',
                'description' => 'Subtitling of corporate training videos and presentations with professional terminology and clear communication.',
                'position' => 'Subtitler',
                'languages' => [['source' => 'en', 'target' => 'es']],
            ],

            // Dubbing projects
            [
                'title' => 'Commercial Voice-over',
                'description' => 'Professional voice-over recording for television and radio commercials with multiple language versions and regional adaptations.',
                'position' => 'Voice-over Artist',
                'languages' => [['source' => 'en', 'target' => 'es']],
            ],
            [
                'title' => 'Documentary Narration',
                'description' => 'Narration for documentary films and educational content with clear pronunciation and engaging delivery.',
                'position' => 'Voice-over Artist',
                'languages' => [['source' => 'en', 'target' => 'es']],
            ],
            [
                'title' => 'Audiobook Recording',
                'description' => 'Complete audiobook production including recording, editing, and post-production for literary works and educational content.',
                'position' => 'Voice-over Artist',
                'languages' => [['source' => 'en', 'target' => 'es']],
            ],

            // Software localization
            [
                'title' => 'Mobile App Localization',
                'description' => 'Complete localization of mobile applications including user interface, help documentation, and marketing materials.',
                'position' => 'Localization Specialist',
                'languages' => [['source' => 'en', 'target' => 'es'], ['source' => 'en', 'target' => 'fr']],
            ],
            [
                'title' => 'Website Localization',
                'description' => 'Comprehensive website localization including content translation, SEO optimization, and cultural adaptation.',
                'position' => 'Localization Specialist',
                'languages' => [['source' => 'en', 'target' => 'es']],
            ],
            [
                'title' => 'Software Interface Translation',
                'description' => 'Translation of software user interfaces, error messages, and help documentation for enterprise applications.',
                'position' => 'Localization Specialist',
                'languages' => [['source' => 'en', 'target' => 'es']],
            ],

            // Financial translation
            [
                'title' => 'Annual Report Translation',
                'description' => 'Translation of corporate annual reports and financial statements with precise financial terminology and regulatory compliance.',
                'position' => 'Financial Translator',
                'languages' => [['source' => 'en', 'target' => 'es']],
            ],
            [
                'title' => 'Investment Documentation',
                'description' => 'Translation of investment prospectuses, financial analysis reports, and regulatory documentation.',
                'position' => 'Financial Translator',
                'languages' => [['source' => 'en', 'target' => 'es']],
            ],

            // Educational content
            [
                'title' => 'Online Course Translation',
                'description' => 'Translation of educational content for online learning platforms including course materials, assessments, and multimedia content.',
                'position' => 'Educational Translator',
                'languages' => [['source' => 'en', 'target' => 'es']],
            ],
            [
                'title' => 'Academic Paper Translation',
                'description' => 'Translation of academic research papers and scientific publications with specialized terminology and citation accuracy.',
                'position' => 'Academic Translator',
                'languages' => [['source' => 'en', 'target' => 'es'], ['source' => 'fr', 'target' => 'es']],
            ],
        ];

        $randomItem = $this->faker->randomElement($portfolioItems);
        $year = $this->faker->numberBetween(2018, 2024);

        return [
            'contact_id' => Contact::where('team_id', 1)->inRandomOrder()->first()->id ?? 1,
            'title' => $randomItem['title'],
            'description' => $randomItem['description'],
            'year' => $year,
            'notes' => $this->faker->optional()->paragraph(),
            'data' => [
                'position' => $randomItem['position'],
                'languages' => $randomItem['languages'],
                'client' => $this->faker->optional()->company(),
                'project_duration' => $this->faker->optional()->randomElement(['1 month', '3 months', '6 months', '1 year']),
                'word_count' => $this->faker->optional()->numberBetween(1000, 50000),
                'technologies_used' => $this->faker->optional()->randomElements([
                    'SDL Trados', 'MemoQ', 'Wordfast', 'Aegisub', 'Subtitle Edit', 'Adobe Audition', 'Pro Tools',
                ], $this->faker->numberBetween(1, 3)),
            ],
        ];
    }

    /**
     * Indicate that the portfolio item is for translation work.
     */
    public function translation(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $this->faker->randomElement([
                'Technical Manual Translation',
                'Medical Document Translation',
                'Legal Contract Translation',
                'Marketing Campaign Localization',
                'Annual Report Translation',
                'Investment Documentation',
            ]),
            'data' => [
                'position' => $this->faker->randomElement(['Technical Translator', 'Medical Translator', 'Legal Translator', 'Marketing Translator', 'Financial Translator']),
                'languages' => [['source' => 'en', 'target' => 'es']],
            ],
        ]);
    }

    /**
     * Indicate that the portfolio item is for subtitling work.
     */
    public function subtitling(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $this->faker->randomElement([
                'Netflix Series Subtitling',
                'Documentary Subtitling',
                'Corporate Video Subtitling',
            ]),
            'data' => [
                'position' => 'Subtitler',
                'languages' => [['source' => 'en', 'target' => 'es']],
                'technologies_used' => ['Aegisub', 'Subtitle Edit', 'EZTitles'],
            ],
        ]);
    }

    /**
     * Indicate that the portfolio item is for voice-over work.
     */
    public function voiceOver(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $this->faker->randomElement([
                'Commercial Voice-over',
                'Documentary Narration',
                'Audiobook Recording',
            ]),
            'data' => [
                'position' => 'Voice-over Artist',
                'languages' => [['source' => 'en', 'target' => 'es']],
                'technologies_used' => ['Adobe Audition', 'Pro Tools', 'Audacity'],
            ],
        ]);
    }

    /**
     * Indicate that the portfolio item is for localization work.
     */
    public function localization(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $this->faker->randomElement([
                'Mobile App Localization',
                'Website Localization',
                'Software Interface Translation',
            ]),
            'data' => [
                'position' => 'Localization Specialist',
                'languages' => [['source' => 'en', 'target' => 'es']],
                'technologies_used' => ['SDL Trados', 'MemoQ', 'Crowdin'],
            ],
        ]);
    }

    /**
     * Indicate that the portfolio item is for educational content.
     */
    public function educational(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $this->faker->randomElement([
                'Online Course Translation',
                'Academic Paper Translation',
            ]),
            'data' => [
                'position' => $this->faker->randomElement(['Educational Translator', 'Academic Translator']),
                'languages' => [['source' => 'en', 'target' => 'es']],
            ],
        ]);
    }

    /**
     * Indicate that the portfolio item is recent (last 2 years).
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'year' => $this->faker->numberBetween(2022, 2024),
        ]);
    }

    /**
     * Indicate that the portfolio item is older (3+ years ago).
     */
    public function older(): static
    {
        return $this->state(fn (array $attributes) => [
            'year' => $this->faker->numberBetween(2018, 2021),
        ]);
    }
}
