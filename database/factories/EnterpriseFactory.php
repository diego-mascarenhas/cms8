<?php

namespace Database\Factories;

use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnterpriseFactory extends Factory
{
    protected $model = Enterprise::class;

    public function definition()
    {
        $categoryKey = $this->faker->randomElement([
            'medical_pharmaceutical',
            'entertainment_media',
            'technology_software',
            'legal_financial',
            'marketing_advertising',
        ]);

        $enterpriseData = $this->getEnterpriseDataByCategory($categoryKey);
        $country = $this->faker->randomElement($enterpriseData['countries']);

        $nameIndex = $this->faker->numberBetween(0, count($enterpriseData['names']) - 1);
        $name = $enterpriseData['names'][$nameIndex];
        $website = $enterpriseData['websites'][$nameIndex];
        $description = $enterpriseData['descriptions'][$nameIndex];

        $phone = $this->generatePhone($country);
        $email = 'info@'.$website;
        $address = $this->generateAddress($country);
        $code = strtoupper(substr(str_replace(' ', '', $name), 0, 3)).$this->faker->numberBetween(100, 999);

        return [
            'team_id' => 1,
            'type_id' => 1, // Cliente
            'name' => $name,
            'code' => $code,
            'website' => 'https://'.$website,
            'phone' => $phone,
            'email' => $email,
            'whatsapp' => $phone,
            'referred_by' => $this->faker->boolean(30) ? $this->faker->randomElement(['LinkedIn', 'Google', 'Referral', 'Website']) : null,
            'address' => $address['address'],
            'postal_code' => $address['postal_code'],
            'locality' => $address['locality'],
            'province' => $address['province'],
            'country' => $country,
            'data' => json_encode([
                'description' => $description,
                'category' => $categoryKey,
                'size' => $this->faker->randomElement(['startup', 'small', 'medium', 'large', 'enterprise']),
                'industry' => $this->getIndustryByCategory($categoryKey),
            ]),
            'payment_type_id' => $this->faker->randomElement([1, 2, 3, 4]),
            'invoice_type_id' => $this->faker->randomElement([1, 2, 3, 4]),
            'status_id' => $this->faker->randomElement([1, 2]),
            'creator_id' => function () {
                $users = User::whereHas('teams', function ($q) {
                    $q->where('team_id', 1);
                })->get();

                return $users->isNotEmpty() ? $users->random()->id : null;
            },
            'responsible_id' => null,
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'updated_at' => now(),
        ];
    }

    /**
     * State for medical/pharmaceutical companies
     */
    public function medical()
    {
        return $this->state(function (array $attributes) {
            $enterpriseData = $this->getEnterpriseDataByCategory('medical_pharmaceutical');
            $nameIndex = $this->faker->numberBetween(0, count($enterpriseData['names']) - 1);

            return [
                'name' => $enterpriseData['names'][$nameIndex],
                'website' => 'https://'.$enterpriseData['websites'][$nameIndex],
                'data' => json_encode([
                    'description' => $enterpriseData['descriptions'][$nameIndex],
                    'category' => 'medical_pharmaceutical',
                    'size' => $this->faker->randomElement(['medium', 'large', 'enterprise']),
                    'industry' => 'Healthcare & Pharmaceutical',
                ]),
            ];
        });
    }

    /**
     * State for entertainment/media companies
     */
    public function entertainment()
    {
        return $this->state(function (array $attributes) {
            $enterpriseData = $this->getEnterpriseDataByCategory('entertainment_media');
            $nameIndex = $this->faker->numberBetween(0, count($enterpriseData['names']) - 1);

            return [
                'name' => $enterpriseData['names'][$nameIndex],
                'website' => 'https://'.$enterpriseData['websites'][$nameIndex],
                'data' => json_encode([
                    'description' => $enterpriseData['descriptions'][$nameIndex],
                    'category' => 'entertainment_media',
                    'size' => $this->faker->randomElement(['small', 'medium', 'large', 'enterprise']),
                    'industry' => 'Entertainment & Media',
                ]),
            ];
        });
    }

    /**
     * State for technology/software companies
     */
    public function technology()
    {
        return $this->state(function (array $attributes) {
            $enterpriseData = $this->getEnterpriseDataByCategory('technology_software');
            $nameIndex = $this->faker->numberBetween(0, count($enterpriseData['names']) - 1);

            return [
                'name' => $enterpriseData['names'][$nameIndex],
                'website' => 'https://'.$enterpriseData['websites'][$nameIndex],
                'data' => json_encode([
                    'description' => $enterpriseData['descriptions'][$nameIndex],
                    'category' => 'technology_software',
                    'size' => $this->faker->randomElement(['startup', 'small', 'medium', 'large', 'enterprise']),
                    'industry' => 'Technology & Software',
                ]),
            ];
        });
    }

    /**
     * State for legal/financial companies
     */
    public function legal()
    {
        return $this->state(function (array $attributes) {
            $enterpriseData = $this->getEnterpriseDataByCategory('legal_financial');
            $nameIndex = $this->faker->numberBetween(0, count($enterpriseData['names']) - 1);

            return [
                'name' => $enterpriseData['names'][$nameIndex],
                'website' => 'https://'.$enterpriseData['websites'][$nameIndex],
                'data' => json_encode([
                    'description' => $enterpriseData['descriptions'][$nameIndex],
                    'category' => 'legal_financial',
                    'size' => $this->faker->randomElement(['medium', 'large', 'enterprise']),
                    'industry' => 'Legal & Financial Services',
                ]),
            ];
        });
    }

    /**
     * State for marketing/advertising companies
     */
    public function marketing()
    {
        return $this->state(function (array $attributes) {
            $enterpriseData = $this->getEnterpriseDataByCategory('marketing_advertising');
            $nameIndex = $this->faker->numberBetween(0, count($enterpriseData['names']) - 1);

            return [
                'name' => $enterpriseData['names'][$nameIndex],
                'website' => 'https://'.$enterpriseData['websites'][$nameIndex],
                'data' => json_encode([
                    'description' => $enterpriseData['descriptions'][$nameIndex],
                    'category' => 'marketing_advertising',
                    'size' => $this->faker->randomElement(['startup', 'small', 'medium', 'large']),
                    'industry' => 'Marketing & Advertising',
                ]),
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
                'creator_id' => function () use ($teamId) {
                    $users = User::whereHas('teams', function ($q) use ($teamId) {
                        $q->where('team_id', $teamId);
                    })->get();

                    return $users->isNotEmpty() ? $users->random()->id : null;
                },
            ];
        });
    }

    private function getEnterpriseDataByCategory($categoryKey)
    {
        $categories = [
            'medical_pharmaceutical' => [
                'names' => ['Pfizer', 'Johnson & Johnson', 'Novartis', 'Roche', 'Merck KGaA'],
                'websites' => ['pfizer.com', 'jnj.com', 'novartis.com', 'roche.com', 'merckgroup.com'],
                'descriptions' => [
                    'Global pharmaceutical company focused on innovative medicines',
                    'Multinational medical devices and pharmaceutical corporation',
                    'Swiss multinational pharmaceutical corporation',
                    'Swiss multinational healthcare company',
                    'German multinational pharmaceutical and life sciences company',
                ],
                'countries' => ['us', 'ch', 'de', 'uk', 'fr'],
            ],
            'entertainment_media' => [
                'names' => ['Netflix', 'Disney', 'Warner Bros', 'Universal Pictures', 'Sony Pictures'],
                'websites' => ['netflix.com', 'disney.com', 'warnerbros.com', 'universalpictures.com', 'sonypictures.com'],
                'descriptions' => [
                    'Global streaming entertainment service',
                    'Diversified multinational mass media corporation',
                    'American entertainment conglomerate',
                    'American film studio owned by Comcast',
                    'American diversified entertainment company',
                ],
                'countries' => ['us', 'gb', 'ca', 'au', 'jp'],
            ],
            'technology_software' => [
                'names' => ['Microsoft', 'Google', 'Apple', 'Amazon', 'Meta'],
                'websites' => ['microsoft.com', 'google.com', 'apple.com', 'amazon.com', 'meta.com'],
                'descriptions' => [
                    'Multinational technology corporation',
                    'Multinational technology company specializing in Internet services',
                    'Multinational technology company specializing in consumer electronics',
                    'Multinational technology company focusing on e-commerce and cloud computing',
                    'Multinational technology conglomerate focusing on social media',
                ],
                'countries' => ['us', 'ie', 'sg', 'jp', 'de'],
            ],
            'legal_financial' => [
                'names' => ['Baker & McKenzie', 'Clifford Chance', 'Goldman Sachs', 'JPMorgan Chase', 'KPMG'],
                'websites' => ['bakermckenzie.com', 'cliffordchance.com', 'goldmansachs.com', 'jpmorganchase.com', 'kpmg.com'],
                'descriptions' => [
                    'Global law firm providing legal services',
                    'International law firm headquartered in London',
                    'American multinational investment bank',
                    'American multinational investment bank and financial services company',
                    'Multinational professional services network',
                ],
                'countries' => ['us', 'gb', 'ch', 'sg', 'de'],
            ],
            'marketing_advertising' => [
                'names' => ['WPP', 'Omnicom', 'Publicis', 'Interpublic', 'Dentsu'],
                'websites' => ['wpp.com', 'omnicomgroup.com', 'publicisgroupe.com', 'interpublic.com', 'dentsu.com'],
                'descriptions' => [
                    'British multinational advertising and public relations company',
                    'American global media, marketing and corporate communications company',
                    'French multinational advertising and public relations company',
                    'American publicly traded advertising company',
                    'Japanese international advertising and public relations company',
                ],
                'countries' => ['gb', 'us', 'fr', 'jp', 'de'],
            ],
        ];

        return $categories[$categoryKey];
    }

    private function generatePhone($country)
    {
        $phoneFormats = [
            'us' => '+1 ('.$this->faker->numberBetween(200, 999).') '.$this->faker->numberBetween(100, 999).'-'.$this->faker->numberBetween(1000, 9999),
            'gb' => '+44 20 '.$this->faker->numberBetween(1000, 9999).' '.$this->faker->numberBetween(1000, 9999),
            'de' => '+49 '.$this->faker->numberBetween(30, 89).' '.$this->faker->numberBetween(10000000, 99999999),
            'fr' => '+33 1 '.$this->faker->numberBetween(10, 99).' '.$this->faker->numberBetween(10, 99).' '.$this->faker->numberBetween(10, 99).' '.$this->faker->numberBetween(10, 99),
            'es' => '+34 '.$this->faker->numberBetween(600, 999).' '.$this->faker->numberBetween(100, 999).' '.$this->faker->numberBetween(100, 999),
            'ch' => '+41 '.$this->faker->numberBetween(21, 79).' '.$this->faker->numberBetween(100, 999).' '.$this->faker->numberBetween(10, 99).' '.$this->faker->numberBetween(10, 99),
            'jp' => '+81 3-'.$this->faker->numberBetween(1000, 9999).'-'.$this->faker->numberBetween(1000, 9999),
            'ca' => '+1 ('.$this->faker->numberBetween(200, 999).') '.$this->faker->numberBetween(100, 999).'-'.$this->faker->numberBetween(1000, 9999),
            'au' => '+61 2 '.$this->faker->numberBetween(1000, 9999).' '.$this->faker->numberBetween(1000, 9999),
            'sg' => '+65 '.$this->faker->numberBetween(6000, 9999).' '.$this->faker->numberBetween(1000, 9999),
            'ie' => '+353 1 '.$this->faker->numberBetween(100, 999).' '.$this->faker->numberBetween(1000, 9999),
        ];

        return $phoneFormats[$country] ?? '+1 ('.$this->faker->numberBetween(200, 999).') '.$this->faker->numberBetween(100, 999).'-'.$this->faker->numberBetween(1000, 9999);
    }

    private function generateAddress($country)
    {
        $addresses = [
            'us' => [
                'address' => $this->faker->numberBetween(100, 9999).' '.$this->faker->streetName(),
                'postal_code' => $this->faker->postcode(),
                'locality' => $this->faker->city(),
                'province' => $this->faker->state(),
            ],
            'gb' => [
                'address' => $this->faker->numberBetween(1, 999).' '.$this->faker->streetName(),
                'postal_code' => $this->faker->postcode(),
                'locality' => $this->faker->city(),
                'province' => $this->faker->randomElement(['England', 'Scotland', 'Wales', 'Northern Ireland']),
            ],
            'de' => [
                'address' => $this->faker->streetName().' '.$this->faker->numberBetween(1, 200),
                'postal_code' => $this->faker->numberBetween(10000, 99999),
                'locality' => $this->faker->city(),
                'province' => $this->faker->randomElement(['Bayern', 'Berlin', 'Hamburg', 'Nordrhein-Westfalen']),
            ],
            'fr' => [
                'address' => $this->faker->numberBetween(1, 999).' '.$this->faker->streetName(),
                'postal_code' => $this->faker->numberBetween(10000, 95999),
                'locality' => $this->faker->city(),
                'province' => $this->faker->randomElement(['Île-de-France', 'Provence-Alpes-Côte d\'Azur', 'Auvergne-Rhône-Alpes']),
            ],
            'es' => [
                'address' => $this->faker->streetName().' '.$this->faker->numberBetween(1, 200),
                'postal_code' => $this->faker->numberBetween(10000, 52999),
                'locality' => $this->faker->city(),
                'province' => $this->faker->randomElement(['Madrid', 'Barcelona', 'Valencia', 'Sevilla']),
            ],
            'ch' => [
                'address' => $this->faker->streetName().' '.$this->faker->numberBetween(1, 200),
                'postal_code' => $this->faker->numberBetween(1000, 9999),
                'locality' => $this->faker->city(),
                'province' => $this->faker->randomElement(['Zürich', 'Geneva', 'Basel', 'Bern']),
            ],
            'jp' => [
                'address' => $this->faker->numberBetween(1, 99).'-'.$this->faker->numberBetween(1, 99).' '.$this->faker->streetName(),
                'postal_code' => $this->faker->numberBetween(100, 999).'-'.$this->faker->numberBetween(1000, 9999),
                'locality' => $this->faker->city(),
                'province' => $this->faker->randomElement(['Tokyo', 'Osaka', 'Kyoto', 'Yokohama']),
            ],
            'ca' => [
                'address' => $this->faker->numberBetween(100, 9999).' '.$this->faker->streetName(),
                'postal_code' => $this->faker->postcode(),
                'locality' => $this->faker->city(),
                'province' => $this->faker->randomElement(['Ontario', 'Quebec', 'British Columbia', 'Alberta']),
            ],
            'au' => [
                'address' => $this->faker->numberBetween(1, 999).' '.$this->faker->streetName(),
                'postal_code' => $this->faker->numberBetween(1000, 9999),
                'locality' => $this->faker->city(),
                'province' => $this->faker->randomElement(['New South Wales', 'Victoria', 'Queensland', 'Western Australia']),
            ],
            'sg' => [
                'address' => $this->faker->numberBetween(1, 999).' '.$this->faker->streetName(),
                'postal_code' => $this->faker->numberBetween(100000, 999999),
                'locality' => 'Singapore',
                'province' => 'Singapore',
            ],
            'ie' => [
                'address' => $this->faker->numberBetween(1, 999).' '.$this->faker->streetName(),
                'postal_code' => $this->faker->randomElement(['D01', 'D02', 'D03', 'D04']).' '.$this->faker->bothify('????'),
                'locality' => $this->faker->city(),
                'province' => $this->faker->randomElement(['Dublin', 'Cork', 'Galway', 'Limerick']),
            ],
        ];

        return $addresses[$country] ?? $addresses['us'];
    }

    private function getIndustryByCategory($category)
    {
        $industries = [
            'medical_pharmaceutical' => 'Healthcare & Pharmaceutical',
            'entertainment_media' => 'Entertainment & Media',
            'technology_software' => 'Technology & Software',
            'legal_financial' => 'Legal & Financial Services',
            'marketing_advertising' => 'Marketing & Advertising',
        ];

        return $industries[$category] ?? 'General Business';
    }
}
