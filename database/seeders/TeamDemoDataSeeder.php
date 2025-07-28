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

class TeamDemoDataSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		$this->command->info('🌱 Seeding Team Demo data...');

		// Check if Team 1 exists
		$team1 = \App\Models\Team::find(1);
		if (!$team1) {
			$this->command->error('❌ Team 1 not found. Please run the main DatabaseSeeder first.');
			return;
		}

		// Check if we have collaborators
		$collaborators = Contact::where('team_id', 1)->count();
		if ($collaborators === 0) {
			$this->command->warn('⚠️  No collaborators found in Team 1. Creating some basic collaborators first...');
			$this->createBasicCollaborators();
		}

		// Create demo clients (enterprises)
		$this->command->info('📊 Creating demo clients...');
		ClientFactory::new()
			->count(10)
			->client()
			->create();

		// Create demo projects
		$this->command->info('📋 Creating demo projects...');
		Project::factory()
			->count(15)
			->withFares()
			->create();

		// Create demo fares with units
		$this->command->info('💰 Creating demo fares with units...');
		Fare::factory()
			->count(8)
			->withUnits()
			->create();

		// Create demo software
		$this->command->info('💻 Creating demo software...');
		Software::factory()
			->count(20)
			->create();

		// Create demo certifications
		$this->command->info('🏆 Creating demo certifications...');
		Certification::factory()
			->count(15)
			->create();

		// Create demo experience/portfolio items
		$this->command->info('📚 Creating demo experience/portfolio items...');
		ContactPortfolio::factory()
			->count(25)
			->create();

		$this->command->info('✅ Team Demo data seeded successfully!');
		$this->command->info('📈 Created:');
		$this->command->info('   - 10 demo clients');
		$this->command->info('   - 15 demo projects');
		$this->command->info('   - 8 demo fares with units');
		$this->command->info('   - 20 demo software entries');
		$this->command->info('   - 15 demo certifications');
		$this->command->info('   - 25 demo experience/portfolio items');
	}

	/**
	 * Create basic collaborators if none exist
	 */
	private function createBasicCollaborators(): void
	{
		$collaborators = [
			[
				'name' => 'María',
				'surname' => 'García',
				'email' => 'maria.garcia@demo.com',
				'phone' => '+34600123456',
				'country' => 'ES',
				'language' => 'es',
				'profile' => 'Traductora profesional con especialización en traducción técnica y médica.',
			],
			[
				'name' => 'Carlos',
				'surname' => 'Rodríguez',
				'email' => 'carlos.rodriguez@demo.com',
				'phone' => '+34600123457',
				'country' => 'ES',
				'language' => 'es',
				'profile' => 'Subtitulador y traductor audiovisual con experiencia en plataformas de streaming.',
			],
			[
				'name' => 'Ana',
				'surname' => 'Martínez',
				'email' => 'ana.martinez@demo.com',
				'phone' => '+34600123458',
				'country' => 'ES',
				'language' => 'es',
				'profile' => 'Locutora profesional y traductora con especialización en doblaje y voice-over.',
			],
			[
				'name' => 'Luis',
				'surname' => 'Fernández',
				'email' => 'luis.fernandez@demo.com',
				'phone' => '+34600123459',
				'country' => 'ES',
				'language' => 'es',
				'profile' => 'Traductor jurídico y financiero con amplia experiencia en documentación legal.',
			],
			[
				'name' => 'Elena',
				'surname' => 'López',
				'email' => 'elena.lopez@demo.com',
				'phone' => '+34600123460',
				'country' => 'ES',
				'language' => 'es',
				'profile' => 'Especialista en localización de software y traducción técnica.',
			],
		];

		foreach ($collaborators as $collaborator) {
			Contact::create([
				'team_id' => 1,
				'name' => $collaborator['name'],
				'surname' => $collaborator['surname'],
				'email' => $collaborator['email'],
				'phone' => $collaborator['phone'],
				'country' => $collaborator['country'],
				'language' => $collaborator['language'],
				'profile' => $collaborator['profile'],
				'status_id' => 1, // Active
				'valoration_id' => 1, // Default valuation
			]);
		}

		$this->command->info('👥 Created 5 basic collaborators for Team Demo');
	}
}
