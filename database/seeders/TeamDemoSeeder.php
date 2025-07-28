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
