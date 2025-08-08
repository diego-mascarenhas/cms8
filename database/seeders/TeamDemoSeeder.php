<?php

namespace Database\Seeders;

use App\Models\Enterprise;
use App\Models\Project;
use App\Models\Fare;
use App\Models\Software;
use App\Models\Certification;
use App\Models\ContactPortfolio;
use App\Models\Contact;
use Database\Factories\ClientFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeamDemoSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		$this->command->info('Seeding Team Demo data...');

		// Create demo clients (enterprises)
		$this->command->info('Creating demo clients...');
		ClientFactory::new()
			->count(15)
			->client()
			->create();

		// Create demo projects (reduced to half)
		$this->command->info('Creating demo projects...');
		Project::factory()
			->count(12)
			->withFares()
			->create();

		// Create demo fares with units (reduced to half)
		$this->command->info('Creating demo fares with units...');
		Fare::factory()
			->count(6)
			->withUnits()
			->create();

		// Create fare-unit relationships for demo team
		$this->createDemoFareUnits();

		// Create demo language variants
		$this->createDemoLanguageVariants();

		// Create demo collaborators with language variants
		$this->createDemoCollaboratorsWithLanguages();

		// Assign language variants to project services
		$this->assignLanguageVariantsToServices();

		// Update basic language codes to specific variants
		$this->updateBasicLanguageCodesToVariants();

		// Ensure each service language combination has at least one collaborator
		$this->ensureServiceLanguageCoverage();

		// Assign collaborators to projects (95% acceptance rate)
		$this->assignCollaboratorsToProjects();

		// Create collaborator ratings and services
		$this->createCollaboratorRatingsAndServices();

		// Create demo software
		$this->command->info('Creating demo software...');
		Software::factory()
			->count(30)
			->create();

		// Create demo certifications
		$this->command->info('Creating demo certifications...');
		Certification::factory()
			->count(20)
			->create();

		// Create demo experience/portfolio items
		$this->command->info('Creating demo experience/portfolio items...');
		ContactPortfolio::factory()
			->count(40)
			->create();

		$this->command->info('Team Demo data seeded successfully!');

		// Also seed team-scoped payments for the demo team
		$this->seedDemoPayments();

		// Seed demo billing addresses and invoices
		$this->seedDemoBillingAndInvoices();

		// Seed demo services for Team 1
		$this->seedDemoServices();

		// Seed demo contacts
		$this->seedDemoContacts();
	}

    private function seedDemoServices(): void
    {
        $this->command->info('🛠️ Creating demo services for Team 1...');

        // Hosting services (annual, active)
        \App\Models\Service::factory()
            ->forTeam1()
            ->active()
            ->hosting()
            ->count(5)
            ->create();

        // Generic active services
        \App\Models\Service::factory()
            ->forTeam1()
            ->active()
            ->count(15)
            ->create();

        // Mixed operation services (buy/sell)
        \App\Models\Service::factory()
            ->forTeam1()
            ->count(10)
            ->create();

        $total = \App\Models\Service::whereHas('enterprise', function($q){ $q->where('team_id', 1); })->count();
        $this->command->info("✅ Demo services created for Team 1. Total services: {$total}");
    }

    private function seedDemoBillingAndInvoices(): void
    {
        $this->command->info('🏷️ Creating demo billing addresses and invoices...');

        // Ensure we have a tax status type to reference
        $taxStatuses = \App\Models\EnterpriseTaxStatusType::pluck('id')->all();
        if (empty($taxStatuses)) {
            $this->call(EnterpriseTaxStatusTypeSeeder::class);
            $taxStatuses = \App\Models\EnterpriseTaxStatusType::pluck('id')->all();
        }

        // Create one billing address per enterprise (team 1)
        $enterprises = \App\Models\Enterprise::where('team_id', 1)->get();
        foreach ($enterprises as $enterprise) {
            \App\Models\EnterpriseBillingAddress::firstOrCreate(
                [
                    'enterprise_id' => $enterprise->id,
                    'name' => $enterprise->name . ' Billing',
                ],
                [
                    'identification_number' => 'ID' . str_pad((string)$enterprise->id, 6, '0', STR_PAD_LEFT),
                    'tax_status_type_id' => collect($taxStatuses)->random(),
                    'address' => 'Main St 123',
                    'postal_code' => '28001',
                    'locality' => collect(['Madrid','Barcelona','Valencia','Sevilla','Bilbao','Málaga'])->random(),
                    'province' => collect(['Madrid','Barcelona','Valencia','Sevilla','Vizcaya','Málaga'])->random(),
                    'country' => 'ES',
                    'status' => 1,
                ]
            );
        }

        // Create invoices with items for 10 random enterprises
        $invoiceType = \App\Models\InvoiceType::first();
        $targets = $enterprises->random(min(10, max(1, $enterprises->count())));
        foreach ($targets as $enterprise) {
            $billing = \App\Models\EnterpriseBillingAddress::where('enterprise_id', $enterprise->id)->first();

            // Build diverse items from fares (services) of the team when possible
            $fares = \App\Models\Fare::where('team_id', 1)->inRandomOrder()->take(3)->get();
            $items = [];
            foreach ($fares as $fare) {
                $items[] = [
                    'description' => $fare->name,
                    'quantity' => rand(1, 5) * 100, // e.g., 100/200/300 words or minutes
                    'unit_price' => round(rand(8, 20) / 100, 2), // e.g., 0.08 - 0.20
                    'discount' => 0,
                    'tax_percentage' => 21,
                ];
            }
            // fallback default items if no fares
            if (empty($items)) {
                $items = [
                    ['description' => 'Translation service', 'quantity' => 1000, 'unit_price' => 0.08, 'discount' => 0, 'tax_percentage' => 21],
                    ['description' => 'Review service', 'quantity' => 1000, 'unit_price' => 0.03, 'discount' => 0, 'tax_percentage' => 21],
                ];
            }

            $gross = 0;
            foreach ($items as $it) {
                $gross += ($it['quantity'] * $it['unit_price']) - $it['discount'];
            }
            $discount = 0;
            $total = $gross; // taxes not included in this simple example

            $invoice = \App\Models\Invoice::create([
                'enterprise_id' => $enterprise->id,
                'billing_id' => $billing?->id,
                'type_id' => $invoiceType?->id ?? 1,
                'operation' => 'sell',
                'number' => (string)now()->format('Ymd') . '-' . rand(100, 999),
                'date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'gross_amount' => $gross,
                'discount' => $discount,
                'total_amount' => $total,
                'balance' => $total,
                'status' => 1,
            ]);

            foreach ($items as $it) {
                \App\Models\InvoiceItem::create(array_merge($it, [
                    'invoice_id' => $invoice->id,
                ]));
            }
        }

        $count = \App\Models\Invoice::count();
        $this->command->info("✅ Demo invoices created. Total invoices: {$count}");
    }

    /**
     * Seed demo payment accounts and payments for Team 1
     */
    private function seedDemoPayments(): void
    {
        $this->command->info('💳 Creating demo payment accounts and payments for Team 1...');

        // Ensure payment accounts exist for Team 1
        $accountsBefore = \App\Models\PaymentAccount::where('team_id', 1)->count();
        if ($accountsBefore === 0) {
            $this->call(PaymentAccountSeeder::class);
        }

        // Create sample payments for Team 1
        $this->call(PaymentSeeder::class);

        $accountsAfter = \App\Models\PaymentAccount::where('team_id', 1)->count();
        $payments = \App\Models\Payment::where('team_id', 1)->count();

        $this->command->info("✅ Demo payments ready. Accounts: {$accountsAfter}, Payments: {$payments}");
    }

	/**
	 * Create language variants for demo team
	 */
	private function createDemoLanguageVariants()
	{
		$this->command->info('🌍 Creating demo language variants...');

		// Check if team 1 already has language variants
		$existingVariants = \App\Models\LanguageVariant::where('team_id', 1)->count();
		if ($existingVariants > 0) {
			$this->command->warn("⚠️ Team 1 already has {$existingVariants} language variants. Skipping creation.");
			return;
		}

		// Demo language variants for team 1
		$demoVariants = [
			['code' => 'en-US', 'name' => 'English (US)', 'country_code' => 'US'],
			['code' => 'en-GB', 'name' => 'English (UK)', 'country_code' => 'GB'],
			['code' => 'es-ES', 'name' => 'Spanish (Spain)', 'country_code' => 'ES'],
			['code' => 'es-MX', 'name' => 'Spanish (Mexico)', 'country_code' => 'MX'],
			['code' => 'es-AR', 'name' => 'Spanish (Argentina)', 'country_code' => 'AR'],
			['code' => 'fr-FR', 'name' => 'French (France)', 'country_code' => 'FR'],
			['code' => 'fr-CA', 'name' => 'French (Canada)', 'country_code' => 'CA'],
			['code' => 'de-DE', 'name' => 'German (Germany)', 'country_code' => 'DE'],
			['code' => 'it-IT', 'name' => 'Italian (Italy)', 'country_code' => 'IT'],
			['code' => 'pt-BR', 'name' => 'Portuguese (Brazil)', 'country_code' => 'BR'],
			['code' => 'pt-PT', 'name' => 'Portuguese (Portugal)', 'country_code' => 'PT'],
			['code' => 'nl-NL', 'name' => 'Dutch (Netherlands)', 'country_code' => 'NL'],
			['code' => 'sv-SE', 'name' => 'Swedish (Sweden)', 'country_code' => 'SE'],
			['code' => 'no-NO', 'name' => 'Norwegian (Norway)', 'country_code' => 'NO'],
			['code' => 'da-DK', 'name' => 'Danish (Denmark)', 'country_code' => 'DK'],
		];

		$created = 0;
		$skipped = 0;

		foreach ($demoVariants as $variant) {
			// Check if variant already exists for team 1
			$existingVariant = \App\Models\LanguageVariant::where('code', $variant['code'])
				->where('team_id', 1)
				->first();

			if ($existingVariant) {
				$skipped++;
				$this->command->info("⏭️ Skipped existing variant: {$variant['code']}");
			} else {
				// Create new variant for team 1
				\App\Models\LanguageVariant::create([
					'code' => $variant['code'],
					'name' => $variant['name'],
					'country_code' => $variant['country_code'],
					'team_id' => 1,
					'created_at' => now(),
					'updated_at' => now(),
				]);
				$created++;
				$this->command->info("✅ Created demo variant: {$variant['code']}");
			}
		}

		$this->command->info('📊 Demo language variants creation summary:');
		$this->command->info("   - New variants created: {$created}");
		$this->command->info("   - Variants skipped: {$skipped}");
		$this->command->info("   - Total variants for team 1: " . \App\Models\LanguageVariant::where('team_id', 1)->count());
		$this->command->info('✅ Demo language variants creation completed successfully!');
	}

	/**
	 * Assign language variants to project services
	 */
	private function assignLanguageVariantsToServices()
	{
		$this->command->info('🔗 Assigning language variants to project services...');

		// Get common language variants for team 1
		$enUS = \App\Models\LanguageVariant::where('code', 'en-US')->where('team_id', 1)->first();
		$enGB = \App\Models\LanguageVariant::where('code', 'en-GB')->where('team_id', 1)->first();
		$esES = \App\Models\LanguageVariant::where('code', 'es-ES')->where('team_id', 1)->first();
		$esMX = \App\Models\LanguageVariant::where('code', 'es-MX')->where('team_id', 1)->first();
		$frFR = \App\Models\LanguageVariant::where('code', 'fr-FR')->where('team_id', 1)->first();
		$deDE = \App\Models\LanguageVariant::where('code', 'de-DE')->where('team_id', 1)->first();

		// Check if variants exist
		if (!$enUS || !$esES) {
			$this->command->warn('⚠️ Required language variants not found. Skipping assignment.');
			return;
		}

		// Get all project fares (services) for team 1 that don't have language variants
		$projectFares = \App\Models\ProjectFare::whereHas('project', function($query) {
			$query->where('team_id', 1);
		})->whereNull('source_language_code')
		  ->whereNull('target_language_code')
		  ->get();

		if ($projectFares->isEmpty()) {
			$this->command->info('✅ All project services already have language variants assigned.');
			return;
		}

		$updated = 0;
		$skipped = 0;

		// Common language combinations for demo
		$languageCombinations = [
			['source' => $enUS, 'target' => $esES],
			['source' => $enGB, 'target' => $esES],
			['source' => $enUS, 'target' => $esMX],
			['source' => $frFR, 'target' => $esES],
			['source' => $deDE, 'target' => $esES],
			['source' => $esES, 'target' => $enUS],
			['source' => $esMX, 'target' => $enUS],
		];

				foreach ($projectFares as $index => $projectFare) {
			// Use different combinations to create variety
			$combination = $languageCombinations[$index % count($languageCombinations)];

			$projectFare->update([
				'source_language_code' => $combination['source']->code,
				'target_language_code' => $combination['target']->code,
			]);

			$updated++;
			$this->command->info("✅ Updated service: {$projectFare->fare->name} ({$combination['source']->code} → {$combination['target']->code})");
		}

		$this->command->info('📊 Language variants assignment summary:');
		$this->command->info("   - Services updated: {$updated}");
		$this->command->info("   - Services skipped: {$skipped}");
		$this->command->info("   - Total project services with variants: " . \App\Models\ProjectFare::whereHas('project', function($query) {
			$query->where('team_id', 1);
		})->whereNotNull('source_language_code')->whereNotNull('target_language_code')->count());
		$this->command->info('✅ Language variants assignment completed successfully!');
	}

	/**
	 * Update basic language codes to specific variants
	 */
	private function updateBasicLanguageCodesToVariants()
	{
		$this->command->info('🔄 Updating basic language codes to specific variants...');

		// Mapping of basic codes to specific variants
		$languageMapping = [
			'en' => 'en-US',
			'de' => 'de-DE',
			'fr' => 'fr-FR',
			'es' => 'es-ES',
			'it' => 'it-IT',
			'pt' => 'pt-PT',
			'nl' => 'nl-NL',
			'sv' => 'sv-SE',
			'da' => 'da-DK',
			'no' => 'nb-NO',
			'fi' => 'fi-FI',
			'pl' => 'pl-PL',
			'ru' => 'ru-RU',
			'ja' => 'ja-JP',
			'ko' => 'ko-KR',
			'zh' => 'zh-CN',
			'ar' => 'ar-SA',
			'tr' => 'tr-TR',
		];

		$updated = 0;
		$skipped = 0;

		// Get all project fares for team 1
		$projectFares = \App\Models\ProjectFare::whereHas('project', function($query) {
			$query->where('team_id', 1);
		})->get();

		foreach ($projectFares as $projectFare) {
			$sourceUpdated = false;
			$targetUpdated = false;

			// Update source language code
			if (isset($languageMapping[$projectFare->source_language_code])) {
				$newSourceCode = $languageMapping[$projectFare->source_language_code];
				// Verify the variant exists for team 1
				$variantExists = \App\Models\LanguageVariant::where('code', $newSourceCode)
					->where('team_id', 1)
					->exists();

				if ($variantExists) {
					$projectFare->source_language_code = $newSourceCode;
					$sourceUpdated = true;
				}
			}

			// Update target language code
			if (isset($languageMapping[$projectFare->target_language_code])) {
				$newTargetCode = $languageMapping[$projectFare->target_language_code];
				// Verify the variant exists for team 1
				$variantExists = \App\Models\LanguageVariant::where('code', $newTargetCode)
					->where('team_id', 1)
					->exists();

				if ($variantExists) {
					$projectFare->target_language_code = $newTargetCode;
					$targetUpdated = true;
				}
			}

			// Save if any changes were made
			if ($sourceUpdated || $targetUpdated) {
				$projectFare->save();
				$updated++;
				$this->command->info("✅ Updated service: {$projectFare->fare->name} ({$projectFare->source_language_code} → {$projectFare->target_language_code})");
			} else {
				$skipped++;
			}
		}

		$this->command->info('📊 Language codes update summary:');
		$this->command->info("   - Services updated: {$updated}");
		$this->command->info("   - Services skipped: {$skipped}");
		$this->command->info('✅ Language codes update completed successfully!');
	}

	/**
	 * Create demo collaborators with language variants
	 */
	private function createDemoCollaboratorsWithLanguages()
	{
		$this->command->info('👥 Creating demo collaborators with language variants...');

		// Get language variants for team 1
		$languageVariants = \App\Models\LanguageVariant::where('team_id', 1)->get();
		if ($languageVariants->isEmpty()) {
			$this->command->warn('⚠️ No language variants found for team 1. Skipping collaborators creation.');
			return;
		}

		// Demo collaborators with specific language combinations
		$demoCollaborators = [
			[
				'name' => 'María García',
				'email' => 'maria.garcia@demo.com',
				'phone' => '34600123456',
				'languages' => ['es-ES', 'en-US', 'fr-FR'],
				'specialties' => ['Traducción', 'Subtitulado', 'Doblaje']
			],
			[
				'name' => 'Anna Müller',
				'email' => 'anna.mueller@demo.com',
				'phone' => '4930123456',
				'languages' => ['de-DE', 'en-US', 'es-ES'],
				'specialties' => ['Traducción técnica', 'Localización']
			],
			[
				'name' => 'Sophie Dubois',
				'email' => 'sophie.dubois@demo.com',
				'phone' => '331234567',
				'languages' => ['fr-FR', 'en-GB', 'es-ES'],
				'specialties' => ['Traducción literaria', 'Audiodescripción']
			],
			[
				'name' => 'Giulia Rossi',
				'email' => 'giulia.rossi@demo.com',
				'phone' => '3902123456',
				'languages' => ['it-IT', 'en-US', 'fr-FR'],
				'specialties' => ['Traducción médica', 'Subtitulado']
			],
			[
				'name' => 'Sarah Johnson',
				'email' => 'sarah.johnson@demo.com',
				'phone' => '1555123456',
				'languages' => ['en-US', 'es-MX', 'pt-BR'],
				'specialties' => ['Transcreación', 'Marketing']
			],
			[
				'name' => 'Emma Wilson',
				'email' => 'emma.wilson@demo.com',
				'phone' => '44201234567',
				'languages' => ['en-GB', 'fr-FR', 'de-DE'],
				'specialties' => ['Traducción jurídica', 'Revisión']
			],
			[
				'name' => 'Carmen López',
				'email' => 'carmen.lopez@demo.com',
				'phone' => '3491123456',
				'languages' => ['es-ES', 'en-US', 'it-IT'],
				'specialties' => ['Traducción audiovisual', 'Voice-over']
			],
			[
				'name' => 'Isabella Silva',
				'email' => 'isabella.silva@demo.com',
				'phone' => '55111234567',
				'languages' => ['pt-BR', 'en-US', 'es-AR'],
				'specialties' => ['Localización', 'Traducción técnica']
			],
			[
				'name' => 'Nina Andersson',
				'email' => 'nina.andersson@demo.com',
				'phone' => '468123456',
				'languages' => ['sv-SE', 'en-US', 'de-DE'],
				'specialties' => ['Traducción científica', 'Subtitulado']
			],
			[
				'name' => 'Elena Popov',
				'email' => 'elena.popov@demo.com',
				'phone' => '7495123456',
				'languages' => ['ru-RU', 'en-US', 'es-ES'],
				'specialties' => ['Traducción técnica', 'Revisión']
			],
			[
				'name' => 'Yuki Tanaka',
				'email' => 'yuki.tanaka@demo.com',
				'phone' => '8131234567',
				'languages' => ['ja-JP', 'en-US', 'es-ES'],
				'specialties' => ['Traducción técnica', 'Localización']
			],
			[
				'name' => 'Min-ji Kim',
				'email' => 'minji.kim@demo.com',
				'phone' => '822123456',
				'languages' => ['ko-KR', 'en-US', 'ja-JP'],
				'specialties' => ['Traducción audiovisual', 'Subtitulado']
			],
			[
				'name' => 'Li Wei',
				'email' => 'li.wei@demo.com',
				'phone' => '86101234567',
				'languages' => ['zh-CN', 'en-US', 'es-ES'],
				'specialties' => ['Traducción técnica', 'Localización']
			],
			[
				'name' => 'Fatima Al-Zahra',
				'email' => 'fatima.alzahra@demo.com',
				'phone' => '96611123456',
				'languages' => ['ar-SA', 'en-US', 'fr-FR'],
				'specialties' => ['Traducción técnica', 'Localización']
			],
			[
				'name' => 'Zeynep Kaya',
				'email' => 'zeynep.kaya@demo.com',
				'phone' => '90212123456',
				'languages' => ['tr-TR', 'en-US', 'de-DE'],
				'specialties' => ['Traducción técnica', 'Subtitulado']
			]
		];

		$created = 0;
		$skipped = 0;

		foreach ($demoCollaborators as $collaboratorData) {
			// Check if collaborator already exists
			$existingCollaborator = \App\Models\Contact::where('email', $collaboratorData['email'])
				->where('team_id', 1)
				->first();

			if ($existingCollaborator) {
				$skipped++;
				$this->command->info("⏭️ Skipped existing collaborator: {$collaboratorData['name']}");
				continue;
			}

			// Create collaborator
			$collaborator = \App\Models\Contact::create([
				'name' => $collaboratorData['name'],
				'email' => $collaboratorData['email'],
				'phone' => $collaboratorData['phone'],
				'team_id' => 1,
				'creator_id' => 1,
				'responsible_id' => 1,
				'status_id' => 1,
				'language' => 'es',
				'country' => 724, // Spain
				'created_at' => now(),
				'updated_at' => now(),
			]);

			// Create language combinations for collaborator
			$languages = $collaboratorData['languages'];
			for ($i = 0; $i < count($languages); $i++) {
				for ($j = 0; $j < count($languages); $j++) {
					if ($i !== $j) { // Don't create self-translations
						$sourceCode = $languages[$i];
						$targetCode = $languages[$j];

						// Check if both variants exist
						$sourceVariant = $languageVariants->where('code', $sourceCode)->first();
						$targetVariant = $languageVariants->where('code', $targetCode)->first();

						if ($sourceVariant && $targetVariant) {
							\App\Models\ContactLanguageVariant::create([
								'contact_id' => $collaborator->id,
								'source_language_code' => $sourceCode,
								'target_language_code' => $targetCode,
								'proficiency_level' => rand(3, 5), // Random proficiency level 3-5
								'is_certified' => rand(0, 1), // Random certification status
								'created_at' => now(),
								'updated_at' => now(),
							]);
						}
					}
				}
			}

			$created++;
			$this->command->info("✅ Created collaborator: {$collaboratorData['name']} with " . count($collaboratorData['languages']) . " languages");
		}

		$this->command->info('📊 Demo collaborators creation summary:');
		$this->command->info("   - New collaborators created: {$created}");
		$this->command->info("   - Collaborators skipped: {$skipped}");
		$this->command->info("   - Total collaborators for team 1: " . \App\Models\Contact::where('team_id', 1)->whereHas('languageVariants')->count());
		$this->command->info('✅ Demo collaborators creation completed successfully!');
	}

	/**
	 * Ensure each service language combination has at least one collaborator
	 */
	private function ensureServiceLanguageCoverage()
	{
		$this->command->info('🔍 Ensuring service language coverage...');

		// Get all project services for team 1
		$projectServices = \App\Models\ProjectFare::whereHas('project', function($query) {
			$query->where('team_id', 1);
		})->whereNotNull('source_language_code')
		  ->whereNotNull('target_language_code')
		  ->get();

		// Get all collaborators with their language variants
		$collaborators = \App\Models\Contact::with(['languageVariants'])
			->where('team_id', 1)
			->whereHas('languageVariants')
			->get();

		$uncoveredServices = [];
		$coveredServices = [];

		foreach ($projectServices as $service) {
			$sourceCode = $service->source_language_code;
			$targetCode = $service->target_language_code;

			// Check if any collaborator has this language combination
			$hasCoverage = false;
			foreach ($collaborators as $collaborator) {
				foreach ($collaborator->languageVariants as $variant) {
					if ($variant->source_language_code === $sourceCode &&
						$variant->target_language_code === $targetCode) {
						$hasCoverage = true;
						break 2;
					}
				}
			}

			if ($hasCoverage) {
				$coveredServices[] = "{$sourceCode} → {$targetCode}";
			} else {
				$uncoveredServices[] = "{$sourceCode} → {$targetCode}";
			}
		}

		$this->command->info("📊 Service language coverage summary:");
		$this->command->info("   - Covered combinations: " . count($coveredServices));
		$this->command->info("   - Uncovered combinations: " . count($uncoveredServices));

		if (!empty($uncoveredServices)) {
			$this->command->warn("⚠️ Uncovered language combinations:");
			foreach (array_slice($uncoveredServices, 0, 10) as $combination) {
				$this->command->warn("   - {$combination}");
			}
			if (count($uncoveredServices) > 10) {
				$this->command->warn("   ... and " . (count($uncoveredServices) - 10) . " more");
			}
		}

		$this->command->info('✅ Service language coverage check completed!');

		// Create additional collaborators to cover missing combinations
		if (!empty($uncoveredServices)) {
			$this->createAdditionalCollaboratorsForCoverage($uncoveredServices);
		}
	}

	/**
	 * Assign collaborators to projects (95% acceptance rate)
	 */
	private function assignCollaboratorsToProjects()
	{
		$this->command->info('📋 Assigning collaborators to projects (95% acceptance rate)...');

		// Get all projects for team 1
		$projects = \App\Models\Project::where('team_id', 1)->get();

		// Get all collaborators
		$collaborators = \App\Models\Contact::where('team_id', 1)
			->whereHas('languageVariants')
			->get();

		if ($projects->isEmpty() || $collaborators->isEmpty()) {
			$this->command->warn('⚠️ No projects or collaborators found. Skipping assignment.');
			return;
		}

		$totalAssignments = 0;
		$acceptedAssignments = 0;
		$rejectedAssignments = 0;

		// For each project, assign 2-4 collaborators
		foreach ($projects as $project) {
			$numCollaborators = rand(2, 4);
			$selectedCollaborators = $collaborators->random($numCollaborators);

						foreach ($selectedCollaborators as $collaborator) {
				// Check if assignment already exists
				$existingAssignment = \App\Models\ContactProject::where('contact_id', $collaborator->id)
					->where('project_id', $project->id)
					->first();

				if ($existingAssignment) {
					continue; // Skip if already assigned
				}

				// 95% acceptance rate
				$isAccepted = rand(1, 100) <= 95;
				$status = $isAccepted ? 'accepted' : 'rejected';

				// Create project assignment
				\App\Models\ContactProject::create([
					'contact_id' => $collaborator->id,
					'project_id' => $project->id,
					'status' => $status,
					'sent_at' => now(),
					'viewed_at' => $isAccepted ? now() : null,
					'responded_at' => $isAccepted ? now() : null,
					'response_message' => $isAccepted ? 'Interesado en el proyecto' : 'No disponible en este momento',
					'created_at' => now(),
					'updated_at' => now(),
				]);

				$totalAssignments++;
				if ($isAccepted) {
					$acceptedAssignments++;
				} else {
					$rejectedAssignments++;
				}
			}
		}

		$acceptanceRate = $totalAssignments > 0 ? round(($acceptedAssignments / $totalAssignments) * 100, 1) : 0;

		$this->command->info('📊 Project assignments summary:');
		$this->command->info("   - Total assignments: {$totalAssignments}");
		$this->command->info("   - Accepted: {$acceptedAssignments}");
		$this->command->info("   - Rejected: {$rejectedAssignments}");
		$this->command->info("   - Acceptance rate: {$acceptanceRate}%");
		$this->command->info('✅ Project assignments completed successfully!');
	}

	/**
	 * Create additional collaborators to cover missing language combinations
	 */
	private function createAdditionalCollaboratorsForCoverage($uncoveredServices)
	{
		$this->command->info('👥 Creating additional collaborators for missing language combinations...');

		// Get language variants for team 1
		$languageVariants = \App\Models\LanguageVariant::where('team_id', 1)->get();

		// Analyze uncovered combinations to find the most common ones
		$combinationCounts = [];
		foreach ($uncoveredServices as $combination) {
			$combinationCounts[$combination] = ($combinationCounts[$combination] ?? 0) + 1;
		}

		// Sort by frequency and take the top combinations
		arsort($combinationCounts);
		$topCombinations = array_slice($combinationCounts, 0, 10, true);

		$created = 0;
		$maxAdditionalCollaborators = 5; // Limit to avoid too many

		foreach ($topCombinations as $combination => $count) {
			if ($created >= $maxAdditionalCollaborators) break;

			// Parse the combination
			$parts = explode(' → ', $combination);
			if (count($parts) !== 2) continue;

			$sourceCode = $parts[0];
			$targetCode = $parts[1];

			// Skip self-translations (same language)
			if ($sourceCode === $targetCode) continue;

			// Create a specialized collaborator for this combination
			$collaboratorName = $this->generateCollaboratorName($sourceCode, $targetCode);
			$email = strtolower(str_replace(' ', '.', $collaboratorName)) . '.specialist@demo.com';

			// Check if collaborator already exists
			$existingCollaborator = \App\Models\Contact::where('email', $email)
				->where('team_id', 1)
				->first();

			if ($existingCollaborator) {
				continue;
			}

			// Create collaborator
			$collaborator = \App\Models\Contact::create([
				'name' => $collaboratorName,
				'email' => $email,
				'phone' => '34600000000', // Generic Spanish number
				'team_id' => 1,
				'creator_id' => 1,
				'responsible_id' => 1,
				'status_id' => 1,
				'language' => 'es',
				'country' => 724, // Spain
				'created_at' => now(),
				'updated_at' => now(),
			]);

			// Create the specific language combination
			\App\Models\ContactLanguageVariant::create([
				'contact_id' => $collaborator->id,
				'source_language_code' => $sourceCode,
				'target_language_code' => $targetCode,
				'proficiency_level' => 5, // High proficiency for specialists
				'is_certified' => 1, // Certified for specialists
				'created_at' => now(),
				'updated_at' => now(),
			]);

			$created++;
			$this->command->info("✅ Created specialist collaborator: {$collaboratorName} for {$combination}");
		}

		$this->command->info("📊 Additional collaborators created: {$created}");
		$this->command->info('✅ Additional collaborators creation completed!');
	}

	/**
	 * Generate a collaborator name based on language combination
	 */
	private function generateCollaboratorName($sourceCode, $targetCode)
	{
		$sourceName = $this->getLanguageName($sourceCode);
		$targetName = $this->getLanguageName($targetCode);

		$names = [
			'Ana', 'María', 'Carmen', 'Isabel', 'Elena', 'Sofia', 'Laura', 'Patricia', 'Rosa', 'Teresa'
		];

		$surnames = [
			'García', 'Rodríguez', 'González', 'Fernández', 'López', 'Martínez', 'Sánchez', 'Pérez', 'Gómez', 'Martin'
		];

		$name = $names[array_rand($names)];
		$surname = $surnames[array_rand($surnames)];

		return "{$name} {$surname} ({$sourceName}→{$targetName})";
	}

	/**
	 * Get language name from code
	 */
	private function getLanguageName($code)
	{
		$languageMap = [
			'en-US' => 'EN', 'en-GB' => 'EN',
			'es-ES' => 'ES', 'es-MX' => 'ES',
			'fr-FR' => 'FR', 'fr-CA' => 'FR',
			'de-DE' => 'DE', 'de-AT' => 'DE',
			'it-IT' => 'IT', 'pt-BR' => 'PT',
			'pt-PT' => 'PT', 'nl-NL' => 'NL',
			'sv-SE' => 'SV', 'da-DK' => 'DA',
			'no-NO' => 'NO', 'fi-FI' => 'FI',
			'pl-PL' => 'PL', 'ru-RU' => 'RU',
			'ja-JP' => 'JA', 'ko-KR' => 'KO',
			'zh-CN' => 'ZH', 'ar-SA' => 'AR',
			'tr-TR' => 'TR'
		];

		return $languageMap[$code] ?? $code;
	}

	/**
	 * Create collaborator ratings and services
	 */
	private function createCollaboratorRatingsAndServices()
	{
		$this->command->info('⭐ Creating collaborator ratings and services...');

		// Get all collaborators
		$collaborators = \App\Models\Contact::where('team_id', 1)
			->whereHas('languageVariants')
			->get();

		// Get all fares (services) for team 1
		$fares = \App\Models\Fare::where('team_id', 1)->get();

		if ($collaborators->isEmpty() || $fares->isEmpty()) {
			$this->command->warn('⚠️ No collaborators or fares found. Skipping ratings and services creation.');
			return;
		}

		$ratingsCreated = 0;
		$servicesCreated = 0;

		foreach ($collaborators as $collaborator) {
			// Assign rating with realistic distribution: 70% Validada, 20% Interesante, 10% others
			// Ojo tiene prioridad sobre Lista negra (mayor probabilidad)
			$random = rand(1, 100);
			if ($random <= 70) {
				$ratingId = 12; // Validada
			} elseif ($random <= 90) {
				$ratingId = 13; // Interesante
			} elseif ($random <= 95) {
				$ratingId = 14; // Ojo (ID 14) - 5% probabilidad
			} else {
				$ratingId = 15; // Lista negra (ID 15) - 5% probabilidad
			}
			$collaborator->update(['valoration_id' => $ratingId]);
			$ratingsCreated++;

			// Assign 3-6 random services to each collaborator
			$numServices = rand(3, 6);
			$selectedFares = $fares->random($numServices);

			foreach ($selectedFares as $fare) {
												// Check if service already exists for this collaborator
				$existingService = DB::table('contact_fare')->where('contact_id', $collaborator->id)
					->where('fare_id', $fare->id)
					->first();

				if (!$existingService) {
					// Get random language combination for this collaborator
					$languageVariant = $collaborator->languageVariants->random();

					// Create service with random price
					DB::table('contact_fare')->insert([
						'contact_id' => $collaborator->id,
						'fare_id' => $fare->id,
						'source_language_code' => $languageVariant->source_language_code,
						'target_language_code' => $languageVariant->target_language_code,
						'price' => rand(15, 50) + (rand(0, 99) / 100), // Random price between 15-50 EUR
						'unit_id' => $fare->units->first()?->id ?? null,
						'currency_code' => 'EUR',
						'created_at' => now(),
						'updated_at' => now(),
					]);
					$servicesCreated++;
				}
			}
		}

		$this->command->info('📊 Collaborator ratings and services summary:');
		$this->command->info("   - Ratings assigned: {$ratingsCreated}");
		$this->command->info("   - Services created: {$servicesCreated}");
		$this->command->info('✅ Collaborator ratings and services creation completed!');
	}

	/**
	 * Create fare-unit relationships for demo team
	 */
	private function createDemoFareUnits()
	{
		$this->command->info('🔗 Creating demo fare-unit relationships...');

		// Get all units
		$minuteUnit = \App\Models\Unit::where('type', 'min')->first();
		$tenMinutesUnit = \App\Models\Unit::where('type', '10 min')->first();
		$hourUnit = \App\Models\Unit::where('type', 'h')->first();
		$wordUnit = \App\Models\Unit::where('type', 'pal')->first();
		$pageUnit = \App\Models\Unit::where('type', 'pág')->first();
		$rollUnit = \App\Models\Unit::where('type', 'rollo')->first();

		// Check if units exist
		if (!$minuteUnit || !$tenMinutesUnit || !$hourUnit || !$wordUnit || !$pageUnit || !$rollUnit) {
			$this->command->warn('Warning: Some units not found. Skipping demo fare units creation.');
			return;
		}

		$minuteId = $minuteUnit->id;
		$tenMinutesId = $tenMinutesUnit->id;
		$hourId = $hourUnit->id;
		$wordId = $wordUnit->id;
		$pageId = $pageUnit->id;
		$rollId = $rollUnit->id;

		// Get all demo fares (team 1)
		$demoFares = \App\Models\Fare::where('team_id', 1)->get();

		if ($demoFares->isEmpty()) {
			$this->command->warn('No demo fares found. Skipping fare-unit relationships.');
			return;
		}

		$created = 0;
		$skipped = 0;

		// Assign units to demo fares based on their names
		foreach ($demoFares as $fare) {
			$unitIds = [];

			// Determine units based on fare name
			if (str_contains(strtolower($fare->name), 'traducción') || str_contains(strtolower($fare->name), 'translation')) {
				if (str_contains(strtolower($fare->name), 'subtitulado') || str_contains(strtolower($fare->name), 'subtitling')) {
					$unitIds = [$minuteId];
				} elseif (str_contains(strtolower($fare->name), 'doblaje') || str_contains(strtolower($fare->name), 'dubbing')) {
					$unitIds = [$minuteId, $rollId];
				} elseif (str_contains(strtolower($fare->name), 'guion') || str_contains(strtolower($fare->name), 'script')) {
					$unitIds = [$pageId];
				} else {
					$unitIds = [$wordId];
				}
			} elseif (str_contains(strtolower($fare->name), 'revisión') || str_contains(strtolower($fare->name), 'review')) {
				$unitIds = [$wordId];
			} elseif (str_contains(strtolower($fare->name), 'transcripción') || str_contains(strtolower($fare->name), 'transcription')) {
				$unitIds = [$minuteId];
			} elseif (str_contains(strtolower($fare->name), 'localización') || str_contains(strtolower($fare->name), 'localization')) {
				$unitIds = [$wordId];
			} elseif (str_contains(strtolower($fare->name), 'audiodescripción') || str_contains(strtolower($fare->name), 'audio description')) {
				$unitIds = [$minuteId];
			} else {
				// Default to words for unknown fare types
				$unitIds = [$wordId];
			}

			// Create relationships
			foreach ($unitIds as $unitId) {
				// Check if relationship already exists
				$existingRelationship = \Illuminate\Support\Facades\DB::table('fare_unit')
					->where('fare_id', $fare->id)
					->where('unit_id', $unitId)
					->first();

				if (!$existingRelationship) {
					\Illuminate\Support\Facades\DB::table('fare_unit')->insert([
						'fare_id' => $fare->id,
						'unit_id' => $unitId,
						'created_at' => now(),
						'updated_at' => now(),
					]);
					$created++;
				} else {
					$skipped++;
				}
			}
		}

		$this->command->info("✅ Created {$created} new demo fare-unit relationships");
		if ($skipped > 0) {
			$this->command->info("⏭️ Skipped {$skipped} existing relationships");
		}
	}

	/**
	 * Create specific types of demo data
	 */
	public function createTranslationFares(): void
	{
		Fare::factory()
			->count(5)
			->translation()
			->withWordUnits()
			->create();
	}

	public function createAudiovisualFares(): void
	{
		Fare::factory()
			->count(4)
			->audiovisual()
			->withTimeUnits()
			->create();
	}

	public function createSpecializedFares(): void
	{
		Fare::factory()
			->count(3)
			->specialized()
			->withAudiovisualUnits()
			->create();
	}

	public function createCatTools(): void
	{
		Software::factory()
			->count(8)
			->catTool()
			->create();
	}

	public function createSubtitlingSoftware(): void
	{
		Software::factory()
			->count(6)
			->subtitling()
			->create();
	}

	public function createAudioSoftware(): void
	{
		Software::factory()
			->count(5)
			->audioEditing()
			->create();
	}

	public function createVideoSoftware(): void
	{
		Software::factory()
			->count(5)
			->videoEditing()
			->create();
	}

	public function createDevelopmentSoftware(): void
	{
		Software::factory()
			->count(6)
			->development()
			->create();
	}

	public function createTranslationCertifications(): void
	{
		Certification::factory()
			->count(8)
			->translation()
			->create();
	}

	public function createLanguageCertifications(): void
	{
		Certification::factory()
			->count(8)
			->languageProficiency()
			->create();
	}

	public function createAudiovisualCertifications(): void
	{
		Certification::factory()
			->count(4)
			->audiovisual()
			->create();
	}

	public function createTranslationPortfolio(): void
	{
		ContactPortfolio::factory()
			->count(15)
			->translation()
			->create();
	}

	public function createSubtitlingPortfolio(): void
	{
		ContactPortfolio::factory()
			->count(10)
			->subtitling()
			->create();
	}

	public function createVoiceOverPortfolio(): void
	{
		ContactPortfolio::factory()
			->count(8)
			->voiceOver()
			->create();
	}

	public function createLocalizationPortfolio(): void
	{
		ContactPortfolio::factory()
			->count(7)
			->localization()
			->create();
	}

	/**
	 * Create active projects
	 */
	public function createActiveProjects(): void
	{
		Project::factory()
			->count(8)
			->active()
			->create();
	}

	/**
	 * Create completed projects
	 */
	public function createCompletedProjects(): void
	{
		Project::factory()
			->count(12)
			->completed()
			->create();
	}

	/**
	 * Create pending projects
	 */
	public function createPendingProjects(): void
	{
		Project::factory()
			->count(5)
			->pending()
			->create();
	}

	/**
	 * Seed demo contacts for Team 1
	 */
	private function seedDemoContacts(): void
	{
		$this->command->info('👥 Creating demo contacts for Team 1...');

		// Example contacts for Team 1 (Demo)
		$exampleContacts = [
			[
				'team_id' => 1,
				'name' => 'Admin Example',
				'email' => 'admin@example.com',
				'profile' => 'Example admin contact for demonstration',
				'creator_id' => 1,
				'responsible_id' => 1,
				'status_id' => 5,
			],
			[
				'team_id' => 1,
				'name' => 'Demo User',
				'email' => 'demo@example.com',
				'profile' => 'Example demo contact for testing',
				'creator_id' => 1,
				'responsible_id' => 1,
				'user_id' => 8,
				'status_id' => 5,
			],
		];

		foreach ($exampleContacts as $contactData) {
			$contact = \App\Models\Contact::updateOrCreate(
				['email' => $contactData['email'], 'team_id' => $contactData['team_id']],
				$contactData
			);

			// Create sentiment history for example contacts
			if (!\App\Models\ContactSentimentHistory::where('contact_id', $contact->id)->exists()) {
				\App\Models\ContactSentimentHistory::create([
					'contact_id' => $contact->id,
					'sentiment_id' => (function () {
						$rand = rand(1, 100);
						if ($rand <= 80) {
							return \App\Models\ContactSentiment::whereIn('id', [3, 4, 5])
								->inRandomOrder()
								->first()
								->id;
						} else {
							return \App\Models\ContactSentiment::whereIn('id', [1, 2])
								->inRandomOrder()
								->first()
								->id;
						}
					})(),
					'notes' => fake()->sentence,
				]);
			}
		}

		$this->command->info('✅ Demo contacts created for Team 1');
	}
}
