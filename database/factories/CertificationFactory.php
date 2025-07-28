<?php

namespace Database\Factories;

use App\Models\Certification;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificationFactory extends Factory
{
	protected $model = Certification::class;

	public function definition(): array
	{
		$certifications = [
			// Translation certifications
			['certification' => 'ATA Certification', 'language' => 'en'],
			['certification' => 'CIOL Diploma in Translation', 'language' => 'en'],
			['certification' => 'ISO 17100:2015', 'language' => 'en'],
			['certification' => 'ProZ Certified PRO', 'language' => 'en'],
			['certification' => 'SDL Trados Certification', 'language' => 'en'],
			['certification' => 'MemoQ Certification', 'language' => 'en'],
			['certification' => 'Wordfast Certification', 'language' => 'en'],
			['certification' => 'OmegaT Certification', 'language' => 'en'],

			// Spanish certifications
			['certification' => 'DELE C2', 'language' => 'es'],
			['certification' => 'SIELE Global', 'language' => 'es'],
			['certification' => 'Traductor Jurado', 'language' => 'es'],
			['certification' => 'Certificado de Traducción', 'language' => 'es'],

			// English certifications
			['certification' => 'TOEFL iBT', 'language' => 'en'],
			['certification' => 'IELTS Academic', 'language' => 'en'],
			['certification' => 'Cambridge C2 Proficiency', 'language' => 'en'],
			['certification' => 'TOEIC', 'language' => 'en'],
			['certification' => 'Cambridge C1 Advanced', 'language' => 'en'],
			['certification' => 'Cambridge B2 First', 'language' => 'en'],

			// French certifications
			['certification' => 'DELF B2', 'language' => 'fr'],
			['certification' => 'DALF C1', 'language' => 'fr'],
			['certification' => 'TCF', 'language' => 'fr'],
			['certification' => 'DELF A2', 'language' => 'fr'],
			['certification' => 'DELF B1', 'language' => 'fr'],

			// German certifications
			['certification' => 'Goethe-Zertifikat C1', 'language' => 'de'],
			['certification' => 'TestDaF', 'language' => 'de'],
			['certification' => 'Goethe-Zertifikat B2', 'language' => 'de'],
			['certification' => 'Goethe-Zertifikat B1', 'language' => 'de'],

			// Italian certifications
			['certification' => 'CILS C2', 'language' => 'it'],
			['certification' => 'CELI C2', 'language' => 'it'],
			['certification' => 'PLIDA C2', 'language' => 'it'],
			['certification' => 'CILS B2', 'language' => 'it'],

			// Portuguese certifications
			['certification' => 'CAPLE C2', 'language' => 'pt'],
			['certification' => 'CELPE-Bras', 'language' => 'pt'],
			['certification' => 'CAPLE B2', 'language' => 'pt'],

			// Other language certifications
			['certification' => 'JLPT N1', 'language' => 'ja'],
			['certification' => 'HSK 6', 'language' => 'zh'],
			['certification' => 'TOPIK 6', 'language' => 'ko'],
			['certification' => 'JLPT N2', 'language' => 'ja'],
			['certification' => 'HSK 5', 'language' => 'zh'],

			// Professional certifications
			['certification' => 'Certified Professional Translator', 'language' => 'en'],
			['certification' => 'Certified Interpreter', 'language' => 'en'],
			['certification' => 'Certified Localization Professional', 'language' => 'en'],
			['certification' => 'Certified Technical Translator', 'language' => 'en'],
			['certification' => 'Certified Medical Translator', 'language' => 'en'],
			['certification' => 'Certified Legal Translator', 'language' => 'en'],

			// Audiovisual certifications
			['certification' => 'ATRAE Subtitling Certification', 'language' => 'es'],
			['certification' => 'EZTitles Certification', 'language' => 'en'],
			['certification' => 'Subtitle Workshop Certification', 'language' => 'en'],
			['certification' => 'Aegisub Certification', 'language' => 'en'],
			['certification' => 'Professional Subtitler', 'language' => 'en'],
			['certification' => 'Professional Voice-over Artist', 'language' => 'en'],
		];

		$randomCert = $this->faker->randomElement($certifications);

		return [
			'team_id' => 1, // Team 1 (Demo)
			'certification' => $randomCert['certification'],
			'language' => $randomCert['language'],
		];
	}

	/**
	 * Indicate that the certification is for translation.
	 */
	public function translation(): static
	{
		return $this->state(fn (array $attributes) => [
			'certification' => $this->faker->randomElement([
				'ATA Certification',
				'CIOL Diploma in Translation',
				'ISO 17100:2015',
				'ProZ Certified PRO',
				'SDL Trados Certification',
				'MemoQ Certification',
				'Wordfast Certification',
				'OmegaT Certification',
				'Certified Professional Translator',
				'Certified Technical Translator',
				'Certified Medical Translator',
				'Certified Legal Translator',
			]),
		]);
	}

	/**
	 * Indicate that the certification is for language proficiency.
	 */
	public function languageProficiency(): static
	{
		return $this->state(fn (array $attributes) => [
			'certification' => $this->faker->randomElement([
				'DELE C2',
				'SIELE Global',
				'TOEFL iBT',
				'IELTS Academic',
				'Cambridge C2 Proficiency',
				'TOEIC',
				'DELF B2',
				'DALF C1',
				'Goethe-Zertifikat C1',
				'TestDaF',
				'JLPT N1',
				'HSK 6',
			]),
		]);
	}

	/**
	 * Indicate that the certification is for audiovisual work.
	 */
	public function audiovisual(): static
	{
		return $this->state(fn (array $attributes) => [
			'certification' => $this->faker->randomElement([
				'ATRAE Subtitling Certification',
				'EZTitles Certification',
				'Subtitle Workshop Certification',
				'Aegisub Certification',
				'Professional Subtitler',
				'Professional Voice-over Artist',
			]),
		]);
	}

	/**
	 * Indicate that the certification is for Spanish.
	 */
	public function spanish(): static
	{
		return $this->state(fn (array $attributes) => [
			'certification' => $this->faker->randomElement([
				'DELE C2',
				'SIELE Global',
				'Traductor Jurado',
				'Certificado de Traducción',
			]),
			'language' => 'es',
		]);
	}

	/**
	 * Indicate that the certification is for English.
	 */
	public function english(): static
	{
		return $this->state(fn (array $attributes) => [
			'certification' => $this->faker->randomElement([
				'ATA Certification',
				'CIOL Diploma in Translation',
				'TOEFL iBT',
				'IELTS Academic',
				'Cambridge C2 Proficiency',
				'TOEIC',
			]),
			'language' => 'en',
		]);
	}

	/**
	 * Indicate that the certification is for French.
	 */
	public function french(): static
	{
		return $this->state(fn (array $attributes) => [
			'certification' => $this->faker->randomElement([
				'DELF B2',
				'DALF C1',
				'TCF',
				'DELF A2',
				'DELF B1',
			]),
			'language' => 'fr',
		]);
	}

	/**
	 * Indicate that the certification is for German.
	 */
	public function german(): static
	{
		return $this->state(fn (array $attributes) => [
			'certification' => $this->faker->randomElement([
				'Goethe-Zertifikat C1',
				'TestDaF',
				'Goethe-Zertifikat B2',
				'Goethe-Zertifikat B1',
			]),
			'language' => 'de',
		]);
	}
}
