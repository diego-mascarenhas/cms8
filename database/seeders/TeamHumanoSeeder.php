<?php

namespace Database\Seeders;

use App\Models\Enterprise;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TeamHumanoSeeder extends Seeder
{
	private $teamId = 3;  // Humano Team ID

	public function run()
	{
		$this->command->info('🚀 Setting up Humano Data...');

		// 1. Create Humano Team
		$team = $this->createHumanoTeam();

		// 2. Create Humano users
		$this->createHumanoUsers($team);

		// 3. Create Humano enterprise
		//  $this->createHumanoEnterprise($team);

		// 4. Create Humano contacts
		// $this->createHumanoContacts($team);

		// 5. Create Humano categories
		// $this->createHumanoCategories();

		$this->command->info('✅ Humano setup completed successfully');
	}

	/**
	 * Create Humano Team
	 */
	private function createHumanoTeam()
	{
		$humanoOwner = User::where('email', 'victor@machbel.com')->first();

		// Create Humano owner if not exists
		if (!$humanoOwner) {
			$humanoOwner = User::create([
				'name' => 'Victor Machbel',
				'email' => 'victor@machbel.com',
				'password' => Hash::make('Simplicity!'),
			]);
			$humanoOwner->assignRole('admin');
			$this->command->info('✅ Created Humano owner user: victor@machbel.com');
		}

		// Use Jetstream's proper method to create team
		$team = $humanoOwner->ownedTeams()->firstOrCreate(
			['name' => "Humano's Team"],
			[
				'name' => "Humano's Team",
				'personal_team' => false,
			]
		);

		// Ensure known password for owner
		$humanoOwner->update(['password' => Hash::make('Simplicity!')]);

		// Ensure the user is in the team
		if (!$team->users()->where('user_id', $humanoOwner->id)->exists()) {
			$team->users()->attach($humanoOwner->id, ['role' => 'admin']);
		}

		$this->command->info("✅ Created Humano Team (ID: {$team->id})");

		return $team;
	}

	/**
	 * Create Humano users
	 */
	private function createHumanoUsers($team)
	{
		$this->command->info('👥 Setting up Humano users...');

		$humanoOwner = User::where('email', 'victor@machbel.com')->first();

		// Update current team for main user (already created in createHumanoTeam)
		$humanoOwner->update([
			'current_team_id' => $team->id,
		]);

		// Add revision alpha user to humano team as well (create if missing)
		$revision = User::where('email', 'diego.mascarenhas@icloud.com')->first();
		if (!$revision) {
			$revision = User::create([
				'name' => 'Diego Mascarenhas',
				'email' => 'diego.mascarenhas@icloud.com',
				'password' => Hash::make('Simplicity!'),
			]);
			$revision->assignRole('admin');
			$this->command->info('✅ Created user: diego.mascarenhas@icloud.com');
		}

		// Always set known password for Diego
		$revision->update(['password' => Hash::make('Simplicity!')]);

		// Ensure user is in team and set as current team
		if (!$revision->teams()->where('team_id', $team->id)->exists()) {
			$revision->teams()->attach($team->id, ['role' => 'admin']);
		}
		$revision->update(['current_team_id' => $team->id]);
		$this->command->info('✅ Added Diego Mascarenhas to Humano team');

		$this->command->info('✅ Updated Humano team users');
	}
}
