<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Certification;
use App\Models\Contact;
use App\Models\CustomTranslation;
use App\Models\ContactLanguageVariant;
use App\Models\Enterprise;
use App\Models\Fare;
use App\Models\FareType;
use App\Models\Language;
use App\Models\LanguageVariant;
use App\Models\Module;
use App\Models\Software;
use App\Models\SoftwareType;
use App\Models\Stylebook;
use App\Models\Team;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TeamBboSeeder extends Seeder
{
	private $teamId = 4;  // BBO Team ID

	public function run()
	{
		$this->command->info('🚀 MODIFIED: Setting up BBO Client Data from JSON...');
		$this->command->info('🔧 DEBUG: BboSeeder run() method started');

		// 0. Ensure languages and language variants are available
		$this->ensureLanguagesAvailable();

		// 1. Create BBO Team
		$team = $this->createBboTeam();

		// 2. Create BBO users
		$this->createBboUsers($team);

		// 3. Create BBO enterprise
		$this->createBboEnterprise($team);

		// 4. Create BBO categories
		$this->createBboCategories();

		// 4.5. Create BBO fare types
		$this->createBboFareTypes();

		// 4.6. Create BBO fares
		$this->createBboFares();

		// 4.7. Create BBO fare units relationships
		$this->createBboFareUnits();

		// 4.8. Create BBO software
		$this->createBboSoftware();

		// 4.9. Create BBO certifications
		$this->createBboCertifications();

		// 4.10. Create BBO stylebooks
		$this->createBboStylebooks();

		// 4.11. Create BBO valorations (must be before importing collaborators)
		$this->createBboValorations($team);

		// 4.12. Create BBO custom translations
		$this->createBboCustomTranslations($team);

		// 4.13. Create BBO topics (all from TopicsSeeder)
		$this->createBboTopics();

		// 2. Seed BBO language variants (pasa el team_id real)
		$this->seedBboLanguageVariants($team->id);

		// 5. Import BBO collaborators from JSON
		try {
			$this->command->info('🔧 DEBUG: Starting step 5 - importBboCollaboratorsFromJson');
			$this->importBboCollaboratorsFromJson($team);
			$this->command->info('🔧 DEBUG: Completed step 5 successfully');
		} catch (\Exception $e) {
			$this->command->error('Error in step 5: ' . $e->getMessage());
			Log::error('BBO Seeder Step 5 Error: ' . $e->getMessage());
			$this->command->error('Stack trace: ' . $e->getTraceAsString());
		} catch (\Throwable $e) {
			$this->command->error('Fatal error in step 5: ' . $e->getMessage());
			Log::error('BBO Seeder Step 5 Fatal Error: ' . $e->getMessage());
			$this->command->error('Stack trace: ' . $e->getTraceAsString());
		}

		// 6. Import BBO clients from CSV
		try {
			$this->command->info('🔧 DEBUG: Starting step 6 - importBboClientsFromCsv');
			$this->importBboClientsFromCsv($team);
			$this->command->info('🔧 DEBUG: Completed step 6 successfully');
		} catch (\Exception $e) {
			$this->command->error('Error in step 6: ' . $e->getMessage());
			Log::error('BBO Seeder Step 6 Error: ' . $e->getMessage());
		} catch (\Throwable $e) {
			$this->command->error('Fatal error in step 6: ' . $e->getMessage());
			Log::error('BBO Seeder Step 6 Fatal Error: ' . $e->getMessage());
		}

		// 7. Create users for contacts without users
		$this->command->info('🔧 DEBUG: About to execute step 7 - createUsersForContacts');
		try {
			$this->createUsersForContacts($team);
		} catch (\Exception $e) {
			$this->command->error('Error in step 7: ' . $e->getMessage());
			Log::error('BBO Seeder Step 7 Error: ' . $e->getMessage());
		}

		$this->command->info('✅ BBO Client setup completed successfully');

		// Habilitar módulos adicionales para el equipo BBO
		$bboModules = [
			'languages',
			'language-variants',
			'fares',
			'softwares',
			'certifications',
			'stylebooks',
			'notifications',
			'collaborators',
		];
		foreach ($bboModules as $moduleKey) {
			$team->enableModule($moduleKey);
			$this->command->info("✅ Módulo '{$moduleKey}' habilitado para el equipo BBO");
		}


	}

	/**
	 * Create BBO Team
	 */
	private function createBboTeam()
	{
		$bboOwner = User::where('email', 'bego@bbosubtitulado.com')->first();

		if (!$bboOwner) {
			$this->command->info('BBO owner user not found. Creating Bego Ballester Olmos...');

			$bboOwner = User::create([
				'name' => 'Begoña Ballester Olmos',
				'email' => 'bego@bbosubtitulado.com',
				'password' => Hash::make('bbounicornio123'),
				'email_verified_at' => now(),
			]);

			// Assign admin role
			$bboOwner->assignRole('admin');

			$this->command->info('✅ Created BBO owner user: ' . $bboOwner->name);
		}

		// Verificar si ya existe un equipo con ID 4
		$existingTeam = Team::find($this->teamId);

		if ($existingTeam) {
			$this->command->info("✅ Equipo BBO ya existe con ID: {$this->teamId}");

			// Asegurar que el usuario esté en el equipo
			if (!$existingTeam->users()->where('user_id', $bboOwner->id)->exists()) {
				$existingTeam->users()->attach($bboOwner->id, ['role' => 'admin']);
			}

			return $existingTeam;
		}

		// Si no existe, crear el equipo con ID específico 4
		$team = new Team();
		$team->id = $this->teamId;
		$team->user_id = $bboOwner->id;
		$team->name = "BBO's Team";
		$team->personal_team = false;
		$team->save();

		// Ensure the user is in the team
		if (!$team->users()->where('user_id', $bboOwner->id)->exists()) {
			$team->users()->attach($bboOwner->id, ['role' => 'admin']);
		}

		$this->command->info("✅ Created BBO Team (ID: {$team->id})");

		return $team;
	}

	/**
	 * Create BBO users
	 */
	private function createBboUsers($team)
	{
		$this->command->info('👥 Creating BBO users...');

		$bboUsers = [
			[
				'name' => 'Begoña Ballester Olmos',
				'email' => 'bego@bbosubtitulado.com',
				'role' => 2,  // Admin role
			],
			[
				'name' => 'Claudia Caballero',
				'email' => 'claudia@bbosubtitulado.com',
				'role' => 2,  // PM role
			],
			[
				'name' => 'Rocío Broseta',
				'email' => 'rocio@bbosubtitulado.com',
				'role' => 2,  // PM role
			],
			[
				'name' => 'Marta Navas',
				'email' => 'marta@bbosubtitulado.com',
				'role' => 2,  // PM role
			],
			[
				'name' => 'Tom Jackson',
				'email' => 'tom@bbosubtitulado.com',
				'role' => 2,  // PM role
			],
			[
				'name' => 'Jesús Buendía',
				'email' => 'jesus@bbosubtitulado.com',
				'role' => 2,  // PM role
			],
			[
				'name' => 'Vendors',
				'email' => 'vendors@bbosubtitulado.com',
				'role' => 2,  // Vendor Manager role
			],
			[
				'name' => 'Amy Martínez',
				'email' => 'amy@bbosubtitulado.com',
				'role' => 2,  // Admin role
			],
			[
				'name' => 'Tester',
				'email' => 'tester@bbosubtitulado.com',
				'role' => 3,  // Collaborator role
			],
		];

		foreach ($bboUsers as $userData) {
			$user = User::updateOrCreate(
				['email' => $userData['email']],
				[
					'name' => $userData['name'],
					'email' => $userData['email'],
					'password' => Hash::make('bbounicornio123'),
					'email_verified_at' => now(),
					'current_team_id' => $team->id,
				]
			);

			$user->assignRole($userData['role']);

			// Add to team if not already there
			if (!$user->teams()->where('team_id', $team->id)->exists()) {
				$user->teams()->attach($team->id);
			}

			$this->command->info("✅ Created/Updated BBO user: {$userData['name']}");
		}
	}

	/**
	 * Create BBO enterprise
	 */
	private function createBboEnterprise($team)
	{
		$this->command->info('🏢 Creating BBO enterprise...');

		$enterprise = Enterprise::updateOrCreate(
			['name' => 'BBO Translation Agency', 'team_id' => $team->id],
			[
				'name' => 'BBO Translation Agency',
				'team_id' => $team->id,
				'type_id' => 1,  // Client type
				'status_id' => 2,  // Active status
				'creator_id' => 1,
				'email' => 'info@bbosubtitulado.com',
				'phone' => '912345678',
				'website' => 'https://bbo.com',
				'address' => 'Calle Principal 123',
				'locality' => 'Madrid',
				'postal_code' => '28001',
				'country' => 'España',
			]
		);

		$this->command->info("✅ Created BBO enterprise (ID: {$enterprise->id})");
	}

	/**
	 * Create BBO categories
	 */
	private function createBboCategories()
	{
		$this->command->info('📂 Creating BBO categories...');

		// Get the projects module
		$projectsModule = Module::where('key', 'projects')->first();

		if (!$projectsModule) {
			$this->command->warn('Projects module not found, skipping category creation');
			return;
		}

		// Create parent categories for different project types
		$parentCategories = [
			[
				'name' => 'Proyectos de Traducción',
				'description' => 'Proyectos de traducción y localización para BBO',
				'module_id' => $projectsModule->id,
				'team_id' => $this->teamId,
				'status' => 1,
			],
			[
				'name' => 'Categorías de Estilo de Cine',
				'description' => 'Categorías de estilo audiovisual y cine para proyectos BBO',
				'module_id' => $projectsModule->id,
				'team_id' => $this->teamId,
				'status' => 1,
			],
			[
				'name' => 'Tipos de Contenido',
				'description' => 'Diferentes tipos de contenido para proyectos BBO',
				'module_id' => $projectsModule->id,
				'team_id' => $this->teamId,
				'status' => 1,
			],
		];

		// Create parent categories first
		$createdParents = [];
		foreach ($parentCategories as $parentData) {
			$parent = Category::updateOrCreate(
				[
					'name' => $parentData['name'],
					'module_id' => $parentData['module_id'],
					'team_id' => $parentData['team_id'],
				],
				$parentData
			);
			$createdParents[$parentData['name']] = $parent;
			$this->command->info("✅ Created/Updated parent category: {$parentData['name']}");
		}

		// Create subcategories for Translation Projects
		$translationSubcategories = [
			'Traducción Jurídica' => 'Proyectos de traducción jurídica para BBO',
			'Traducción Técnica' => 'Proyectos de traducción técnica para BBO',
			'Traducción Médica' => 'Proyectos de traducción médica y sanitaria',
			'Traducción de Marketing' => 'Proyectos de traducción publicitaria y marketing',
			'Traducción Financiera' => 'Proyectos de traducción financiera y bancaria',
			'Traducción Literaria' => 'Proyectos de traducción literaria y creativa',
		];

		foreach ($translationSubcategories as $name => $description) {
			$category = Category::updateOrCreate(
				[
					'name' => $name,
					'module_id' => $projectsModule->id,
					'team_id' => $this->teamId,
				],
				[
					'name' => $name,
					'description' => $description,
					'module_id' => $projectsModule->id,
					'team_id' => $this->teamId,
					'parent_id' => $createdParents['Proyectos de Traducción']->id,
					'status' => 1,
				]
			);
			$this->command->info("✅ Created/Updated translation subcategory: {$name}");
		}

		// Create subcategories for Film Style Categories
		$filmStyleSubcategories = [
			'Drama' => 'Contenido dramático y teatral',
			'Comedia' => 'Contenido de comedia y humorístico',
			'Documental' => 'Contenido documental y educativo',
			'Acción' => 'Contenido de acción y aventura',
			'Terror' => 'Contenido de terror y suspenso',
			'Romance' => 'Contenido romántico',
			'Ciencia Ficción' => 'Contenido de ciencia ficción y fantasía',
			'Animación' => 'Contenido animado y caricaturas',
			'Reality TV' => 'Contenido de telerrealidad',
			'Noticias' => 'Contenido de noticias y actualidad',
			'Deportes' => 'Contenido deportivo',
			'Infantil' => 'Contenido infantil y familiar',
			'Corporativo' => 'Contenido corporativo y empresarial',
			'Educativo' => 'Contenido educativo y de formación',
			'Comercial' => 'Contenido comercial y publicitario',
		];

		foreach ($filmStyleSubcategories as $name => $description) {
			$category = Category::updateOrCreate(
				[
					'name' => $name,
					'module_id' => $projectsModule->id,
					'team_id' => $this->teamId,
				],
				[
					'name' => $name,
					'description' => $description,
					'module_id' => $projectsModule->id,
					'team_id' => $this->teamId,
					'parent_id' => $createdParents['Categorías de Estilo de Cine']->id,
					'status' => 1,
				]
			);
			$this->command->info("✅ Created/Updated film style subcategory: {$name}");
		}

		// Create subcategories for Content Types
		$contentTypeSubcategories = [
			'Audiodescripción' => 'Audiodescripción para accesibilidad',
			'Doblaje' => 'Proyectos de doblaje y voice-over',
			'Localización' => 'Localización y adaptación de contenido',
			'Posproducción' => 'Proyectos de posproducción y edición',
			'Control de calidad' => 'Control de calidad y revisión de proyectos',
			'Subtitulación' => 'Proyectos de subtitulación y creación de subtítulos',
			'Transcripción' => 'Transcripción y subtitulado cerrado',
			'Voice Over' => 'Proyectos de voice over y narración',
		];

		foreach ($contentTypeSubcategories as $name => $description) {
			$category = Category::updateOrCreate(
				[
					'name' => $name,
					'module_id' => $projectsModule->id,
					'team_id' => $this->teamId,
				],
				[
					'name' => $name,
					'description' => $description,
					'module_id' => $projectsModule->id,
					'team_id' => $this->teamId,
					'parent_id' => $createdParents['Tipos de Contenido']->id,
					'status' => 1,
				]
			);
			$this->command->info("✅ Created/Updated content type subcategory: {$name}");
		}
	}

	/**
	 * Import BBO collaborators from JSON file
	 */
	private function importBboCollaboratorsFromJson($team)
	{
		$this->command->info('📄 Importing BBO collaborators from JSON...');

		// Get the JSON file path
		$jsonFilePath = base_path('../db/bbo.json');

		if (!file_exists($jsonFilePath)) {
			Log::error("JSON file not found: {$jsonFilePath}");
			$this->command->error("JSON file not found: {$jsonFilePath}");
			return;
		}

		$jsonContent = file_get_contents($jsonFilePath);
		$colaboradores = json_decode($jsonContent, true);

		if (!is_array($colaboradores)) {
			Log::error("Invalid JSON format in file: {$jsonFilePath}");
			$this->command->error("Invalid JSON format in file: {$jsonFilePath}");
			return;
		}

		$this->command->info('Found ' . count($colaboradores) . ' BBO collaborators to import');

		$teamId = $team->id;
		$defaultCountry = 724;  // Spain
		$defaultLanguage = 'es';
		$defaultStatusId = 1;
		$collaboratorRoleId = 3;  // collaborator role ID

		foreach ($colaboradores as $colaborador) {
			try {
				$nombre = $colaborador['nombre'] ?? null;
				$email = $colaborador['email'] ?? null;
				$telefono = $colaborador['telefono'] ?? null;
				$pais = $colaborador['pais'] ?? null;
				$poblacion = $colaborador['poblacion'] ?? null;
				$codigoPostal = $colaborador['codigo_postal'] ?? null;
				$nifCif = $colaborador['nif_cif'] ?? null;
				$domicilio = $colaborador['domicilio'] ?? null;
				$valoracion = $colaborador['valoracion'] ?? null;
				$fares = $colaborador['fares'] ?? [];
				$languageVariants = $colaborador['language_variants'] ?? [];
				$services = $colaborador['services'] ?? [];
				$softwares = $colaborador['softwares'] ?? [];

				// Skip if name is empty
				if (empty($nombre)) {
					Log::warning('Skipping record with empty name');
					continue;
				}

				// Check if contact already exists
				$existingContact = null;
				if (!empty($email)) {
					$existingContact = Contact::where('email', $email)
						->where('team_id', $teamId)
						->first();
				}

				if ($existingContact) {
					$this->command->warn("Contact already exists: {$nombre} ({$email})");
					continue;
				}

				// Clean phone number (extract only digits)
				$cleanPhone = !empty($telefono) ? preg_replace('/[^0-9]/', '', $telefono) : null;
				if ($cleanPhone && strlen($cleanPhone) > 15) {
					$cleanPhone = substr($cleanPhone, -15);  // Keep last 15 digits
				}

				// Map country names to IDs that exist in the database
				$countryCode = $this->mapCountryToCode($pais);

				// Map valoration to ID
				$valorationId = $this->mapValorationToId($valoracion);

				// Prepare additional data with extras structure
				$additionalData = [
					'extras' => [
						'phone' => $telefono,
						'pais' => $pais,
						'poblacion' => $poblacion,
						'codigo_postal' => $codigoPostal,
						'nif_cif' => $nifCif,
						'domicilio' => $domicilio,
						'valoracion' => $valoracion,
					],
					'country' => $pais,
					'imported_from_bbo' => true,
					'fares' => $fares,
					'services' => $services,
					'softwares' => $softwares,
					'language_variants' => $languageVariants,
				];

				// Create contact
				$contact = Contact::create([
					'team_id' => $teamId,
					'name' => $nombre,
					'email' => $email ?: null,
					'phone' => $cleanPhone ? (int) $cleanPhone : null,
					'country' => $countryCode,
					'language' => $defaultLanguage,
					'status_id' => $defaultStatusId,
					'valoration_id' => $valorationId, // Assign valoration
					'creator_id' => 1,  // Admin user
					'responsible_id' => 1,  // Admin user
					'data' => $additionalData,
					'profile' => 'BBO Collaborator imported from legacy system',
				]);

				// Log valoration assignment
				if ($valorationId) {
					$this->command->info("✅ Assigned valoration ID {$valorationId} to contact: {$nombre}");
				} else {
					$this->command->info("ℹ️ No valoration assigned to contact: {$nombre} (valoracion: {$valoracion})");
				}

				// Process language combinations
				if (!empty($languageVariants) && is_array($languageVariants)) {
					foreach ($languageVariants as $combination) {
						if (is_array($combination) && count($combination) === 2) {
							$sourceLanguage = $this->mapLanguageNameToCode($combination[0]);
							$targetLanguage = $this->mapLanguageNameToCode($combination[1]);

							if ($sourceLanguage && $targetLanguage && $sourceLanguage !== $targetLanguage) {
								$this->createLanguageVariant($contact, $sourceLanguage, $targetLanguage);
							}
						}
					}
				}

				// Asignar tarifas por combinación de idiomas
				if (!empty($fares) && is_array($fares) && !empty($languageVariants)) {
					foreach ($fares as $fare) {
						$fareName = is_array($fare) ? ($fare[0] ?? null) : $fare;
						$price = is_array($fare) ? ($fare[1] ?? null) : null;
						$currency = is_array($fare) ? ($fare[2] ?? 'EUR') : 'EUR';

						if ($fareName) {
							$fareModel = \App\Models\Fare::where('name', $fareName)
								->where('team_id', $teamId)
								->with('units')
								->first();

							if ($fareModel) {
								// Selecciona la primera unidad disponible para la tarifa
								$unitId = $fareModel->units->isNotEmpty() ? $fareModel->units->first()->id : null;

								foreach ($languageVariants as $combination) {
									if (is_array($combination) && count($combination) === 2) {
										$sourceLanguage = $this->mapLanguageNameToCode($combination[0]);
										$targetLanguage = $this->mapLanguageNameToCode($combination[1]);

										if ($sourceLanguage && $targetLanguage && $sourceLanguage !== $targetLanguage) {
											DB::table('contact_fare')->updateOrInsert(
												[
													'contact_id' => $contact->id,
													'fare_id' => $fareModel->id,
													'source_language_code' => $sourceLanguage,
													'target_language_code' => $targetLanguage,
												],
												[
													'price' => $price ?: 0,
													'currency_code' => $currency ?: 'EUR',
													'unit_id' => $unitId,
													'updated_at' => now(),
													'created_at' => now(),
												]
											);
										}
									}
								}
							}
						}
					}
				}

				// Process fares and software
				$tarifasNoImportadas = [];
				$softwaresNoImportados = [];

				// Fares
				if (!empty($fares) && is_array($fares)) {
					foreach ($fares as $fare) {
						$fareName = is_array($fare) ? ($fare[0] ?? null) : $fare;
						if ($fareName) {
							$fareModel = \App\Models\Fare::where('name', $fareName)
								->where('team_id', $teamId)
								->first();
							if ($fareModel) {
								$contact->fares()->syncWithoutDetaching([
									$fareModel->id => []
								]);
							} else {
								$tarifasNoImportadas[] = $fareName;
							}
						}
					}
				}

				// Software
				if (!empty($softwares) && is_array($softwares)) {
					foreach ($softwares as $software) {
						$softwareName = is_array($software) ? ($software[0] ?? null) : $software;
						if ($softwareName) {
							$softwareModel = \App\Models\Software::where('name', $softwareName)
								->where('team_id', $teamId)
								->first();
							if ($softwareModel) {
								$contact->softwares()->syncWithoutDetaching([$softwareModel->id]);
							} else {
								$softwaresNoImportados[] = $softwareName;
							}
						}
					}
				}

				// Clean successfully imported sections from data field
				$updateData = is_object($contact->data) ? (array) $contact->data : ($contact->data ?? []);
				$needsUpdate = false;

				// Remove successfully imported fares from data field
				if (!empty($fares) && empty($tarifasNoImportadas)) {
					unset($updateData['fares']);
					$needsUpdate = true;
					$this->command->info("✅ Cleaned fares from data field for: {$nombre}");
				} elseif (!empty($tarifasNoImportadas)) {
					$updateData['unimported_fares'] = $tarifasNoImportadas;
					$needsUpdate = true;
				}

				// Remove successfully imported software from data field
				if (!empty($softwares) && empty($softwaresNoImportados)) {
					unset($updateData['softwares']);
					$needsUpdate = true;
					$this->command->info("✅ Cleaned softwares from data field for: {$nombre}");
				} elseif (!empty($softwaresNoImportados)) {
					$updateData['unimported_software'] = $softwaresNoImportados;
					$needsUpdate = true;
				}

				// Remove successfully imported language variants from data field
				if (!empty($languageVariants)) {
					unset($updateData['language_variants']);
					$needsUpdate = true;
					$this->command->info("✅ Cleaned language_variants from data field for: {$nombre}");
				}

				// Remove successfully imported services from data field (always clean as they're stored as JSON)
				if (!empty($services)) {
					unset($updateData['services']);
					$needsUpdate = true;
					$this->command->info("✅ Cleaned services from data field for: {$nombre}");
				}

				// Update contact data if changes were made
				if ($needsUpdate) {
					$contact->data = $updateData;
					$contact->save();
				}

				// Create associated user if email is provided
				if (!empty($email)) {
					$existingUser = User::where('email', $email)->first();

					if (!$existingUser) {
						$user = User::create([
							'name' => $nombre,
							'email' => $email,
							'password' => Hash::make('bbounicornio123'),
							'current_team_id' => $teamId,
							'phone' => $cleanPhone ? (int) $cleanPhone : null,
							'email_verified_at' => now(),
						]);

						// Add user to team
						$user->teams()->attach($teamId);

						// Assign collaborator role
						$user->assignRole('collaborator');

						// Link contact to user
						$contact->update(['user_id' => $user->id]);

						$this->command->info("✅ Created BBO user and contact: {$nombre} ({$email})");
					} else {
						// Link existing user to contact
						$contact->update(['user_id' => $existingUser->id]);

						// Ensure user has collaborator role
						if (!$existingUser->hasRole('collaborator')) {
							$existingUser->assignRole('collaborator');
						}

						$this->command->info("✅ Linked existing user to BBO contact: {$nombre} ({$email})");
					}
				} else {
					$this->command->info("✅ Created BBO contact without user: {$nombre} (no email)");
				}
			} catch (\Exception $e) {
				Log::error('Error importing BBO collaborator: ' . $e->getMessage());
				Log::error('Collaborator data: ' . json_encode($colaborador));
				$this->command->error("Error importing BBO collaborator {$nombre}: " . $e->getMessage());
			}
		}

		$this->command->info('✅ BBO collaborators import completed!');
	}

	/**
	 * Create language variant record for contact
	 */
	private function createLanguageVariant(Contact $contact, string $sourceCode, string $targetCode, int $proficiencyLevel = 4, bool $isCertified = true, ?string $notes = null): void
	{
		// Check if combination already exists
		$existingVariant = ContactLanguageVariant::where('contact_id', $contact->id)
			->where('source_language_code', $sourceCode)
			->where('target_language_code', $targetCode)
			->first();

		if (!$existingVariant) {
			ContactLanguageVariant::create([
				'contact_id' => $contact->id,
				'source_language_code' => $sourceCode,
				'target_language_code' => $targetCode,
				'proficiency_level' => $proficiencyLevel,
				'is_certified' => $isCertified,
				'notes' => $notes,
			]);
		}
	}

	/**
	 * Normalize spaces in a string (trim and reduce multiple spaces to single space)
	 */
	private function normalizeSpaces(string $text): string
	{
		return preg_replace('/\s+/', ' ', trim($text));
	}

	/**
	 * Map language names to language variant codes
	 */
	private function mapLanguageNameToCode(string $languageName): ?string
	{
		// First, check if it's already a valid language code
		if (preg_match('/^[a-z]{2}-[A-Z]{2}$/', $languageName)) {
			$variant = LanguageVariant::where('code', $languageName)->first();
			if ($variant) {
				return $languageName;
			}
		}

		// Normalize the language name to handle multiple spaces
		$normalizedName = strtolower($this->normalizeSpaces($languageName));

		$languageMapping = [
			// Spanish variants
			'español' => 'es-ES',
			'castellano' => 'es-ES',
			'spanish' => 'es-ES',
			'español españa' => 'es-ES',
			'español mexicano' => 'es-MX',
			'español méxico' => 'es-MX',
			'español argentino' => 'es-AR',
			'español colombia' => 'es-CO',
			'español chile' => 'es-CL',
			'español perú' => 'es-PE',
			'español venezuela' => 'es-VE',
			'espanhol' => 'es-ES',
			// English variants
			'inglés' => 'en-US',
			'english' => 'en-US',
			'inglés americano' => 'en-US',
			'inglés estados unidos' => 'en-US',
			'inglés británico' => 'en-GB',
			'inglés reino unido' => 'en-GB',
			'inglés canadiense' => 'en-CA',
			'inglés australia' => 'en-AU',
			'anglès' => 'en-US',
			// French variants
			'francés' => 'fr-FR',
			'french' => 'fr-FR',
			'français' => 'fr-FR',
			'francés francia' => 'fr-FR',
			'francés canadiense' => 'fr-CA',
			'francés bélgica' => 'fr-BE',
			'francés suiza' => 'fr-CH',
			// German variants
			'alemán' => 'de-DE',
			'german' => 'de-DE',
			'deutsch' => 'de-DE',
			'alemán alemania' => 'de-DE',
			'alemán austria' => 'de-AT',
			'alemán suiza' => 'de-CH',
			// Italian variants
			'italiano' => 'it-IT',
			'italian' => 'it-IT',
			'italiano italia' => 'it-IT',
			'italiano suiza' => 'it-CH',
			// Portuguese variants
			'portugués' => 'pt-PT',
			'portuguese' => 'pt-PT',
			'português' => 'pt-PT',
			'portugués portugal' => 'pt-PT',
			'portugués brasil' => 'pt-BR',
			'portugués brasileño' => 'pt-BR',
			// Catalan variants
			'catalán' => 'ca-ES',
			'catalan' => 'ca-ES',
			'català' => 'ca-ES',
			'catalán españa' => 'ca-ES',
			'catalán andorra' => 'ca-AD',
			// Galician variants
			'gallego' => 'gl-ES',
			'galician' => 'gl-ES',
			'galego' => 'gl-ES',
			'GL' => 'gl-ES',
			'gl' => 'gl-ES',
			// Language codes used in the data
			'es-ES' => 'es-ES',
			'es-MX' => 'es-MX',
			'es-AR' => 'es-AR',
			'es-CO' => 'es-CO',
			'es-CL' => 'es-CL',
			'es-PE' => 'es-PE',
			'es-VE' => 'es-VE',
			'en-US' => 'en-US',
			'en-GB' => 'en-GB',
			'en-CA' => 'en-CA',
			'en-AU' => 'en-AU',
			'fr-FR' => 'fr-FR',
			'fr-CA' => 'fr-CA',
			'fr-BE' => 'fr-BE',
			'fr-CH' => 'fr-CH',
			'de-DE' => 'de-DE',
			'de-AT' => 'de-AT',
			'de-CH' => 'de-CH',
			'it-IT' => 'it-IT',
			'it-CH' => 'it-CH',
			'pt-PT' => 'pt-PT',
			'pt-BR' => 'pt-BR',
			'ca-ES' => 'ca-ES',
			'ca-AD' => 'ca-AD',
			'gl-ES' => 'gl-ES',
			'zh-CN' => 'zh-CN',
			'ru-RU' => 'ru-RU',
			'ja-JP' => 'ja-JP',
			'ko-KR' => 'ko-KR',
			'th-TH' => 'th-TH',
		];

		return $languageMapping[$normalizedName] ?? null;
	}

	/**
	 * Map valoration names to valoration IDs for BBO team
	 */
	private function mapValorationToId(?string $valorationName): ?int
	{
		if (empty($valorationName)) {
			return null;
		}

		$valorationMapping = [
			'Top' => ($this->teamId * 10) + 1,
			'Validada' => ($this->teamId * 10) + 2,
			'Interesante' => ($this->teamId * 10) + 3,
			'Ojo' => ($this->teamId * 10) + 5,
			'OJO' => ($this->teamId * 10) + 5, // Handle uppercase version from CSV
			'Lista negra' => ($this->teamId * 10) + 4,
			'En espera' => ($this->teamId * 10) + 5, // Legacy mapping for backward compatibility
		];

		return $valorationMapping[$valorationName] ?? null;
	}

	/**
	 * Map country names to country codes
	 */
	private function mapCountryToCode(?string $country): int
	{
		if (empty($country) || !is_numeric($country)) {
			return 724;  // Default to Spain
		}

		$countryMappings = [
			'España' => 724,
			'Spain' => 724,
			'Argentina' => 32,
			'Bolivia' => 68,
			'Brasil' => 76,
			'Brazil' => 76,
			'Chile' => 152,
			'Colombia' => 170,
			'Costa Rica' => 188,
			'Cuba' => 192,
			'República Dominicana' => 214,
			'Dominican Republic' => 214,
			'Ecuador' => 218,
			'El Salvador' => 222,
			'Estados Unidos' => 840,
			'United States' => 840,
			'EEUU' => 840,
			'USA' => 840,
			'US' => 840,
			'Guatemala' => 320,
			'Honduras' => 340,
			'México' => 484,
			'Mexico' => 484,
			'Nicaragua' => 558,
			'Panamá' => 591,
			'Panama' => 591,
			'Paraguay' => 600,
			'Perú' => 604,
			'Peru' => 604,
			'Uruguay' => 858,
			'Venezuela' => 862,
			'France' => 250,
			'Francia' => 250,
			'Germany' => 276,
			'Alemania' => 276,
			'Italy' => 380,
			'Italia' => 380,
			'Portugal' => 620,
			'China' => 156,
			'Japan' => 392,
			'Japón' => 392,
			'Korea' => 410,
			'Corea' => 410,
			'Thailand' => 764,
			'Tailandia' => 764,
			'Russia' => 643,
			'Rusia' => 643,
		];

		return $countryMappings[$country] ?? 724;
	}

	/**
	 * Create BBO fare types
	 */
	private function createBboFareTypes()
	{
		$this->command->info('🏷️ Creating BBO fare types...');

		$types = [
			['id' => 10, 'name' => 'Traducción audiovisual'],
			['id' => 11, 'name' => 'Traducción general (texto)'],
			['id' => 12, 'name' => 'Accesibilidad audiovisual'],
		];

		foreach ($types as $type) {
			\App\Models\FareType::updateOrCreate(
				['id' => $type['id']],
				$type
			);
		}

		$this->command->info('✅ Created/Updated ' . count($types) . ' BBO fare types');
	}

	/**
	 * Create BBO fares
	 */
	private function createBboFares()
	{
		$this->command->info('💰 Creating BBO fares...');

		$fares = [
			// Traducción audiovisual (type_id = 10)
			['name' => 'Traducción de plantilla', 'type_id' => 10],
			['name' => 'Traducción + subtitulado sin guion (creación de subtítulos)', 'type_id' => 10],
			['name' => 'Traducción + subtitulado con guion (creación de subtítulos)', 'type_id' => 10],
			['name' => 'Traducción sin guion', 'type_id' => 10],
			['name' => 'Traducción con guion', 'type_id' => 10],
			['name' => 'Traducción para locución, doblaje, voice over', 'type_id' => 10],
			['name' => 'Traducción de guion literario', 'type_id' => 10],
			['name' => 'Transcreación', 'type_id' => 10],
			['name' => 'Transcripción', 'type_id' => 10],
			['name' => 'Transcripción + subtitulado (creación de subtítulos)', 'type_id' => 10],
			['name' => 'Adaptación + subtitulado (creación de subtítulos)', 'type_id' => 10],
			['name' => 'Revisión audiovisual', 'type_id' => 10],
			['name' => 'Ajuste de traducción para doblaje', 'type_id' => 10],
			['name' => 'Posedición de traducción audiovisual', 'type_id' => 10],
			['name' => 'Posedición de transcripción', 'type_id' => 10],
			// Traducción general (texto) (type_id = 11)
			['name' => 'Traducción general', 'type_id' => 11],
			['name' => 'Revisión general', 'type_id' => 11],
			['name' => 'Traducción jurídica', 'type_id' => 11],
			['name' => 'Traducción médica', 'type_id' => 11],
			['name' => 'Traducción técnica', 'type_id' => 11],
			['name' => 'Traducción científica', 'type_id' => 11],
			// Accesibilidad audiovisual (type_id = 12)
			['name' => 'Posedición de traducción', 'type_id' => 12],
			['name' => 'Subtítulos para sordos con guion', 'type_id' => 12],
			['name' => 'Subtítulos para sordos sin guion', 'type_id' => 12],
			['name' => 'Adaptación a subtítulos para sordos', 'type_id' => 12],
			['name' => 'Revisión de subtítulos para sordos', 'type_id' => 12],
			['name' => 'Creación guion de audiodescripción', 'type_id' => 12],
			['name' => 'Locución de audiodescripción', 'type_id' => 12],
			['name' => 'Lengua de signos', 'type_id' => 12],
		];

		foreach ($fares as $fare) {
			\App\Models\Fare::updateOrCreate(
				[
					'name' => $fare['name'],
					'team_id' => $this->teamId,
				],
				array_merge($fare, ['team_id' => $this->teamId])
			);
		}

		$this->command->info('✅ Created/Updated ' . count($fares) . ' BBO fares');
	}

	/**
	 * Create BBO fare units relationships
	 */
	private function createBboFareUnits()
	{
		$this->command->info('🔗 Creating BBO fare units relationships...');

		// First, get all the unit IDs - using updated names as defined in UnitsSeeder
		$minuteUnit = \App\Models\Unit::where('type', 'min')->first();
		$tenMinutesUnit = \App\Models\Unit::where('type', '10 min')->first();
		$hourUnit = \App\Models\Unit::where('type', 'h')->first();
		$wordUnit = \App\Models\Unit::where('type', 'pal')->first();
		$pageUnit = \App\Models\Unit::where('type', 'pág')->first();
		$rollUnit = \App\Models\Unit::where('type', 'rollo')->first();

		// Check if units exist before proceeding
		if (!$minuteUnit || !$tenMinutesUnit || !$hourUnit || !$wordUnit || !$pageUnit || !$rollUnit) {
			$this->command->warn('Warning: Some units not found. Skipping BBO fare units creation.');
			return;
		}

		$minuteId = $minuteUnit->id;
		$tenMinutesId = $tenMinutesUnit->id;
		$hourId = $hourUnit->id;
		$wordId = $wordUnit->id;
		$pageId = $pageUnit->id;
		$rollId = $rollUnit->id;

		// Define the relationships for BBO team
		$relationships = [
			// Traducción audiovisual (type_id = 10)
			['fare_name' => 'Traducción de plantilla', 'unit_ids' => [$minuteId]],
			['fare_name' => 'Traducción + subtitulado sin guion (creación de subtítulos)', 'unit_ids' => [$minuteId]],
			['fare_name' => 'Traducción + subtitulado con guion (creación de subtítulos)', 'unit_ids' => [$minuteId]],
			['fare_name' => 'Traducción sin guion', 'unit_ids' => [$minuteId]],
			['fare_name' => 'Traducción con guion', 'unit_ids' => [$pageId]],
			['fare_name' => 'Traducción para locución, doblaje, voice over', 'unit_ids' => [$minuteId, $rollId]],
			['fare_name' => 'Traducción de guion literario', 'unit_ids' => [$pageId]],
			['fare_name' => 'Transcreación', 'unit_ids' => [$hourId]],
			['fare_name' => 'Transcripción', 'unit_ids' => [$minuteId]],
			['fare_name' => 'Transcripción + subtitulado (creación de subtítulos)', 'unit_ids' => [$minuteId]],
			['fare_name' => 'Adaptación + subtitulado (creación de subtítulos)', 'unit_ids' => [$minuteId]],
			['fare_name' => 'Revisión audiovisual', 'unit_ids' => [$minuteId]],
			['fare_name' => 'Ajuste de traducción para doblaje', 'unit_ids' => [$minuteId, $rollId]],
			['fare_name' => 'Posedición de traducción audiovisual', 'unit_ids' => [$hourId, $minuteId]],
			['fare_name' => 'Posedición de transcripción', 'unit_ids' => [$hourId, $minuteId]],
			// Traducción general (texto) (type_id = 11)
			['fare_name' => 'Traducción general', 'unit_ids' => [$wordId]],
			['fare_name' => 'Revisión general', 'unit_ids' => [$wordId]],
			['fare_name' => 'Traducción jurídica', 'unit_ids' => [$wordId]],
			['fare_name' => 'Traducción médica', 'unit_ids' => [$wordId]],
			['fare_name' => 'Traducción técnica', 'unit_ids' => [$wordId]],
			['fare_name' => 'Traducción científica', 'unit_ids' => [$wordId]],
			// Accesibilidad audiovisual (type_id = 12)
			['fare_name' => 'Posedición de traducción', 'unit_ids' => [$hourId, $wordId]],
			['fare_name' => 'Subtítulos para sordos con guion', 'unit_ids' => [$minuteId]],
			['fare_name' => 'Subtítulos para sordos sin guion', 'unit_ids' => [$minuteId]],
			['fare_name' => 'Adaptación a subtítulos para sordos', 'unit_ids' => [$minuteId]],
			['fare_name' => 'Revisión de subtítulos para sordos', 'unit_ids' => [$minuteId]],
			['fare_name' => 'Creación guion de audiodescripción', 'unit_ids' => [$minuteId]],
			['fare_name' => 'Locución de audiodescripción', 'unit_ids' => [$minuteId]],
			['fare_name' => 'Lengua de signos', 'unit_ids' => [$minuteId]],
		];

		$created = 0;
		$skipped = 0;

		// Get all BBO fares and create a mapping by name
		$bboFares = \App\Models\Fare::where('team_id', $this->teamId)->get();
		$fareMapping = $bboFares->keyBy('name');

		// Create the relationships for BBO team only
		foreach ($relationships as $relationship) {
			$fareName = $relationship['fare_name'];

			if ($fareMapping->has($fareName)) {
				$fare = $fareMapping->get($fareName);

				foreach ($relationship['unit_ids'] as $unitId) {
					// Check if relationship already exists
					$existingRelationship = DB::table('fare_unit')
						->where('fare_id', $fare->id)
						->where('unit_id', $unitId)
						->first();

					if (!$existingRelationship) {
						DB::table('fare_unit')->insert([
							'fare_id' => $fare->id,
							'unit_id' => $unitId,
							'created_at' => now(),
							'updated_at' => now(),
						]);
						$created++;
					} else {
						$skipped++;
					}
				}
			} else {
				$this->command->warn("Fare not found: {$fareName} for team {$this->teamId}");
			}
		}

		$this->command->info("✅ Created {$created} new fare-unit relationships");
		if ($skipped > 0) {
			$this->command->info("⏭️ Skipped {$skipped} existing relationships");
		}
	}

	/**
	 * Create users for BBO contacts that have email but no associated user
	 */
	private function createUsersForContacts($team)
	{
		$this->command->info('👥 Creating users for BBO contacts without users...');
		$this->command->info("🔧 DEBUG: Team ID is: {$team->id}");

		// Find BBO contacts with email but no user
		$this->command->info('🔧 DEBUG: Searching for contacts...');
		$contacts = Contact::where('team_id', $team->id)
			->whereNotNull('email')
			->whereNull('user_id')
			->orderBy('name')
			->get();

		$this->command->info("🔧 DEBUG: Found {$contacts->count()} contacts");

		if ($contacts->isEmpty()) {
			$this->command->info('✅ All BBO contacts with email already have users');
			return;
		}

		$this->command->info("Found {$contacts->count()} contacts that need users");

		$created = 0;
		$linked = 0;
		$errors = 0;

		foreach ($contacts as $contact) {
			try {
				// Check if user already exists with this email
				$existingUser = User::where('email', $contact->email)->first();

				if ($existingUser) {
					// Link existing user to contact
					$contact->update(['user_id' => $existingUser->id]);

					// Ensure user has collaborator role
					if (!$existingUser->hasRole('collaborator')) {
						$existingUser->assignRole('collaborator');
					}

					// Ensure user is in BBO team
					if (!$existingUser->teams()->where('team_id', $team->id)->exists()) {
						$existingUser->teams()->attach($team->id);
					}

					$linked++;
					$this->command->info("✅ Linked existing user: {$contact->name} ({$contact->email})");
				} else {
					// Create new user
					$user = User::create([
						'name' => $contact->name,
						'email' => $contact->email,
						'password' => Hash::make('bbounicornio123'),
						'current_team_id' => $team->id,
						'phone' => $contact->phone,
						'email_verified_at' => now(),
					]);

					// Add user to team
					$user->teams()->attach($team->id);

					// Assign collaborator role
					$user->assignRole('collaborator');

					// Link contact to user
					$contact->update(['user_id' => $user->id]);

					$created++;
					$this->command->info("✅ Created user: {$contact->name} ({$contact->email})");
				}
			} catch (\Exception $e) {
				$errors++;
				Log::error("Error creating BBO user for contact {$contact->id}: " . $e->getMessage());
				$this->command->error("❌ Error creating user for {$contact->name} ({$contact->email}): {$e->getMessage()}");
			}
		}

		$this->command->info('📊 User creation summary:');
		$this->command->info("   - New users created: {$created}");
		$this->command->info("   - Existing users linked: {$linked}");
		$this->command->info("   - Errors: {$errors}");
		$this->command->info('✅ User creation for BBO contacts completed!');
	}

	/**
	 * Create BBO software
	 */
	private function createBboSoftware()
	{
		$this->command->info('💻 Creating BBO software...');

		// Get the software module
		$softwareModule = \App\Models\Module::where('key', 'softwares')->first();

		if (!$softwareModule) {
			$this->command->warn('Software module not found, skipping software creation');
			return;
		}

		// Create parent category for software
		$softwareParentCategory = \App\Models\Category::firstOrCreate([
			'name' => 'Software de Traducción Audiovisual',
			'module_id' => $softwareModule->id,
			'team_id' => $this->teamId,
		], [
			'description' => 'Software especializado para traducción audiovisual y localización',
			'status' => 1,
		]);

		// Get or create software subcategories
		$subtitleCategory = \App\Models\Category::firstOrCreate([
			'name' => 'Subtitulación',
			'module_id' => $softwareModule->id,
			'team_id' => $this->teamId,
		], [
			'description' => 'Software para subtitulación y captions',
			'parent_id' => $softwareParentCategory->id,
			'status' => 1,
		]);

		$dubbingCategory = \App\Models\Category::firstOrCreate([
			'name' => 'Doblaje',
			'module_id' => $softwareModule->id,
			'team_id' => $this->teamId,
		], [
			'description' => 'Software para doblaje y audio',
			'parent_id' => $softwareParentCategory->id,
			'status' => 1,
		]);

		$videoEditingCategory = \App\Models\Category::firstOrCreate([
			'name' => 'Edición de video',
			'module_id' => $softwareModule->id,
			'team_id' => $this->teamId,
		], [
			'description' => 'Software para edición de video',
			'parent_id' => $softwareParentCategory->id,
			'status' => 1,
		]);

		$catToolsCategory = \App\Models\Category::firstOrCreate([
			'name' => 'CAT Tools',
			'module_id' => $softwareModule->id,
			'team_id' => $this->teamId,
		], [
			'description' => 'Computer Assisted Translation tools',
			'parent_id' => $softwareParentCategory->id,
			'status' => 1,
		]);

		$developmentCategory = \App\Models\Category::firstOrCreate([
			'name' => 'Desarrollo',
			'module_id' => $softwareModule->id,
			'team_id' => $this->teamId,
		], [
			'description' => 'Software de desarrollo y programación',
			'parent_id' => $softwareParentCategory->id,
			'status' => 1,
		]);

		// Create BBO-specific software
		$bboSoftware = [
			// Subtitulación
			['name' => 'Aegisub', 'team_id' => $this->teamId, 'category_id' => $subtitleCategory->id],
			['name' => 'Subtitle Edit', 'team_id' => $this->teamId, 'category_id' => $subtitleCategory->id],
			['name' => 'Subtitle Workshop', 'team_id' => $this->teamId, 'category_id' => $subtitleCategory->id],
			['name' => 'EZTitles', 'team_id' => $this->teamId, 'category_id' => $subtitleCategory->id],
			['name' => 'SubtitleNEXT', 'team_id' => $this->teamId, 'category_id' => $subtitleCategory->id],
			['name' => 'Ooona', 'team_id' => $this->teamId, 'category_id' => $subtitleCategory->id],
			['name' => 'Amara', 'team_id' => $this->teamId, 'category_id' => $subtitleCategory->id],
			['name' => 'Kapwing', 'team_id' => $this->teamId, 'category_id' => $subtitleCategory->id],
			['name' => 'FAB Subtitler', 'team_id' => $this->teamId, 'category_id' => $subtitleCategory->id],
			['name' => 'VisualSubSync', 'team_id' => $this->teamId, 'category_id' => $subtitleCategory->id],
			['name' => 'Media Subtitler', 'team_id' => $this->teamId, 'category_id' => $subtitleCategory->id],
			['name' => 'Caption Hub', 'team_id' => $this->teamId, 'category_id' => $subtitleCategory->id],
			// Doblaje
			['name' => 'Pro Tools', 'team_id' => $this->teamId, 'category_id' => $dubbingCategory->id],
			['name' => 'Adobe Audition', 'team_id' => $this->teamId, 'category_id' => $dubbingCategory->id],
			['name' => 'Logic Pro X', 'team_id' => $this->teamId, 'category_id' => $dubbingCategory->id],
			['name' => 'Cubase', 'team_id' => $this->teamId, 'category_id' => $dubbingCategory->id],
			['name' => 'REAPER', 'team_id' => $this->teamId, 'category_id' => $dubbingCategory->id],
			['name' => 'Audacity', 'team_id' => $this->teamId, 'category_id' => $dubbingCategory->id],
			['name' => 'GarageBand', 'team_id' => $this->teamId, 'category_id' => $dubbingCategory->id],
			// Edición de video
			['name' => 'Adobe Premiere Pro', 'team_id' => $this->teamId, 'category_id' => $videoEditingCategory->id],
			['name' => 'Final Cut Pro', 'team_id' => $this->teamId, 'category_id' => $videoEditingCategory->id],
			['name' => 'DaVinci Resolve', 'team_id' => $this->teamId, 'category_id' => $videoEditingCategory->id],
			['name' => 'Avid Media Composer', 'team_id' => $this->teamId, 'category_id' => $videoEditingCategory->id],
			['name' => 'Vegas Pro', 'team_id' => $this->teamId, 'category_id' => $videoEditingCategory->id],
			['name' => 'iMovie', 'team_id' => $this->teamId, 'category_id' => $videoEditingCategory->id],
			['name' => 'OpenShot', 'team_id' => $this->teamId, 'category_id' => $videoEditingCategory->id],
			['name' => 'Shotcut', 'team_id' => $this->teamId, 'category_id' => $videoEditingCategory->id],
			// CAT Tools y Software de Traducción
			['name' => 'SDL Trados', 'team_id' => $this->teamId, 'category_id' => $catToolsCategory->id],
			['name' => 'MemoQ', 'team_id' => $this->teamId, 'category_id' => $catToolsCategory->id],
			['name' => 'Wordfast', 'team_id' => $this->teamId, 'category_id' => $catToolsCategory->id],
			['name' => 'Memsource', 'team_id' => $this->teamId, 'category_id' => $catToolsCategory->id],
			['name' => 'Xbench', 'team_id' => $this->teamId, 'category_id' => $catToolsCategory->id],
			['name' => 'OmegaT', 'team_id' => $this->teamId, 'category_id' => $catToolsCategory->id],
			['name' => 'Smartcat', 'team_id' => $this->teamId, 'category_id' => $catToolsCategory->id],
			['name' => 'Phrase TMS', 'team_id' => $this->teamId, 'category_id' => $catToolsCategory->id],
			['name' => 'Crowdin', 'team_id' => $this->teamId, 'category_id' => $catToolsCategory->id],
			['name' => 'Multiterm', 'team_id' => $this->teamId, 'category_id' => $catToolsCategory->id],
			// Software de Desarrollo y Edición
			['name' => 'Visual Studio Code', 'team_id' => $this->teamId, 'category_id' => $developmentCategory->id],
			['name' => 'Notepad++', 'team_id' => $this->teamId, 'category_id' => $developmentCategory->id],
			['name' => 'Adobe Photoshop', 'team_id' => $this->teamId, 'category_id' => $developmentCategory->id],
			['name' => 'Swift', 'team_id' => $this->teamId, 'category_id' => $developmentCategory->id],
			// Software Especializado
			['name' => 'Wincap', 'team_id' => $this->teamId, 'category_id' => $subtitleCategory->id],
			['name' => 'SWXE', 'team_id' => $this->teamId, 'category_id' => $subtitleCategory->id],
			['name' => 'Annotation edit (Mac)', 'team_id' => $this->teamId, 'category_id' => $subtitleCategory->id],
			['name' => 'Spot', 'team_id' => $this->teamId, 'category_id' => $subtitleCategory->id],
			['name' => 'SSTG1', 'team_id' => $this->teamId, 'category_id' => $subtitleCategory->id],
			['name' => 'GTS', 'team_id' => $this->teamId, 'category_id' => $subtitleCategory->id],
			['name' => 'Maestra Suite', 'team_id' => $this->teamId, 'category_id' => $subtitleCategory->id],
			['name' => 'Ayato', 'team_id' => $this->teamId, 'category_id' => $subtitleCategory->id],
		];

		$created = 0;
		$updated = 0;

		foreach ($bboSoftware as $software) {
			$existing = \App\Models\Software::where('name', $software['name'])
				->where('team_id', $software['team_id'])
				->first();

			if ($existing) {
				$existing->update($software);
				$updated++;
			} else {
				\App\Models\Software::create($software);
				$created++;
			}
		}

		$this->command->info("✅ Created {$created} new BBO software entries");
		if ($updated > 0) {
			$this->command->info("🔄 Updated {$updated} existing BBO software entries");
		}
	}

	/**
	 * Create BBO certifications
	 */
	private function createBboCertifications()
	{
		$this->command->info('🏆 Creating BBO certifications...');

		$certifications = [
			// Translation certifications
			['certification' => 'ATA Certification', 'language' => 'en', 'team_id' => $this->teamId],
			['certification' => 'CIOL Diploma in Translation', 'language' => 'en', 'team_id' => $this->teamId],
			['certification' => 'ISO 17100:2015', 'language' => 'en', 'team_id' => $this->teamId],
			['certification' => 'ProZ Certified PRO', 'language' => 'en', 'team_id' => $this->teamId],
			['certification' => 'SDL Trados Certification', 'language' => 'en', 'team_id' => $this->teamId],
			// Spanish certifications
			['certification' => 'DELE C2', 'language' => 'es', 'team_id' => $this->teamId],
			['certification' => 'SIELE Global', 'language' => 'es', 'team_id' => $this->teamId],
			// English certifications
			['certification' => 'TOEFL iBT', 'language' => 'en', 'team_id' => $this->teamId],
			['certification' => 'IELTS Academic', 'language' => 'en', 'team_id' => $this->teamId],
			['certification' => 'Cambridge C2 Proficiency', 'language' => 'en', 'team_id' => $this->teamId],
			['certification' => 'TOEIC', 'language' => 'en', 'team_id' => $this->teamId],
			// French certifications
			['certification' => 'DELF B2', 'language' => 'fr', 'team_id' => $this->teamId],
			['certification' => 'DALF C1', 'language' => 'fr', 'team_id' => $this->teamId],
			['certification' => 'TCF', 'language' => 'fr', 'team_id' => $this->teamId],
			// German certifications
			['certification' => 'Goethe-Zertifikat C1', 'language' => 'de', 'team_id' => $this->teamId],
			['certification' => 'TestDaF', 'language' => 'de', 'team_id' => $this->teamId],
			// Other language certifications
			['certification' => 'JLPT N1', 'language' => 'ja', 'team_id' => $this->teamId],
			['certification' => 'HSK Level 6', 'language' => 'zh', 'team_id' => $this->teamId],
			['certification' => 'TOPIK Level 6', 'language' => 'ko', 'team_id' => $this->teamId],
			// Audiovisual translation certifications
			['certification' => 'ATRAE Professional Certification', 'language' => 'es', 'team_id' => $this->teamId],
			['certification' => 'Subtitling Diploma ESIT', 'language' => 'fr', 'team_id' => $this->teamId],
			['certification' => 'EZTitles Certification', 'language' => 'en', 'team_id' => $this->teamId],
			// BBO-specific certifications
			['certification' => 'BBO Internal Quality Certification', 'language' => 'es', 'team_id' => $this->teamId],
			['certification' => 'BBO Subtitling Specialist', 'language' => 'es', 'team_id' => $this->teamId],
			['certification' => 'BBO Dubbing Specialist', 'language' => 'es', 'team_id' => $this->teamId],
			['certification' => 'BBO Audio Description Specialist', 'language' => 'es', 'team_id' => $this->teamId],
			['certification' => 'BBO Accessibility Expert', 'language' => 'es', 'team_id' => $this->teamId],
		];

		$created = 0;
		$updated = 0;

		foreach ($certifications as $certification) {
			$existing = \App\Models\Certification::where('certification', $certification['certification'])
				->where('team_id', $certification['team_id'])
				->first();

			if ($existing) {
				$existing->update($certification);
				$updated++;
			} else {
				\App\Models\Certification::create($certification);
				$created++;
			}
		}

		$this->command->info("✅ Created {$created} new BBO certification entries");
		if ($updated > 0) {
			$this->command->info("🔄 Updated {$updated} existing BBO certification entries");
		}
	}

	/**
	 * Create BBO stylebooks
	 */
	private function createBboStylebooks()
	{
		$this->command->info('📚 Creating BBO stylebooks...');

		// Create the storage directory if it doesn't exist
		if (!\Illuminate\Support\Facades\Storage::disk('public')->exists('stylebooks')) {
			\Illuminate\Support\Facades\Storage::disk('public')->makeDirectory('stylebooks');
		}

		// Create a placeholder PDF file if it doesn't exist
		$placeholderPath = 'stylebooks/placeholder.pdf';
		if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($placeholderPath)) {
			\Illuminate\Support\Facades\Storage::disk('public')->put($placeholderPath, 'Placeholder file for seeding purposes');
		}

		$stylebooks = [
			// General style guides
			[
				'name' => 'APA Style Guide',
				'language' => 'en',
				'date' => \Carbon\Carbon::now()->subMonths(3),
				'file' => 'stylebooks/placeholder.pdf',
				'team_id' => $this->teamId,
			],
			[
				'name' => 'Chicago Manual of Style',
				'language' => 'en',
				'date' => \Carbon\Carbon::now()->subMonths(6),
				'file' => 'stylebooks/placeholder.pdf',
				'team_id' => $this->teamId,
			],
			[
				'name' => 'MLA Style Guide',
				'language' => 'en',
				'date' => \Carbon\Carbon::now()->subMonths(9),
				'file' => 'stylebooks/placeholder.pdf',
				'team_id' => $this->teamId,
			],
			[
				'name' => 'Manual de Estilo El País',
				'language' => 'es',
				'date' => \Carbon\Carbon::now()->subMonths(1),
				'file' => 'stylebooks/placeholder.pdf',
				'team_id' => $this->teamId,
			],
			[
				'name' => 'Guía de Estilo - RAE',
				'language' => 'es',
				'date' => \Carbon\Carbon::now()->subMonths(5),
				'file' => 'stylebooks/placeholder.pdf',
				'team_id' => $this->teamId,
			],
			[
				'name' => 'Le Petit Robert Style Guide',
				'language' => 'fr',
				'date' => \Carbon\Carbon::now()->subMonths(2),
				'file' => 'stylebooks/placeholder.pdf',
				'team_id' => $this->teamId,
			],
			[
				'name' => 'Duden Style Guide',
				'language' => 'de',
				'date' => \Carbon\Carbon::now()->subMonths(4),
				'file' => 'stylebooks/placeholder.pdf',
				'team_id' => $this->teamId,
			],
			// BBO-specific stylebooks
			[
				'name' => 'BBO Subtitling Style Guide',
				'language' => 'es',
				'date' => \Carbon\Carbon::now()->subMonths(2),
				'file' => 'stylebooks/placeholder.pdf',
				'team_id' => $this->teamId,
			],
			[
				'name' => 'BBO Dubbing Style Guide',
				'language' => 'es',
				'date' => \Carbon\Carbon::now()->subMonths(3),
				'file' => 'stylebooks/placeholder.pdf',
				'team_id' => $this->teamId,
			],
			[
				'name' => 'BBO Audio Description Guidelines',
				'language' => 'es',
				'date' => \Carbon\Carbon::now()->subMonths(1),
				'file' => 'stylebooks/placeholder.pdf',
				'team_id' => $this->teamId,
			],
			[
				'name' => 'BBO Quality Standards Manual',
				'language' => 'es',
				'date' => \Carbon\Carbon::now()->subMonths(4),
				'file' => 'stylebooks/placeholder.pdf',
				'team_id' => $this->teamId,
			],
			[
				'name' => 'BBO Technical Translation Guidelines',
				'language' => 'en',
				'date' => \Carbon\Carbon::now()->subMonths(2),
				'file' => 'stylebooks/placeholder.pdf',
				'team_id' => $this->teamId,
			],
			[
				'name' => 'BBO Legal Translation Style Guide',
				'language' => 'es',
				'date' => \Carbon\Carbon::now()->subMonths(6),
				'file' => 'stylebooks/placeholder.pdf',
				'team_id' => $this->teamId,
			],
		];

		$created = 0;
		$updated = 0;

		foreach ($stylebooks as $stylebook) {
			$existing = \App\Models\Stylebook::where('name', $stylebook['name'])
				->where('team_id', $stylebook['team_id'])
				->first();

			if ($existing) {
				$existing->update($stylebook);
				$updated++;
			} else {
				\App\Models\Stylebook::create($stylebook);
				$created++;
			}
		}

		$this->command->info("✅ Created {$created} new BBO stylebook entries");
		if ($updated > 0) {
			$this->command->info("🔄 Updated {$updated} existing BBO stylebook entries");
		}
	}

	/**
	 * Create BBO valorations
	 */
	private function createBboValorations($team)
	{
		$this->command->info('🏷️ Creating BBO valorations...');

		$valorations = [
			['id' => 1, 'name' => 'Top', 'icon' => '⭐'],
			['id' => 2, 'name' => 'Validada', 'icon' => '✅'],
			['id' => 3, 'name' => 'Interesante', 'icon' => '🕐'],
			['id' => 5, 'name' => 'Ojo', 'icon' => '👁️'],
			['id' => 4, 'name' => 'Lista negra', 'icon' => '❌'],
		];

		$created = 0;
		$updated = 0;

		foreach ($valorations as $valoration) {
			$valorationId = ($team->id * 10) + $valoration['id'];

			$existing = DB::table('contact_valorations')
				->where('id', $valorationId)
				->first();

			if ($existing) {
				DB::table('contact_valorations')
					->where('id', $valorationId)
					->update([
						'team_id' => $team->id,
						'name' => $valoration['name'],
						'icon' => $valoration['icon'],
						'updated_at' => now(),
					]);
				$updated++;
			} else {
				DB::table('contact_valorations')->insert([
					'id' => $valorationId,
					'team_id' => $team->id,
					'name' => $valoration['name'],
					'icon' => $valoration['icon'],
					'created_at' => now(),
					'updated_at' => now(),
				]);
				$created++;
			}
		}

		$this->command->info("✅ Created {$created} new BBO valoration entries");
		if ($updated > 0) {
			$this->command->info("🔄 Updated {$updated} existing BBO valoration entries");
		}
	}

	/**
	 * Ensure languages and language variants are available before processing contacts
	 */
	private function ensureLanguagesAvailable()
	{
		$this->command->info('🌐 Ensuring languages and language variants are available...');

		try {
			// Check if we need to run the language seeders
			$languageCount = 0;
			$variantCount = 0;

			try {
				$languageCount = Language::count();
			} catch (\Exception $e) {
				$this->command->error('Error counting languages: ' . $e->getMessage());
			}

			try {
				$variantCount = LanguageVariant::count();
			} catch (\Exception $e) {
				$this->command->error('Error counting language variants: ' . $e->getMessage());
			}

			if ($languageCount === 0) {
				$this->command->info('📝 Running LanguageSeeder...');
				$this->call('db:seed', ['--class' => \Database\Seeders\LanguageSeeder::class]);
			}

			if ($variantCount === 0) {
				$this->command->info('🌐 Running LanguageVariantSeeder...');
				$this->call('db:seed', ['--class' => \Database\Seeders\LanguageVariantSeeder::class]);
			}

			// Verify that the specific language variants we need are available
			$requiredVariants = ['es-ES', 'en-US', 'fr-FR', 'de-DE'];
			$missingVariants = [];

			try {
				foreach ($requiredVariants as $variantCode) {
					$variant = LanguageVariant::where('code', $variantCode)->first();
					if (!$variant) {
						$missingVariants[] = $variantCode;
					}
				}

				if (!empty($missingVariants)) {
					$this->command->warn('⚠️ Missing language variants: ' . implode(', ', $missingVariants));
					$this->command->info('🌐 Running LanguageVariantSeeder to ensure all variants are available...');
					$this->call('db:seed', ['--class' => \Database\Seeders\LanguageVariantSeeder::class]);
				}
			} catch (\Exception $e) {
				$this->command->error('Error checking required variants: ' . $e->getMessage());
				$this->command->info('🌐 Running LanguageVariantSeeder anyway...');
				$this->call('db:seed', ['--class' => \Database\Seeders\LanguageVariantSeeder::class]);
			}

			$this->command->info("✅ Languages and variants verified. Found {$languageCount} languages and {$variantCount} variants.");
		} catch (\Exception $e) {
			$this->command->error('Error in ensureLanguagesAvailable: ' . $e->getMessage());
			$this->command->info('Continuing with seeding anyway...');
		}
	}

	/**
	 * Import BBO clients from CSV file
	 */
	private function importBboClientsFromCsv($team)
	{
		$this->command->info('📄 Importing BBO clients from CSV...');

		// Get the CSV file path
		$csvFilePath = base_path('../db/bbo_clientes.csv');

		if (!file_exists($csvFilePath)) {
			Log::error("CSV file not found: {$csvFilePath}");
			$this->command->error("CSV file not found: {$csvFilePath}");
			return;
		}

		// Read CSV file
		$csvContent = file_get_contents($csvFilePath);
		$lines = explode("\n", $csvContent);

		// Remove header row
		$header = array_shift($lines);

		// Parse header to get column positions
		$headers = str_getcsv($header);
		$columnMap = array_flip($headers);

		// Define expected columns
		$expectedColumns = [
			'Nombre' => 'name',
			'Logo (url)' => 'logo_url',
			'Libro de estilo (url)' => 'stylebook_url',
			'Gestor BBO' => 'manager',
		];

		// Verify all expected columns exist
		foreach ($expectedColumns as $spanishName => $englishName) {
			if (!isset($columnMap[$spanishName])) {
				$this->command->error("Missing required column: {$spanishName}");
				return;
			}
		}

		$this->command->info('Found ' . count($lines) . ' BBO clients to import');

		$teamId = $team->id;
		$defaultCountry = 724;  // Spain
		$defaultLanguage = 'es';
		$defaultStatusId = 1;
		$clientTypeId = 1;  // Client type

		// Get BBO admin as fallback
		$bboAdmin = User::where('email', 'bego@bbosubtitulado.com')->first();
		if (!$bboAdmin) {
			$this->command->error('BBO admin user not found. Please ensure BBO users are created first.');
			return;
		}

		$created = 0;
		$updated = 0;
		$skipped = 0;
		$errors = 0;

		foreach ($lines as $lineNumber => $line) {
			try {
				// Skip empty lines
				if (empty(trim($line))) {
					continue;
				}

				$row = str_getcsv($line);

				// Skip if row doesn't have enough columns
				if (count($row) < count($expectedColumns)) {
					$skipped++;
					continue;
				}

				$nombre = trim($row[$columnMap['Nombre']] ?? '');
				$logoUrl = trim($row[$columnMap['Logo (url)']] ?? '');
				$stylebookUrl = trim($row[$columnMap['Libro de estilo (url)']] ?? '');
				$gestor = trim($row[$columnMap['Gestor BBO']] ?? '');

				// Skip if name is empty
				if (empty($nombre)) {
					$skipped++;
					continue;
				}

				// Check if enterprise already exists
				$existingEnterprise = \App\Models\Enterprise::where('name', $nombre)
					->where('team_id', $teamId)
					->first();

				if ($existingEnterprise) {
					$this->command->warn("Enterprise already exists: {$nombre}");
					$skipped++;
					continue;
				}

				// Determine responsible_id based on gestor
				$responsibleId = null;
				if (!empty($gestor)) {
					$this->command->info("🔍 Looking for user with email: {$gestor}");
					$responsibleUser = User::where('email', $gestor)->first();
					if ($responsibleUser) {
						$responsibleId = $responsibleUser->id;
						$this->command->info("✅ Assigned {$gestor} (ID: {$responsibleUser->id}) as responsible for: {$nombre}");
					} else {
						$this->command->warn("⚠️ User with email '{$gestor}' not found, leaving responsible_id as null for: {$nombre}");
					}
				} else {
					$this->command->info("ℹ️ No gestor specified for: {$nombre}, leaving responsible_id as null");
				}

				// Prepare additional data with extras structure
				$additionalData = [
					'extras' => [
						'logo_url' => $logoUrl,
						'stylebook_url' => $stylebookUrl,
						'gestor_bbo' => $gestor,
					],
					'imported_from_bbo' => true,
					'import_source' => 'bbo_clientes.csv',
				];

				// Create enterprise
				$enterprise = \App\Models\Enterprise::create([
					'team_id' => $teamId,
					'name' => $nombre,
					'type_id' => $clientTypeId,
					'status_id' => 2,  // status_id 2 (Activo)
					'creator_id' => $bboAdmin->id,
					'responsible_id' => $responsibleId,
					'data' => $additionalData,
				]);

				$created++;
				$this->command->info("✅ Created BBO client: {$nombre} (Responsible: " . ($gestor ?: 'Admin') . ')');
			} catch (\Exception $e) {
				$errors++;
				Log::error('Error importing BBO client at line ' . ($lineNumber + 2) . ': ' . $e->getMessage());
				Log::error('Client data: ' . json_encode($row ?? []));
				$this->command->error("Error importing BBO client {$nombre}: " . $e->getMessage());
			}
		}

		$this->command->info('📊 BBO clients import summary:');
		$this->command->info("   - New clients created: {$created}");
		$this->command->info("   - Existing clients skipped: {$skipped}");
		$this->command->info("   - Errors: {$errors}");
		$this->command->info('✅ BBO clients import completed!');
	}

	private function seedBboLanguageVariants($teamId)
	{
		$variants = [
			['code' => 'es-ES', 'name' => 'Español (España)', 'base_language' => 'es', 'country_code' => 'ES'],
			['code' => 'fr-FR', 'name' => 'Francés (Francia)', 'base_language' => 'fr', 'country_code' => 'FR'],
			['code' => 'en-GB', 'name' => 'Inglés (Reino Unido)', 'base_language' => 'en', 'country_code' => 'GB'],
			['code' => 'pt-PT', 'name' => 'Portugués (Portugal)', 'base_language' => 'pt', 'country_code' => 'PT'],
			['code' => 'ca-ES', 'name' => 'Catalán (España)', 'base_language' => 'ca', 'country_code' => 'ES'],
			['code' => 'de-DE', 'name' => 'Alemán (Alemania)', 'base_language' => 'de', 'country_code' => 'DE'],
			['code' => 'zh-CN', 'name' => 'Chino (China)', 'base_language' => 'zh', 'country_code' => 'CN'],
			['code' => 'it-IT', 'name' => 'Italiano (Italia)', 'base_language' => 'it', 'country_code' => 'IT'],
			['code' => 'ru-RU', 'name' => 'Ruso (Rusia)', 'base_language' => 'ru', 'country_code' => 'RU'],
			['code' => 'es-AR', 'name' => 'Español (Argentina)', 'base_language' => 'es', 'country_code' => 'AR'],
			['code' => 'ja-JP', 'name' => 'Japonés (Japón)', 'base_language' => 'ja', 'country_code' => 'JP'],
			['code' => 'gl-ES', 'name' => 'Gallego (España)', 'base_language' => 'gl', 'country_code' => 'ES'],
			['code' => 'ko-KR', 'name' => 'Coreano (Corea del Sur)', 'base_language' => 'ko', 'country_code' => 'KR'],
			['code' => 'th-TH', 'name' => 'Tailandés (Tailandia)', 'base_language' => 'th', 'country_code' => 'TH'],
		];

		foreach ($variants as $variant) {
			LanguageVariant::updateOrCreate(
				['code' => $variant['code'], 'team_id' => $teamId],
				array_merge($variant, ['team_id' => $teamId])
			);
		}
	}

	/**
	 * Create BBO custom translations
	 */
	private function createBboCustomTranslations($team)
	{
		$this->command->info('🌐 Creating BBO custom translations...');

		// Welcome translation for team 1 (used for unauthenticated users)
		$welcomeTranslation = [
			'key' => 'welcome',
			'value' => '¡Bienvenida a :name! 👋',
			'locale' => 'es',
			'group' => 'auth',
		];

		$existing = \App\Models\CustomTranslation::where('team_id', 1)
			->where('key', $welcomeTranslation['key'])
			->where('group', $welcomeTranslation['group'])
			->where('locale', $welcomeTranslation['locale'])
			->first();

		if ($existing) {
			$existing->update([
				'value' => $welcomeTranslation['value'],
				'updated_at' => now(),
			]);
			$this->command->info("🔄 Updated welcome translation for team 1");
		} else {
			\App\Models\CustomTranslation::create([
				'team_id' => 1,
				'key' => $welcomeTranslation['key'],
				'value' => $welcomeTranslation['value'],
				'locale' => $welcomeTranslation['locale'],
				'group' => $welcomeTranslation['group'],
			]);
			$this->command->info("✅ Created welcome translation for team 1");
		}

		// BBO Team specific translations - Users to Usuarias and Collaborators to Colaboradoras
		$bboTranslations = [
			// Usuario/Usuaria translations
			['key' => 'User', 'value' => 'Usuaria', 'locale' => 'es', 'group' => 'app'],
			['key' => 'User Management', 'value' => 'Gestión de usuarias', 'locale' => 'es', 'group' => 'app'],
			['key' => 'User Profile', 'value' => 'Perfil de usuaria', 'locale' => 'es', 'group' => 'app'],
			['key' => 'User interface', 'value' => 'Interfaz de usuaria', 'locale' => 'es', 'group' => 'app'],
			['key' => 'Add User', 'value' => 'Agregar Usuaria', 'locale' => 'es', 'group' => 'app'],
			['key' => 'Create User', 'value' => 'Crear Usuaria', 'locale' => 'es', 'group' => 'app'],
			['key' => 'Edit User', 'value' => 'Editar Usuaria', 'locale' => 'es', 'group' => 'app'],
			['key' => 'User created successfully.', 'value' => 'Usuaria creada exitosamente.', 'locale' => 'es', 'group' => 'app'],
			['key' => 'User updated successfully.', 'value' => 'Usuaria actualizada exitosamente.', 'locale' => 'es', 'group' => 'app'],
			['key' => 'User removed from team successfully.', 'value' => 'Usuaria removida del equipo exitosamente.', 'locale' => 'es', 'group' => 'app'],
			['key' => 'User details and information', 'value' => 'Detalles e información de la usuaria', 'locale' => 'es', 'group' => 'app'],
			['key' => 'User Information', 'value' => 'Información de la Usuaria', 'locale' => 'es', 'group' => 'app'],
			['key' => 'Do you want to remove this user from the team?', 'value' => '¿Deseas remover esta usuaria del equipo?', 'locale' => 'es', 'group' => 'app'],
			['key' => 'Manage team users and their permissions', 'value' => 'Gestionar usuarias del equipo y sus permisos', 'locale' => 'es', 'group' => 'app'],
			['key' => 'You cannot delete yourself.', 'value' => 'No puedes eliminarte a ti misma.', 'locale' => 'es', 'group' => 'app'],

			// Colaborador/Colaboradora translations
			['key' => 'New Collaborator', 'value' => 'Nueva colaboradora', 'locale' => 'es', 'group' => 'app'],
			['key' => 'Add a new collaborator', 'value' => 'Añadir una nueva colaboradora', 'locale' => 'es', 'group' => 'app'],

			// Admin/Administrator - keeping neutral for now, could be changed if needed
			// ['key' => 'Administrator', 'value' => 'Administradora', 'locale' => 'es', 'group' => 'app'],
			// ['key' => 'Admin', 'value' => 'Admin', 'locale' => 'es', 'group' => 'app'],
		];

		$created = 0;
		$updated = 0;

		foreach ($bboTranslations as $translation) {
			$existing = \App\Models\CustomTranslation::where('team_id', $team->id)
				->where('key', $translation['key'])
				->where('group', $translation['group'])
				->where('locale', $translation['locale'])
				->first();

			if ($existing) {
				$existing->update([
					'value' => $translation['value'],
					'updated_at' => now(),
				]);
				$updated++;
				$this->command->info("🔄 Updated translation for BBO team: {$translation['key']} → {$translation['value']}");
			} else {
				\App\Models\CustomTranslation::create([
					'team_id' => $team->id,
					'key' => $translation['key'],
					'value' => $translation['value'],
					'locale' => $translation['locale'],
					'group' => $translation['group'],
				]);
				$created++;
				$this->command->info("✅ Created translation for BBO team: {$translation['key']} → {$translation['value']}");
			}
		}

		$this->command->info("📊 BBO translations summary:");
		$this->command->info("   - Created: {$created} translations");
		$this->command->info("   - Updated: {$updated} translations");
		$this->command->info("✅ BBO custom translations completed");
	}

	/**
	 * Create BBO topics (all from TopicsSeeder)
	 */
	private function createBboTopics()
	{
		$this->command->info('🎯 Creating BBO topics (all from TopicsSeeder)...');

		// Check if team 4 already has topics
		$existingTeam4Topics = \App\Models\Topic::where('team_id', 4)->count();
		if ($existingTeam4Topics > 0) {
			$this->command->warn("⚠️ Team 4 already has {$existingTeam4Topics} topics. Skipping creation.");
			return;
		}

		// All topics from TopicsSeeder
		$bboTopics = [
			'Medicina',
			'Viajes',
			'Técnica',
			'Ciencia',
			'Cine',
			'Letras',
			'Tecnología',
			'Deportes',
			'Arte',
			'Música',
			'Gastronomía',
			'Historia',
			'Educación',
			'Psicología',
			'Economía',
			'Política',
			'Medio Ambiente',
			'Salud',
			'Cultura',
			'Literatura',
			'Filosofía',
			'Arquitectura',
			'Diseño',
			'Marketing',
			'Negocios',
			'Finanzas',
			'Legal',
			'Inmobiliario',
			'Agricultura',
			'Turismo',
			'Comunicación',
			'Periodismo',
			'Traducción',
			'Interpretación',
			'Subtitulado',
			'Localización',
			'Gaming',
			'E-commerce',
			'Redes Sociales',
			'Automóvil',
			'Energía',
			'Ingeniería',
			'Biotecnología',
			'Farmacéutica',
			'Cosmética',
			'Moda',
			'Textil',
			'Alimentación',
			'Bebidas',
			'Entretenimiento',
		];

		$created = 0;
		$skipped = 0;

		foreach ($bboTopics as $topicName) {
			// Check if topic already exists for team 4
			$existingTopic = \App\Models\Topic::where('name', $topicName)
				->where('team_id', 4)
				->first();

			if ($existingTopic) {
				$skipped++;
				$this->command->info("⏭️ Skipped existing topic: {$topicName}");
			} else {
				// Create new topic for team 4
				\App\Models\Topic::create([
					'name' => $topicName,
					'team_id' => 4,
					'created_at' => now(),
					'updated_at' => now(),
				]);
				$created++;
				$this->command->info("✅ Created BBO topic: {$topicName}");
			}
		}

		$this->command->info('📊 BBO topics creation summary:');
		$this->command->info("   - New topics created: {$created}");
		$this->command->info("   - Topics skipped: {$skipped}");
		$this->command->info("   - Total topics for team 4: " . \App\Models\Topic::where('team_id', 4)->count());
		$this->command->info('✅ BBO topics creation completed successfully!');
	}
}
