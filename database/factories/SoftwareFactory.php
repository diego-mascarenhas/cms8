<?php

namespace Database\Factories;

use App\Models\Software;
use App\Models\Category;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

class SoftwareFactory extends Factory
{
	protected $model = Software::class;

	public function definition(): array
	{
		$softwareNames = [
			// CAT Tools
			'SDL Trados Studio',
			'MemoQ',
			'Wordfast Pro',
			'OmegaT',
			'Smartcat',
			'Lilt',
			'Phrase',
			'Crowdin',
			'Lokalise',
			'POEditor',

			// Subtitling Software
			'Aegisub',
			'Subtitle Edit',
			'Subtitle Workshop',
			'EZTitles',
			'Spot',
			'WinCAPS',
			'Subtitle Composer',
			'Gaupol',
			'Jubler',
			'SubRip',

			// Audio Editing
			'Adobe Audition',
			'Audacity',
			'Pro Tools',
			'Logic Pro',
			'Cubase',
			'Reaper',
			'GarageBand',
			'FL Studio',
			'Ableton Live',
			'Nuendo',

			// Video Editing
			'Adobe Premiere Pro',
			'Final Cut Pro',
			'DaVinci Resolve',
			'Avid Media Composer',
			'Sony Vegas',
			'After Effects',
			'Camtasia',
			'OpenShot',
			'Blender',
			'Lightworks',

			// Office Suite
			'Microsoft Word',
			'Microsoft Excel',
			'Microsoft PowerPoint',
			'Google Docs',
			'Google Sheets',
			'Google Slides',
			'LibreOffice Writer',
			'LibreOffice Calc',
			'LibreOffice Impress',
			'Pages',

			// PDF Editing
			'Adobe Acrobat Pro',
			'PDF-XChange Editor',
			'Foxit PDF Editor',
			'Nitro Pro',
			'PDFsam Basic',
			'Sejda PDF',
			'SmallPDF',
			'ILovePDF',
			'PDF24 Creator',
			'GIMP',

			// Design Software
			'Adobe Photoshop',
			'Adobe Illustrator',
			'Adobe InDesign',
			'Figma',
			'Sketch',
			'Canva',
			'Affinity Designer',
			'Affinity Photo',
			'CorelDRAW',
			'Inkscape',

			// Browsers
			'Google Chrome',
			'Mozilla Firefox',
			'Safari',
			'Microsoft Edge',
			'Opera',
			'Brave',
			'Vivaldi',
			'Tor Browser',
			'Chromium',
			'Waterfox',

			// Communication
			'Slack',
			'Microsoft Teams',
			'Zoom',
			'Skype',
			'Discord',
			'Telegram',
			'WhatsApp Desktop',
			'Signal',
			'Webex',
			'Google Meet',

			// Project Management
			'Trello',
			'Asana',
			'Monday.com',
			'Jira',
			'Notion',
			'ClickUp',
			'Basecamp',
			'Wrike',
			'Smartsheet',
			'TeamGantt',

			// Development
			'Visual Studio Code',
			'Sublime Text',
			'Atom',
			'IntelliJ IDEA',
			'Eclipse',
			'NetBeans',
			'Brackets',
			'Notepad++',
			'Vim',
			'Emacs',
		];

		return [
			'team_id' => 1, // Team 1 (Demo)
			'name' => $this->faker->randomElement($softwareNames),
			'category_id' => $this->getRandomCategoryId(),
		];
	}

	/**
	 * Indicate that the software is a CAT tool.
	 */
	public function catTool(): static
	{
		return $this->state(fn (array $attributes) => [
			'name' => $this->faker->randomElement([
				'SDL Trados Studio',
				'MemoQ',
				'Wordfast Pro',
				'OmegaT',
				'Smartcat',
				'Lilt',
				'Phrase',
				'Crowdin',
				'Lokalise',
				'POEditor',
			]),
			'category_id' => $this->getCategoryIdByName('CAT Tools'),
		]);
	}

	/**
	 * Indicate that the software is for subtitling.
	 */
	public function subtitling(): static
	{
		return $this->state(fn (array $attributes) => [
			'name' => $this->faker->randomElement([
				'Aegisub',
				'Subtitle Edit',
				'Subtitle Workshop',
				'EZTitles',
				'Spot',
				'WinCAPS',
				'Subtitle Composer',
				'Gaupol',
				'Jubler',
				'SubRip',
			]),
			'category_id' => $this->getCategoryIdByName('Subtitulación'),
		]);
	}

	/**
	 * Indicate that the software is for audio editing.
	 */
	public function audioEditing(): static
	{
		return $this->state(fn (array $attributes) => [
			'name' => $this->faker->randomElement([
				'Adobe Audition',
				'Audacity',
				'Pro Tools',
				'Logic Pro',
				'Cubase',
				'Reaper',
				'GarageBand',
				'FL Studio',
				'Ableton Live',
				'Nuendo',
			]),
			'category_id' => $this->getCategoryIdByName('Doblaje'),
		]);
	}

	/**
	 * Indicate that the software is for video editing.
	 */
	public function videoEditing(): static
	{
		return $this->state(fn (array $attributes) => [
			'name' => $this->faker->randomElement([
				'Adobe Premiere Pro',
				'Final Cut Pro',
				'DaVinci Resolve',
				'Avid Media Composer',
				'Sony Vegas',
				'After Effects',
				'Camtasia',
				'OpenShot',
				'Blender',
				'Lightworks',
			]),
			'category_id' => $this->getCategoryIdByName('Edición de video'),
		]);
	}

	/**
	 * Indicate that the software is for development.
	 */
	public function development(): static
	{
		return $this->state(fn (array $attributes) => [
			'name' => $this->faker->randomElement([
				'Visual Studio Code',
				'Sublime Text',
				'Atom',
				'IntelliJ IDEA',
				'Eclipse',
				'NetBeans',
				'Brackets',
				'Notepad++',
				'Vim',
				'Emacs',
			]),
			'category_id' => $this->getCategoryIdByName('Desarrollo'),
		]);
	}

	/**
	 * Get a random category ID for software.
	 */
	private function getRandomCategoryId(): ?int
	{
		$softwareModule = Module::where('key', 'softwares')->first();

		if (!$softwareModule) {
			return null;
		}

		$category = Category::where('module_id', $softwareModule->id)
			->where('team_id', 1)
			->inRandomOrder()
			->first();

		return $category ? $category->id : null;
	}

	/**
	 * Get category ID by name.
	 */
	private function getCategoryIdByName(string $name): ?int
	{
		$softwareModule = Module::where('key', 'softwares')->first();

		if (!$softwareModule) {
			return null;
		}

		$category = Category::where('module_id', $softwareModule->id)
			->where('team_id', 1)
			->where('name', $name)
			->first();

		return $category ? $category->id : null;
	}
}
