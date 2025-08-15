<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Contact;
use App\Models\Category;
use App\Models\Module;
use App\Models\Team;

class StaffContactsSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		$this->command->info('👥 Creating Staff contacts...');

		// Get the RevisionAlpha team (assuming it's team ID 1, but let's find it dynamically)
		$team = Team::where('name', 'like', '%revision%')->first() ?? Team::find(1);

		if (!$team) {
			$this->command->error('❌ No team found for Staff contacts');
			return;
		}

		$this->command->info("Using team: {$team->name} (ID: {$team->id})");

		// Get the contacts module
		$contactsModule = Module::where('key', 'contacts')->first();

		if (!$contactsModule) {
			$this->command->error('❌ Contacts module not found');
			return;
		}

		// Get or create the Staff category
		$staffCategory = Category::where('name', 'Staff')
			->where('module_id', $contactsModule->id)
			->where('team_id', $team->id)
			->first();

		if (!$staffCategory) {
			$this->command->warn('⚠️  Staff category not found, creating it...');

			// Get main contact category
			$mainContactCategory = Category::where('name', 'Contactos')
				->where('module_id', $contactsModule->id)
				->where('team_id', $team->id)
				->first();

			$staffCategory = Category::create([
				'name' => 'Staff',
				'module_id' => $contactsModule->id,
				'team_id' => $team->id,
				'parent_id' => $mainContactCategory?->id,
				'description' => 'Contactos internos del equipo',
				'status' => 1,
			]);
		}

		$this->command->info("Using Staff category: {$staffCategory->name} (ID: {$staffCategory->id})");

		// Staff contacts to create
		$staffContacts = [
			[
				'email' => 'revisionalpha@hotmail.com',
				'name' => 'Revision Alpha Hotmail',
			],
			[
				'email' => 'revisionalpha@gmail.com',
				'name' => 'Revision Alpha Gmail',
			],
			[
				'email' => 'info@revisionalpha.com',
				'name' => 'Revision Alpha Info',
			],
			[
				'email' => 'webmaster@revisionalpha.cloud',
				'name' => 'Revision Alpha Webmaster',
			],
			[
				'email' => 'administracion@revisionalpha.es',
				'name' => 'Revision Alpha Admin',
			],
		];

		$created = 0;
		$updated = 0;
		$categoryAssignments = 0;

		foreach ($staffContacts as $contactData) {
			// Check if contact exists by email
			$contact = Contact::where('email', $contactData['email'])
				->where('team_id', $team->id)
				->first();

			if ($contact) {
				// Update existing contact
				$contact->update([
					'name' => $contactData['name'],
					'status_id' => 1,
				]);
				$updated++;
				$this->command->info("🔄 Updated contact: {$contactData['email']}");
			} else {
				// Create new contact
				$contact = Contact::create([
					'team_id' => $team->id,
					'name' => $contactData['name'],
					'email' => $contactData['email'],
					'status_id' => 1,
					'creator_id' => 1, // Default to user 1
				]);
				$created++;
				$this->command->info("✅ Created contact: {$contactData['email']}");
			}

			// Assign to Staff category if not already assigned
			if (!$contact->categories()->where('category_id', $staffCategory->id)->exists()) {
				$contact->categories()->attach($staffCategory->id);
				$categoryAssignments++;
				$this->command->info("🏷️  Assigned {$contactData['email']} to Staff category");
			}
		}

		$this->command->info('📊 Staff contacts summary:');
		$this->command->info("   - Contacts created: {$created}");
		$this->command->info("   - Contacts updated: {$updated}");
		$this->command->info("   - Category assignments: {$categoryAssignments}");
		$this->command->info('✅ Staff contacts seeding completed successfully!');
	}
}
