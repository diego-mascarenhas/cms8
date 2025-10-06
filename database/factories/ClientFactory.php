<?php

namespace Database\Factories;

use App\Models\Enterprise;
use App\Models\EnterpriseStatus;
use App\Models\EnterpriseType;
use App\Models\User;
use Idoneo\HumanoBilling\Models\InvoiceType;
use Idoneo\HumanoBilling\Models\PaymentType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
	protected $model = Enterprise::class;

	public function definition(): array
	{
		$companyNames = [
			'TechCorp Solutions',
			'Global Media Group',
			'Digital Innovations Ltd',
			'Creative Studios International',
			'Multimedia Productions',
			'Language Services Pro',
			'Content Creation Hub',
			'Translation Excellence',
			'Audiovisual Masters',
			'Communication Experts',
		];

		$countries = ['ES', 'US', 'GB', 'DE', 'FR', 'IT', 'CA', 'AU', 'MX', 'BR'];
		$provinces = ['Madrid', 'Barcelona', 'Valencia', 'Sevilla', 'Bilbao', 'Málaga', 'Zaragoza', 'Murcia', 'Palma', 'Las Palmas'];

		return [
			'team_id' => 1,  // Team 1 (Demo)
			'type_id' => EnterpriseType::where('name', 'Cliente')->first()->id ?? 1,
			'name' => $this->faker->randomElement($companyNames),
			'code' => $this->faker->unique()->regexify('[A-Z]{3}[0-9]{3}'),
			'website' => $this->faker->url(),
			'phone' => $this->faker->phoneNumber(),
			'email' => $this->faker->companyEmail(),
			'whatsapp' => $this->faker->phoneNumber(),
			'referred_by' => $this->faker->optional()->name(),
			'address' => $this->faker->streetAddress(),
			'postal_code' => $this->faker->postcode(),
			'locality' => $this->faker->city(),
			'province' => $this->faker->randomElement($provinces),
			'country' => $this->faker->randomElement($countries),
			'data' => [
				'industry' => $this->faker->randomElement(['Technology', 'Media', 'Education', 'Healthcare', 'Finance', 'Entertainment', 'Marketing', 'Legal', 'Tourism', 'Manufacturing']),
				'company_size' => $this->faker->randomElement(['1-10', '11-50', '51-200', '201-1000', '1000+']),
				'annual_revenue' => $this->faker->randomElement(['< 100k', '100k-500k', '500k-1M', '1M-5M', '5M+']),
				'contact_person' => $this->faker->name(),
				'notes' => $this->faker->optional()->paragraph(),
			],
			'payment_type_id' => PaymentType::inRandomOrder()->first()->id ?? 1,
			'invoice_type_id' => InvoiceType::inRandomOrder()->first()->id ?? 1,
			'status_id' => EnterpriseStatus::inRandomOrder()->first()->id ?? 1,
			'creator_id' => User::whereHas('teams', function ($query) {
				$query->where('team_id', 1);
			})->inRandomOrder()->first()->id ?? 1,
			'responsible_id' => User::whereHas('teams', function ($query) {
				$query->where('team_id', 1);
			})->inRandomOrder()->first()->id ?? 1,
		];
	}

	/**
	 * Indicate that the enterprise is a client.
	 */
	public function client(): static
	{
		return $this->state(fn(array $attributes) => [
			'type_id' => EnterpriseType::where('name', 'Cliente')->first()->id ?? 1,
		]);
	}

	/**
	 * Indicate that the enterprise is a supplier.
	 */
	public function supplier(): static
	{
		return $this->state(fn(array $attributes) => [
			'type_id' => EnterpriseType::where('name', 'Proveedor')->first()->id ?? 2,
		]);
	}
}
