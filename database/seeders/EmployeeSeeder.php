<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\ContactAbsence;
use App\Models\ContactWeeklyAvailability;
use App\Models\User;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
	public function run()
	{
		$this->command->info('👥 Seeding employees...');

		// Check if we have users to assign as creators/responsible
		$users = User::all();
		if ($users->isEmpty()) {
			$this->command->warn('⚠️  No users found. Creating a basic admin user first...');
			$adminUser = User::factory()->create([
				'name' => 'Admin User',
				'email' => 'admin@example.com',
			]);
			$adminUser->assignRole('admin');
			$users = User::all();
		}

		// Create employees using the factory
		$this->command->info('📊 Creating employees...');

		// Create some active employees
		EmployeeFactory::new()
			->count(8)
			->active()
			->create();

		// Create some inactive employees
		EmployeeFactory::new()
			->count(2)
			->inactive()
			->create();

		// Create some employees with specific commands
		EmployeeFactory::new()
			->count(3)
			->command('Desarrollo Web')
			->active()
			->create();

		EmployeeFactory::new()
			->count(2)
			->command('Marketing Digital')
			->active()
			->create();

		// Create some employees with specific contract types
		EmployeeFactory::new()
			->count(2)
			->contractType('Indefinido')
			->active()
			->create();

		EmployeeFactory::new()
			->count(1)
			->contractType('Prácticas')
			->active()
			->create();

		// Create some employees from specific cities
		EmployeeFactory::new()
			->count(2)
			->city('Madrid', 'Madrid')
			->active()
			->create();

		EmployeeFactory::new()
			->count(2)
			->city('Barcelona', 'Barcelona')
			->active()
			->create();

		// Create some random absences for employees
		$this->command->info('📅 Creating employee absences...');
		$employees = Contact::whereHas('user.roles', function ($query) {
			$query->where('name', 'employee');
		})->get();

		foreach ($employees as $employee) {
			// Create some random absences
			$absenceCount = rand(0, 5);
			$usedDates = [];

			for ($i = 0; $i < $absenceCount; $i++) {
				$date = now()->subDays(rand(1, 365))->format('Y-m-d');

				// Avoid duplicate dates for the same employee
				while (in_array($date, $usedDates)) {
					$date = now()->subDays(rand(1, 365))->format('Y-m-d');
				}
				$usedDates[] = $date;

				ContactAbsence::create([
					'team_id' => $employee->team_id,
					'contact_id' => $employee->id,
					'absence_date' => $date,
					'reason' => $this->getRandomAbsenceReason(),
				]);
			}
		}

		$totalEmployees = Contact::whereHas('user.roles', function ($query) {
			$query->where('name', 'employee');
		})->count();

		$activeEmployees = Contact::whereHas('user.roles', function ($query) {
			$query->where('name', 'employee');
		})->where('data->active', true)->count();

		$this->command->info('✅ Employees seeded successfully!');
		$this->command->info("📈 Created: {$totalEmployees} employees ({$activeEmployees} active)");
	}

	/**
	 * Get a random absence reason
	 */
	private function getRandomAbsenceReason()
	{
		$reasons = [
			'Vacaciones',
			'Enfermedad',
			'Permiso personal',
			'Formación',
			'Reunión médica',
			'Asuntos personales',
			'Teletrabajo',
			'Viaje de trabajo',
		];

		return $reasons[array_rand($reasons)];
	}
}
