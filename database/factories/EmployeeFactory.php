<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
	protected $model = Contact::class;

	public function definition()
	{
		$users = User::all();

		// Spanish cities and provinces for employees
		$cities = [
			'Madrid' => 'Madrid',
			'Barcelona' => 'Barcelona',
			'Valencia' => 'Valencia',
			'Sevilla' => 'Sevilla',
			'Zaragoza' => 'Zaragoza',
			'Málaga' => 'Málaga',
			'Murcia' => 'Murcia',
			'Palma' => 'Baleares',
			'Las Palmas' => 'Las Palmas',
			'Bilbao' => 'Vizcaya',
			'Alicante' => 'Alicante',
			'Córdoba' => 'Córdoba',
			'Valladolid' => 'Valladolid',
			'Vigo' => 'Pontevedra',
			'Gijón' => 'Asturias',
		];

		$city = $this->faker->randomElement(array_keys($cities));
		$province = $cities[$city];

		// Employee-specific data
		$employeeData = [
			'city' => $city,
			'province' => $province,
			'command' => $this->faker->randomElement([
				'Desarrollo Web',
				'Diseño Gráfico',
				'Marketing Digital',
				'Atención al Cliente',
				'Administración',
				'Recursos Humanos',
				'Ventas',
				'Logística',
				'Calidad',
				'Investigación y Desarrollo',
			]),
			'active' => $this->faker->boolean(85), // 85% chance of being active
			'dni' => $this->faker->unique()->numerify('########'),
			'nationality' => $this->faker->randomElement(['Española', 'Argentina', 'Colombiana', 'Mexicana', 'Venezolana', 'Peruana', 'Chilena', 'Ecuatoriana']),
			'naf' => $this->faker->unique()->numerify('########'),
			'address' => $this->faker->streetAddress(),
			'postal_code' => $this->faker->postcode(),
			'contract_type' => $this->faker->randomElement([
				'Indefinido',
				'Temporal',
				'Prácticas',
				'Formación',
				'Autónomo',
				'Por Obra',
			]),
			'account_number' => $this->faker->unique()->numerify('ES####################'),
		];

		return [
			'team_id' => 1,
			'name' => $this->faker->firstName(),
			'surname' => $this->faker->lastName(),
			'email' => $this->faker->unique()->safeEmail,
			'phone' => $this->faker->numberBetween(600000000, 699999999),
			'language' => 'es', // Employees are primarily Spanish speakers
			'country' => 724, // Spain
			'creator_id' => $users->random()->id,
			'responsible_id' => $users->random()->id,
			'status_id' => ContactStatus::inRandomOrder()->first()->id,
			'birthday' => $this->faker->dateTimeBetween('-50 years', '-18 years'),
			'profile' => $this->generateEmployeeProfile(),
			'data' => $employeeData,
		];
	}

	public function configure()
	{
		return $this->afterCreating(function (Contact $contact) {
			// Create a user with employee role for this contact
			$user = User::factory()->create([
				'name' => $contact->name . ' ' . $contact->surname,
				'email' => $contact->email,
				'phone' => $contact->phone,
			]);

			// Assign employee role
			$user->assignRole('employee');

			// Link the contact to the user
			$contact->update(['user_id' => $user->id]);

			// Create weekly availability for the employee
			$contact->weeklyAvailability()->create([
				'team_id' => $contact->team_id,
				'monday' => $this->faker->boolean(90),
				'tuesday' => $this->faker->boolean(90),
				'wednesday' => $this->faker->boolean(90),
				'thursday' => $this->faker->boolean(90),
				'friday' => $this->faker->boolean(90),
				'saturday' => $this->faker->boolean(20),
				'sunday' => $this->faker->boolean(10),
			]);
		});
	}

	/**
	 * Generate a realistic employee profile
	 */
	private function generateEmployeeProfile()
	{
		$profiles = [
			'Desarrollador web con experiencia en Laravel, Vue.js y bases de datos MySQL. Especializado en aplicaciones empresariales y e-commerce.',
			'Diseñador gráfico creativo con amplia experiencia en branding corporativo, diseño web y material promocional para empresas.',
			'Especialista en marketing digital con conocimientos en SEO, SEM, redes sociales y análisis de datos para optimización de campañas.',
			'Profesional de atención al cliente con experiencia en gestión de incidencias, soporte técnico y mejora de la experiencia del usuario.',
			'Administrativo con sólidos conocimientos en gestión documental, facturación, contabilidad básica y organización empresarial.',
			'Responsable de recursos humanos con experiencia en selección de personal, gestión de nóminas y desarrollo organizacional.',
			'Comercial con amplia experiencia en ventas B2B, gestión de clientes y desarrollo de estrategias comerciales.',
			'Logístico especializado en gestión de almacenes, planificación de rutas y optimización de procesos de distribución.',
			'Responsable de calidad con experiencia en implementación de sistemas de gestión, auditorías y mejora continua.',
			'Investigador con experiencia en desarrollo de nuevos productos, análisis de mercado y proyectos de innovación.',
		];

		return $this->faker->randomElement($profiles);
	}

	/**
	 * Indicate that the employee is active
	 */
	public function active(): static
	{
		return $this->state(fn (array $attributes) => [
			'data' => array_merge($attributes['data'] ?? [], ['active' => true]),
		]);
	}

	/**
	 * Indicate that the employee is inactive
	 */
	public function inactive(): static
	{
		return $this->state(fn (array $attributes) => [
			'data' => array_merge($attributes['data'] ?? [], ['active' => false]),
		]);
	}

	/**
	 * Indicate that the employee has a specific command/position
	 */
	public function command(string $command): static
	{
		return $this->state(fn (array $attributes) => [
			'data' => array_merge($attributes['data'] ?? [], ['command' => $command]),
		]);
	}

	/**
	 * Indicate that the employee has a specific contract type
	 */
	public function contractType(string $contractType): static
	{
		return $this->state(fn (array $attributes) => [
			'data' => array_merge($attributes['data'] ?? [], ['contract_type' => $contractType]),
		]);
	}

	/**
	 * Indicate that the employee is from a specific city
	 */
	public function city(string $city, string $province): static
	{
		return $this->state(fn (array $attributes) => [
			'data' => array_merge($attributes['data'] ?? [], [
				'city' => $city,
				'province' => $province,
			]),
		]);
	}
}
