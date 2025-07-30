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

		// Create demo projects
		$this->command->info('Creating demo projects...');
		Project::factory()
			->count(25)
			->withFares()
			->create();

		// Create demo fares with units
		$this->command->info('Creating demo fares with units...');
		Fare::factory()
			->count(12)
			->withUnits()
			->create();

		// Create fare-unit relationships for demo team
		$this->createDemoFareUnits();

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
}
