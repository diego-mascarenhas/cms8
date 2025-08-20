<?php

namespace App\Console\Commands;

use App\Models\Contact;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ImportAndMoveFiles extends Command
{
	protected $signature = 'files:import-and-move {--team-id=4 : Team ID} {--cv-path=scripts/cv : Path to CV files} {--locucion-path=scripts/locucion : Path to locución files} {--dry-run : Show what would be done without actually doing it}';

	protected $description = 'Import files to database and move them to correct locations';

	public function handle()
	{
		$teamId = $this->option('team-id');
		$cvPath = $this->option('cv-path');
		$locucionPath = $this->option('locucion-path');
		$dryRun = $this->option('dry-run');

		if ($dryRun)
		{
			$this->warn('DRY RUN MODE - No files will be moved or imported');
		}

		$this->info("🚀 Processing files for Team {$teamId}");

		// Get collaborators
		$collaborators = Contact::where('team_id', $teamId)->get();
		$this->info("Found {$collaborators->count()} collaborators");

		$processed = 0;
		$errors = [];

		// Process CV files
		$this->info("\n📄 Processing CV files...");
		$cvFiles = $this->getFilesFromDirectory($cvPath);

		foreach ($cvFiles as $cvFile)
		{
			$result = $this->processFile($cvFile, $collaborators, 'documents', 'CV', $dryRun);
			if ($result['success'])
			{
				$processed++;
				$this->info("✅ {$result['message']}");
			} else
			{
				$errors[] = $result['error'];
				$this->error("❌ {$result['error']}");
			}
		}

		// Process locución files
		$this->info("\n🎤 Processing locución files...");
		$locucionFiles = $this->getLocucionFiles($locucionPath);

		foreach ($locucionFiles as $locucionFile)
		{
			$result = $this->processFile($locucionFile, $collaborators, 'media', 'Locución', $dryRun);
			if ($result['success'])
			{
				$processed++;
				$this->info("✅ {$result['message']}");
			} else
			{
				$errors[] = $result['error'];
				$this->error("❌ {$result['error']}");
			}
		}

		// Summary
		$this->info("\n📊 Summary:");
		$this->info("✅ Processed: {$processed} files");
		$this->info('❌ Errors: '.count($errors).' files');

		if (! empty($errors))
		{
			$this->warn("\nErrors:");
			foreach (array_slice($errors, 0, 10) as $error)
			{
				$this->line("  - {$error}");
			}
			if (count($errors) > 10)
			{
				$this->line('  ... and '.(count($errors) - 10).' more');
			}
		}
	}

	private function getFilesFromDirectory($path)
	{
		if (! is_dir($path))
		{
			return [];
		}

		$files = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
		);

		foreach ($iterator as $file)
		{
			if ($file->isFile())
			{
				$files[] = $file->getPathname();
			}
		}

		return $files;
	}

	private function getLocucionFiles($path)
	{
		$files = [];

		if (! is_dir($path))
		{
			return $files;
		}

		// Recursively find all files in locución directory
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
		);

		foreach ($iterator as $file)
		{
			if ($file->isFile())
			{
				$files[] = $file->getPathname();
			}
		}

		return $files;
	}

	private function processFile($filepath, $collaborators, $collection, $type, $dryRun)
	{
		try
		{
			$filename = basename($filepath);
			$originalName = pathinfo($filename, PATHINFO_FILENAME);

			// Normalize name
			$normalizedName = $this->normalizeName($originalName);

			// Find collaborator
			$collaborator = $this->findCollaborator($collaborators, $normalizedName);

			if (! $collaborator)
			{
				return [
					'success' => false,
					'error' => "Collaborator not found for: {$originalName} (normalized: {$normalizedName})",
				];
			}

			if ($dryRun)
			{
				return [
					'success' => true,
					'message' => "Would process: {$filename} → {$collaborator->name} {$collaborator->surname}",
				];
			}

			// Check if media already exists
			$existingMedia = Media::where('model_type', Contact::class)
				->where('model_id', $collaborator->id)
				->where('collection_name', $collection)
				->where('name', $filename)
				->first();

			if ($existingMedia)
			{
				return [
					'success' => false,
					'error' => "File already exists: {$filename} for {$collaborator->name} {$collaborator->surname}",
				];
			}

			// Add to media collection (this will move the file to correct location)
			$media = $collaborator->addMedia($filepath)
				->toMediaCollection($collection, 'public');

			return [
				'success' => true,
				'message' => "Imported: {$filename} → {$collaborator->name} {$collaborator->surname}",
			];
		} catch (\Exception $e)
		{
			return [
				'success' => false,
				'error' => "Error processing {$filename}: ".$e->getMessage(),
			];
		}
	}

	private function normalizeName($name)
	{
		// Remove prefixes
		$name = preg_replace('/^(Sr\.|Sra\.|Dr\.|Dra\.|Mr\.|Mrs\.|Ms\.)\s*/i', '', $name);

		// Normalize spaces
		$name = preg_replace('/\s+/', ' ', trim($name));

		// Normalize accents
		$replacements = [
			'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
			'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
			'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
			'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
			'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
			'Â' => 'A', 'Ê' => 'E', 'Î' => 'I', 'Ô' => 'O', 'Û' => 'U',
			'ã' => 'a', 'õ' => 'o', 'Ã' => 'A', 'Õ' => 'O',
			'ç' => 'c', 'Ç' => 'C',
		];

		return strtr($name, $replacements);
	}

	private function findCollaborator($collaborators, $name)
	{
		// First try exact match
		$collaborator = $collaborators->first(function ($c) use ($name)
		{
			$fullName = strtolower($c->name.' '.$c->surname);
			$searchName = strtolower($name);

			return $fullName === $searchName;
		});

		if ($collaborator)
		{
			return $collaborator;
		}

		// Try partial matches
		$collaborator = $collaborators->first(function ($c) use ($name)
		{
			$fullName = strtolower($c->name.' '.$c->surname);
			$searchName = strtolower($name);

			return strpos($fullName, $searchName) !== false || strpos($searchName, $fullName) !== false;
		});

		return $collaborator;
	}
}
