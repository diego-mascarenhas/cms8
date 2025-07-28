<?php

namespace Database\Factories;

use App\Models\Fare;
use App\Models\FareType;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class FareFactory extends Factory
{
	protected $model = Fare::class;

	public function definition(): array
	{
		$fareNames = [
			'Traducción General',
			'Traducción Técnica',
			'Traducción Jurídica',
			'Traducción Médica',
			'Subtitulado Estándar',
			'Subtitulado Accesible',
			'Doblaje Comercial',
			'Doblaje Documental',
			'Locución Profesional',
			'Transcripción Audio',
			'Revisión de Textos',
			'Traducción Web',
			'Localización Software',
			'Traducción Marketing',
			'Traducción Financiera',
		];

		return [
			'team_id' => 1, // Team 1 (Demo)
			'name' => $this->faker->randomElement($fareNames),
			'type_id' => FareType::inRandomOrder()->first()->id ?? 1,
			'glosary_id' => null, // Optional field
		];
	}

	/**
	 * Indicate that the fare is for translation services.
	 */
	public function translation(): static
	{
		return $this->state(fn (array $attributes) => [
			'name' => $this->faker->randomElement([
				'Traducción General',
				'Traducción Técnica',
				'Traducción Jurídica',
				'Traducción Médica',
				'Traducción Web',
				'Traducción Marketing',
				'Traducción Financiera',
			]),
		]);
	}

	/**
	 * Indicate that the fare is for audiovisual services.
	 */
	public function audiovisual(): static
	{
		return $this->state(fn (array $attributes) => [
			'name' => $this->faker->randomElement([
				'Subtitulado Estándar',
				'Subtitulado Accesible',
				'Doblaje Comercial',
				'Doblaje Documental',
				'Locución Profesional',
			]),
		]);
	}

	/**
	 * Indicate that the fare is for specialized services.
	 */
	public function specialized(): static
	{
		return $this->state(fn (array $attributes) => [
			'name' => $this->faker->randomElement([
				'Transcripción Audio',
				'Revisión de Textos',
				'Localización Software',
			]),
		]);
	}

	/**
	 * Configure the fare with specific units after creation.
	 */
	public function withUnits(): static
	{
		return $this->afterCreating(function (Fare $fare) {
			// Get random units (1-3 units per fare)
			$units = Unit::inRandomOrder()->take($this->faker->numberBetween(1, 3))->get();

			if ($units->isNotEmpty()) {
				$fare->units()->attach($units->pluck('id')->toArray());
			}
		});
	}

	/**
	 * Configure the fare with word-based units.
	 */
	public function withWordUnits(): static
	{
		return $this->afterCreating(function (Fare $fare) {
			$wordUnits = Unit::whereIn('type', ['pal', 'pág'])->get();

			if ($wordUnits->isNotEmpty()) {
				$fare->units()->attach($wordUnits->pluck('id')->toArray());
			}
		});
	}

	/**
	 * Configure the fare with time-based units.
	 */
	public function withTimeUnits(): static
	{
		return $this->afterCreating(function (Fare $fare) {
			$timeUnits = Unit::whereIn('type', ['min', '10 min', 'h'])->get();

			if ($timeUnits->isNotEmpty()) {
				$fare->units()->attach($timeUnits->pluck('id')->toArray());
			}
		});
	}

	/**
	 * Configure the fare with audiovisual units.
	 */
	public function withAudiovisualUnits(): static
	{
		return $this->afterCreating(function (Fare $fare) {
			$avUnits = Unit::whereIn('type', ['min', 'rollo', 'Total'])->get();

			if ($avUnits->isNotEmpty()) {
				$fare->units()->attach($avUnits->pluck('id')->toArray());
			}
		});
	}
}
