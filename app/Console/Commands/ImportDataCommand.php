<?php

namespace App\Console\Commands;

use App\Helpers\PhoneHelper;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Exception;

class ImportDataCommand extends Command
{
	protected $signature = 'import:interactive {--auto : Run automatic import of ALL data (categories, payment types, enterprises, payment accounts, services, projects, invoices, payments, users)}';

	protected $description = 'Interactive menu for importing data from old database';

	protected function testDatabaseConnection()
	{
		$this->info('Testing database connections...');

		try {
			// Test local database connection
			DB::connection()->getPdo();
			$this->info('✓ Local database connection successful: ' . DB::connection()->getDatabaseName());

			// Test remote database connection
			DB::connection('mysql_tmp')->getPdo();
			$this->info('✓ Remote database connection successful: ' . DB::connection('mysql_tmp')->getDatabaseName());

			return true;
		} catch (Exception $e) {
			$this->error('Database connection failed!');
			$this->error('Error: ' . $e->getMessage());

			// Show connection details (without sensitive data)
			$this->warn('Remote Database Configuration:');
			$config = config('database.connections.mysql_tmp');
			$this->table(
				['Setting', 'Value'],
				[
					['Host', $config['host']],
					['Port', $config['port']],
					['Database', $config['database']],
					['Username', $config['username']],
				],
			);

			if ($this->confirm('Would you like to retry the connection?')) {
				return $this->testDatabaseConnection();
			}

			return false;
		}
	}

	protected function showMainMenu()
	{
		return $this->choice('What would you like to import?', [
			1 => '1. Users',
			2 => '2. Categories',
			3 => '3. Payment Types',
			4 => '4. Payment Accounts',
			5 => '5. Enterprises',
			6 => '6. Services',
			7 => '7. Projects',
			8 => '8. Invoices',
			9 => '9. Payments',
			10 => '10. Notification Types',
			11 => '11. Communications',
			12 => '12. Products (CMS7)',
			13 => '13. Import All',
			14 => '14. Exit',
		]);
	}

	protected function showSubMenu($type)
	{
		while (true) {
			$choice = $this->choice("Select action for $type:", [
				1 => '1. Preview All',
				2 => '2. Preview Specific ID',
				3 => '3. Import All',
				4 => '4. Import Specific ID',
				5 => '5. Back to Main Menu',
			]);

			if ($choice === '5. Back to Main Menu') {
				return;
			}

			$id = null;
			if (in_array($choice, ['2. Preview Specific ID', '4. Import Specific ID'])) {
				$id = $this->ask('Enter the ID');
			}

			switch ($choice) {
				case '1. Preview All':
				case '2. Preview Specific ID':
					$this->previewData($type, $id);
					break;
				case '3. Import All':
				case '4. Import Specific ID':
					if ($this->confirm('Are you sure you want to import this data?')) {
						$this->processImport($type, $id);
					}
					break;
			}
		}
	}

	protected function previewData($type, $id = null)
	{
		$this->info("Previewing $type data...");

		try {
			$data = $this->getData($type, $id);

			if ($data->isEmpty()) {
				$this->warn('No data found!');

				return;
			}

			// Show preview in table format
			$headers = array_keys((array) $data->first());
			$rows = $data->map(function ($item) {
				return (array) $item;
			})->toArray();

			$this->table($headers, $rows);

			$this->info('Total records: ' . $data->count());
		} catch (\Exception $e) {
			$this->error('Error previewing data: ' . $e->getMessage());
		}
	}

	protected function getData($type, $id = null)
	{
		$query = match ($type) {
			'1. Users' => DB::connection('mysql_tmp')
				->table('contactos')
				->whereNotNull('email')
				->where('grupo', env('CMS_GROUP'))
				->whereNotNull('id_empresa')
				->where('area_privada', '!=', 6)
				->where('id', '>', 2)
				->whereNotNull('nombre')
				->where('nombre', '!=', '')
				->whereRaw("TRIM(nombre) != ''")
				->select('id', 'email', 'nombre', 'apellido', 'estado', 'id_empresa', 'area_privada', 'telefono', 'celular', 'fecha_alta', 'fecha_modificacion'),

			'2. Categories' => DB::connection('mysql_tmp')
				->table('categorias_generales')
				->where('grupo', env('CMS_GROUP'))
				->where('padre', 10)
				->select('id', 'categoria', 'padre', 'estado'),

			'3. Service Types' => DB::connection('mysql_tmp')
				->table('categorias_generales_tipo')
				->select('id', 'tipo', 'descripcion', 'caracteristicas', 'id_moneda', 'valor', 'descuento', 'frecuencia', 'template_alta_de_servicio', 'orden', 'estado'),

			'4. Payment Accounts' => DB::connection('mysql_tmp')
				->table('cuentas')
				->where('grupo', env('CMS_GROUP'))
				->select('id', 'nombre_cuenta', 'id_empresa', 'id_moneda', 'estado'),

			'5. Enterprises' => DB::connection('mysql_tmp')
				->table('empresas')
				->where('grupo', env('CMS_GROUP'))
				->select('id', 'empresa', 'id_categoria', 'telefono', 'email', 'estado', 'fecha_modificacion'),

			'6. Services' => DB::connection('mysql_tmp')
				->table('servicios')
				->join('servicios_hosting', 'servicios.id', '=', 'servicios_hosting.id_servicio')
				->where('servicios.grupo', env('CMS_GROUP'))
				->where('servicios.estado', '>', 0)
				->where('servicios.operacion', 'V')
				->select('servicios.*', 'servicios_hosting.*'),

			'7. Projects' => DB::connection('mysql_tmp')
				->table('proyectos')
				->where('grupo', env('CMS_GROUP'))
				->select('id', 'nombre', 'id_empresa', 'estado'),

			'8. Invoices' => DB::connection('mysql_tmp')
				->table('facturas')
				->join('empresas_fiscales', 'facturas.id_empresa_fiscal', '=', 'empresas_fiscales.id')
				->where('facturas.grupo', env('CMS_GROUP'))
				->where('facturas.estado', '>', 0)
				->select(
					'facturas.id',
					'empresas_fiscales.id_empresa as enterprise_id',
					'facturas.fecha',
					'facturas.vencimiento',
					'facturas.operacion',
					'facturas.numero_talonario',
					'facturas.numero_factura',
					'facturas.bruto',
					'facturas.descuento',
					'facturas.total_neto',
					'facturas.saldo',
					'facturas.estado',
					'facturas.fecha_alta',
					'facturas.fecha_modificacion',
					'facturas.id_moneda',
					'facturas.id_forma_pago',
					'facturas.observaciones',
					'facturas.id_factura_tipo',
					'facturas.condicion',
					'facturas.IMP105',
					'facturas.IMP210',
					'facturas.IMP270',
					'facturas.EXENTO',
				),

			'9. Payments' => DB::connection('mysql_tmp')
				->table('pagos')
				->where('grupo', env('CMS_GROUP'))
				->select('id', 'id_empresa', 'id_forma_pago', 'estado'),

			'10. Notification Types' => DB::connection('mysql_tmp')
				->table('comunicaciones_tipo')
				->select('id', 'tipo', 'estado'),

			'11. Communications' => DB::connection('mysql_tmp')
				->table('comunicaciones')
				->where('grupo', env('CMS_GROUP'))
				->select('id', 'id_contacto', 'id_tipo', 'asunto', 'estado'),

			'12. Products (CMS7)' => DB::connection('mysql_tmp')
				->table('categorias_generales')
				->where('grupo', env('CMS_GROUP'))
				->whereNull('padre')
				->where('estado', 1)
				->select('id', 'categoria', 'descripcion', 'caracteristicas', 'valor', 'id_moneda', 'estado', 'fecha_alta'),

			default => throw new \Exception('Invalid type selected'),
		};

		if ($id) {
			if ($type === '8. Invoices') {
				$query->where('facturas.id', $id);
			} elseif ($type === '6. Services') {
				$query->where('servicios.id', $id);
			} else {
				$query->where('id', $id);
			}
		}

		return $query->limit(10)->get();  // Limit preview to 10 records
	}

	public function handle()
	{
		$this->info('=== Database Import Tool ===');

		// Test database connection first
		if (!$this->testDatabaseConnection()) {
			$this->error('Exiting due to database connection failure.');

			return 1;
		}

		if ($this->option('auto')) {
			$this->info('🚀 Running in automatic mode: importing ALL data...');
			$this->newLine();

		// Import in order to respect foreign key constraints
		$this->info('📂 Step 1/10: Importing Categories & Service Types...');
		$this->processImport('2. Categories');
		$this->newLine();

		$this->info('🏢 Step 2/10: Importing Enterprises...');
		$this->processImport('5. Enterprises');
		$this->newLine();

		$this->info('📦 Step 3/10: Importing Services...');
		$this->processImport('6. Services');
		$this->newLine();

		$this->info('📁 Step 4/10: Importing Projects...');
		$this->processImport('7. Projects');
		$this->newLine();

		$this->info('📄 Step 5/10: Importing Invoices...');
		$this->processImport('8. Invoices');
		$this->newLine();

		$this->info('💳 Step 6/10: Importing Payment Accounts...');
		$this->processImport('4. Payment Accounts');
		$this->newLine();

		$this->info('💰 Step 7/10: Importing Payments (linking enterprises & invoices)...');
		$this->processImport('9. Payments');
		$this->newLine();

		$this->info('👥 Step 8/10: Importing Users/Contacts...');
		$this->processImport('1. Users');
		$this->newLine();

		$this->info('🔔 Step 9/10: Importing Notification Types...');
		$this->processImport('10. Notification Types');
		$this->newLine();

		$this->info('📞 Step 10/10: Importing Notifications...');
		$this->processImport('11. Communications');
		$this->newLine();

		$this->info('✅ Automatic import completed successfully!');

		return 0;
		}

		while (true) {
			$choice = $this->showMainMenu();

			if ($choice === '14. Exit') {
				$this->info('Goodbye!');
				break;
			}

			if ($choice === '13. Import All') {
				if ($this->confirm('Are you sure you want to import ALL data?')) {
					$this->importAll();
				}

				continue;
			}

			$this->showSubMenu($choice);
		}
	}

	protected function processImport($type, $id = null)
	{
		$this->info('Starting import...');

		try {
		$result = match ($type) {
			'1. Users' => $this->importUsers($id),
			'2. Categories' => $this->importCategories($id),
			'3. Service Types' => $this->importServiceTypes($id),
			'4. Payment Accounts' => $this->importPaymentAccounts($id),
			'5. Enterprises' => $this->importEnterprises($id),
			'6. Services' => $this->importServices($id),
			'7. Projects' => $this->importProjects($id),
			'8. Invoices' => $this->importInvoices($id),
			'9. Payments' => $this->importPayments($id),
			'10. Notification Types' => $this->importNotificationTypes($id),
			'11. Communications' => $this->importCommunications($id),
			'12. Products (CMS7)' => $this->importProductsWithTeam($id),
			default => throw new \Exception('Invalid type selected'),
		};

			if ($result['imported'] === 0) {
				$this->warn('No records were imported.');
				if (isset($result['message'])) {
					$this->line($result['message']);
				}
			} else {
				$this->info("Successfully imported {$result['imported']} records.");
				if (isset($result['updated'])) {
					$this->info("Updated {$result['updated']} existing records.");
				}

				// Mostrar estadísticas de usuarios si están disponibles
				if (isset($result['users_created'])) {
					$this->info("Users created: {$result['users_created']}");
					$this->info("Users existing: {$result['users_existing']}");
					$this->info("Users skipped: {$result['users_skipped']}");
				}
			}
		} catch (\Exception $e) {
			$this->error('Error during import: ' . $e->getMessage());
		}
	}

	protected function importUsers($id = null)
	{
		$stats = [
			'imported' => 0,
			'updated' => 0,
			'users_created' => 0,
			'users_existing' => 0,
			'users_skipped' => 0,
			'message' => null,
		];

		try {
			$query = DB::connection('mysql_tmp')
				->table('contactos')
				->whereNotNull('email')
				->where('grupo', env('CMS_GROUP'))
				->whereNotNull('id_empresa')
				->where('area_privada', '!=', 6)
				->where('id', '>', 2)
				->whereNotNull('nombre')
				->where('nombre', '!=', '')
				->whereRaw("TRIM(nombre) != ''")
				->select('id', 'email', 'nombre', 'apellido', 'estado', 'id_empresa', 'area_privada', 'telefono', 'celular', 'fecha_alta', 'fecha_modificacion');

			if ($id) {
				$query->where('id', $id);
			}

			$contacts = $query->get();

			if ($contacts->isEmpty()) {
				$stats['message'] = 'No records found matching the criteria.';

				return $stats;
			}

			$bar = $this->output->createProgressBar(count($contacts));
			$bar->start();

			// Obtener el ID de la categoría 'Importado de CMS+' para el módulo de contactos y el equipo
			$contactsModuleId = DB::table('modules')->where('key', 'contacts')->value('id');
			$importedCategory = DB::table('categories')
				->where('name', 'CMS+')
				->where('module_id', $contactsModuleId)
				->where('team_id', env('CMS_TEAM_ID'))
				->first();
			$importedCategoryId = $importedCategory ? $importedCategory->id : null;

			foreach ($contacts as $data) {
				$existingContact = DB::table('contacts')->where('id', $data->id)->first();

				$phone = $data->celular ?? $data->telefono ?? null;
				$cleaned_phone = PhoneHelper::clean($phone, '54', true);

				// Determinar status_id según el estado de la empresa
				$statusId = 5;
				if (!empty($data->id_empresa)) {
					$enterprise = DB::table('enterprises')->where('id', $data->id_empresa)->first();
					if ($enterprise && $enterprise->status_id == 1) {
						$statusId = 6;
					}
				}

				// Crear usuario si corresponde según area_privada
				$userId = null;
				$shouldCreateUser = in_array($data->area_privada, [2, 3, 4]);  // 2=admin, 3=client, 4=user

				if ($shouldCreateUser && $data->email) {
					// Mapear area_privada a roles
					$roleMapping = [
						2 => 'admin',
						3 => 'client',
						4 => 'user',
					];

					$roleName = $roleMapping[$data->area_privada];

					// Verificar si ya existe un usuario con este email
					$existingUser = User::where('email', $data->email)->first();

					if (!$existingUser) {
						try {
							// Crear nuevo usuario
							$user = User::create([
								'name' => trim($data->nombre . ' ' . ($data->apellido ?? '')),
								'email' => $data->email,
								'phone' => $cleaned_phone,
								'password' => Hash::make('Simplicity!'),  // Password temporal
								'email_verified_at' => now(),
								'created_at' => $data->fecha_alta,
								'updated_at' => $data->fecha_modificacion,
							]);

							// Asignar rol
							$user->assignRole($roleName);

							// Asignar al equipo CMS
							$teamId = env('CMS_TEAM_ID');
							if ($teamId) {
								$user->teams()->attach($teamId, ['role' => $roleName]);
							}

						$userId = $user->id;
						$stats['users_created']++;
						// Removed verbose logging - progress bar shows overall progress
					} catch (\Exception $e) {
						$stats['users_skipped']++;
						// Only show errors if verbose
					}
				} else {
					$userId = $existingUser->id;
					$stats['users_existing']++;
					// User already exists, skip logging
				}
			} else {
				$stats['users_skipped']++;
				// Contact doesn't require user account or has no email
			}

				$contactData = [
					'id' => $data->id,
					'team_id' => env('CMS_TEAM_ID'),
					'user_id' => $userId,
					'name' => $data->nombre,
					'surname' => $data->apellido,
					'email' => $data->email,
					'phone' => $cleaned_phone,
					'source_id' => null,
					'birthday' => null,
					'profile' => null,
					'country' => 32,
					'language' => 'es',
					'creator_id' => 1,
					'responsible_id' => null,
					'data' => json_encode([
						'imported_from_cms7' => true,
						'area_privada' => $data->area_privada,
						'original_id' => $data->id,
					]),
					'status_id' => $statusId,
					'created_at' => $data->fecha_alta,
					'updated_at' => $data->fecha_modificacion,
				];

				if (!$existingContact) {
					DB::table('contacts')->insert($contactData);
					$stats['imported']++;
				} else {
					// Merge existing data with new imported_from_cms7 flag
					$existingData = json_decode($existingContact->data ?? '{}', true);
					if (!is_array($existingData)) {
						$existingData = [];
					}
					$existingData['imported_from_cms7'] = true;
					$contactData['data'] = json_encode($existingData);
					DB::table('contacts')->where('id', $existingContact->id)->update($contactData);
					$stats['updated']++;
				}

				// Añadir la relación con la empresa si existe id_empresa
				if (!empty($data->id_empresa)) {
					// Verificar si existe la empresa
					$enterpriseExists = DB::table('enterprises')->where('id', $data->id_empresa)->exists();

					if ($enterpriseExists) {
						// Determinar la posición basada en area_privada
						$position = 'Usuario';  // Default position
						$departmentId = null;  // Default department
						switch ($data->area_privada) {
							case 1:
								$position = 'root';
								break;
							case 2:
								$position = 'Reseller';
								break;
							case 3:
								$position = 'Administrador';
								$departmentId = 1;
								break;
							case 4:
								$position = 'Usuario';
								break;
							case 5:
								$position = 'Invitado';
								break;
							default:
								$position = 'Usuario';
								break;
						}

						// Comprobar si ya existe la relación
						$relationExists = DB::table('contact_enterprise')
							->where('contact_id', $data->id)
							->where('enterprise_id', $data->id_empresa)
							->exists();

						if (!$relationExists) {
							// Crear la relación
							DB::table('contact_enterprise')->insert([
								'contact_id' => $data->id,
								'enterprise_id' => $data->id_empresa,
								'position' => $position,
								'department_id' => $departmentId,
						'created_at' => now(),
						'updated_at' => now(),
					]);
					// Relationship added silently - progress bar shows overall progress
				} else {
							// Actualizar la posición si la relación ya existe
							DB::table('contact_enterprise')
								->where('contact_id', $data->id)
								->where('enterprise_id', $data->id_empresa)
								->update([
									'position' => $position,
									'department_id' => $departmentId,
									'updated_at' => now(),
								]);
							$this->info("Updated position to {$position} for contact {$data->id} in enterprise {$data->id_empresa}");
						}
					} else {
						$this->warn("Enterprise with ID {$data->id_empresa} not found, skipping relationship for contact {$data->id}");
					}
				}

				// Al final de la importación de cada contacto:
				if ($importedCategoryId) {
					$exists = DB::table('contact_category')
						->where('contact_id', $data->id)
						->where('category_id', $importedCategoryId)
						->exists();
					if (!$exists) {
						DB::table('contact_category')->insert([
							'contact_id' => $data->id,
							'category_id' => $importedCategoryId,
						]);
					}
				}

				$bar->advance();
			}

			$bar->finish();
			$this->newLine();
		} catch (\Exception $e) {
			$this->newLine();
			throw new \Exception('Error importing contacts: ' . $e->getMessage());
		}

		return $stats;
	}

	/**
	 * Import payment accounts from cuentas table
	 */
	protected function importPaymentAccounts($id = null)
	{
		$this->info('💳 Importing payment accounts...');

		$stats = [
			'imported' => 0,
			'updated' => 0,
			'message' => null,
		];

		try {
			// Currency mapping from old sys_monedas to new currencies
			// Old ID => New ID
			$currencyMap = [
				1 => 840,  // Pesos (ARG) → USD (no hay ARS, usar USD por defecto)
				2 => 840,  // Dólares → USD
				3 => 978,  // Euros → EUR
				4 => 840,  // Dolar Solidario → USD
				5 => 840,  // Dolar MEP → USD
			];

			$query = DB::connection('mysql_tmp')
				->table('cuentas')
				->where('grupo', env('CMS_GROUP'))
				->where('estado', '>', 0);

			if ($id) {
				$query->where('id', $id);
			}

			$accounts = $query->get();

			if ($accounts->isEmpty()) {
				$stats['message'] = 'No payment accounts found.';
				return $stats;
			}

		$this->info("   Found {$accounts->count()} payment accounts to import");
		$bar = $this->output->createProgressBar($accounts->count());
		$bar->start();

		$skipped = 0;
		foreach ($accounts as $account) {
			try {
				$existingAccount = DB::table('payment_accounts')->where('id', $account->id)->first();

			// Generate unique code
			$code = 'PA-' . str_pad($account->id, 6, '0', STR_PAD_LEFT);

			// Generate account name
			$name = $account->nombre_cuenta ?? 'Account #' . $account->id;

			// Map currency ID from old to new
			$oldCurrencyId = $account->id_moneda ?? 1;
			$newCurrencyId = $currencyMap[$oldCurrencyId] ?? 840; // Default to USD if not mapped

			DB::table('payment_accounts')->insert([
				'id' => $account->id,
				'team_id' => 2, // REVISION ALPHA team
				'code' => $code,
				'name' => $name,
				'symbol' => null,
				'currency_id' => $newCurrencyId,
				'status' => $account->estado > 0 ? 1 : 0,
				'created_at' => now(),
				'updated_at' => now(),
			]);

				if ($existingAccount) {
					$stats['updated']++;
				} else {
					$stats['imported']++;
				}

				$bar->advance();
			} catch (\Exception $e) {
				$skipped++;
				if ($skipped <= 10) {
					$this->newLine();
					$this->warn("     Skipped account {$account->id}: " . $e->getMessage());
				}
				$bar->advance();
				continue;
			}
		}

		$bar->finish();
		$this->newLine();

		if ($skipped > 0) {
			$this->warn("   ⚠️  Skipped {$skipped} accounts due to errors");
		}

		$this->info("✅ Imported {$stats['imported']} payment accounts, updated {$stats['updated']}");

		} catch (\Exception $e) {
			$this->newLine();
			throw new \Exception('Error importing payment accounts: ' . $e->getMessage());
		}

		return $stats;
	}

	protected function importEnterprises($id = null)
	{
		$stats = [
			'imported' => 0,
			'updated' => 0,
			'message' => null,
		];

		try {
			$query = DB::connection('mysql_tmp')
				->table('empresas')
				->where('grupo', env('CMS_GROUP'));

			if ($id) {
				$query->where('id', $id);
			}

			$enterprises = $query->get();

			if ($enterprises->isEmpty()) {
				$stats['message'] = 'No records found matching the criteria.';

				return $stats;
			}

			$bar = $this->output->createProgressBar(count($enterprises));
			$bar->start();

			foreach ($enterprises as $data) {
				$existingEnterprise = DB::table('enterprises')->where('id', $data->id)->first();

				if ($data->id_categoria == 3 || $data->id_categoria == 463) {
					$type_id = 2;  // Supplier
				} elseif ($data->id_categoria == 100 || $data->id_categoria == 464) {
					$type_id = 3;  // Partnership
				} else {
					$type_id = 1;  // Client
				}

				// Obtenemos el ID del contacto responsable
				// (Eliminado: ya no se usa responsible_id para relacionar contacto)
				// $contactId = null;
				// if (! empty($data->id_contacto)) {
				//	 // Verificamos si existe directamente en la tabla contacts
				//	 $contactExists = DB::table('contacts')->where('id', $data->id_contacto)->exists();

				//	 if ($contactExists) {
				//		 $contactId = $data->id_contacto;
				//		 $this->info("Found contact with ID {$contactId} for enterprise {$data->id}");
				//	 } else {
				//		 // Si no existe, lo importamos desde la base de datos original
				//		 $contactData = DB::connection('mysql_tmp')
				//			 ->table('contactos')
				//			 ->where('id', $data->id_contacto)
				//			 ->first();

				//		 if ($contactData) {
				//			 // Verificar si el contacto tiene nombre
				//			 if (! empty(trim($contactData->nombre))) {
				//				 $phone = $contactData->celular ?? $contactData->telefono ?? null;
				//				 $cleaned_phone = $phone ? preg_replace('/\D/', '', $phone) : null;
				//				 if (! empty($cleaned_phone) && strpos($cleaned_phone, '54') !== 0) {
				//					 $cleaned_phone = '54'.$cleaned_phone;
				//				 }

				//				 $newContactData = [
				//					 'id' => $contactData->id,
				//					 'team_id' => env('CMS_TEAM_ID'),
				//					 'user_id' => null,
				//					 'name' => $contactData->nombre.' '.$contactData->apellido,
				//					 'source_id' => null,
				//					 'birthday' => null,
				//					 'profile' => null,
				//					 'engagment' => 'temperate',
				//					 'country' => 32,
				//					 'language' => 'es',
				//					 'creator_id' => 1,
				//					 'responsible_id' => null,
				//					 'data' => json_encode([
				//						 'phone' => $cleaned_phone,
				//						 'email' => $contactData->email,
				//					 ]),
				//					 'status_id' => 5,
				//					 'created_at' => $contactData->fecha_alta,
				//					 'updated_at' => $contactData->fecha_modificacion,
				//				 ];

				//				 DB::table('contacts')->insert($newContactData);
				//				 $contactId = $contactData->id;
				//				 $this->info("Contact with ID {$contactId} was imported for enterprise {$data->id}");
				//			 } else {
				//				 $this->warn("Contact with ID {$data->id_contacto} has no name, skipping import");
				//			 }
				//		 } else {
				//			 $this->warn("Contact with ID {$data->id_contacto} not found in source database");
				//		 }
				//	 }
				// }

				// Map legacy estado to new status_id
				// estado = 1 → Inactivo (status_id = 1)
				// estado > 1 → Activo (status_id = 2)
				$statusId = ($data->estado == 1) ? 1 : 2;

				$enterpriseData = [
					'id' => $data->id,
					'name' => $data->empresa,
					'type_id' => $type_id,
					// 'responsible_id' => $contactId, // Eliminado
					'referred_by' => $data->referido ?? null,
					'address' => $data->domicilio ?? null,
					'postal_code' => $data->codigo_postal ?? null,
					'locality' => $data->localidad ?? null,
					'province' => $data->provincia ?? null,
					'country' => $data->pais ?? null,
					'phone' => $data->telefono ?? null,
					'whatsapp' => $data->whatsapp ?? null,
					'email' => $data->email ?? null,
					'website' => $data->web ?? null,
					// 'payment_type_id' => $data->id_forma_pago ?? null,
					// 'invoice_type_id' => $data->id_factura_tipo ?? null,
					'status_id' => $statusId,
					'created_at' => $data->fecha_alta,
					'updated_at' => $data->fecha_modificacion,
					'deleted_at' => null,  // Never soft-delete, preserve history
					'team_id' => env('CMS_TEAM_ID'),
				];

				if (!$existingEnterprise) {
					DB::table('enterprises')->insert($enterpriseData);
					$stats['imported']++;
				} else {
					DB::table('enterprises')->where('id', $existingEnterprise->id)->update($enterpriseData);
					$stats['updated']++;
				}

				$bar->advance();
			}

			$bar->finish();
			$this->newLine();
		} catch (\Exception $e) {
			$this->newLine();
			throw new \Exception('Error importing enterprises: ' . $e->getMessage());
		}

		return $stats;
	}

	/**
	 * Importa las categorías desde el sistema antiguo
	 * Las categorías padre van a 'categories'
	 * Las categorías hijas van a 'service_types' con category_id = padre
	 */
	protected function importCategories($id = null)
	{
		$stats = [
			'imported' => 0,
			'updated' => 0,
			'message' => null,
		];

		try {
			// Get team ID - REVISION ALPHA team
			$teamId = 2;

			// Buscar el módulo de servicios para asignar a las categorías
			$serviceModule = \App\Models\Module::where('key', 'services')->first();

			// Buscar el módulo de proyectos para categorías de proyectos
			$projectModule = \App\Models\Module::where('key', 'projects')->first();

			if (!$serviceModule) {
				$this->warn("El módulo 'services' no existe. Las categorías se importarán sin módulo asignado.");
			}

			if (!$projectModule) {
				$this->warn("El módulo 'projects' no existe. Las categorías de proyectos se importarán sin módulo asignado.");
			}

			// Obtener todas las categorías del sistema antiguo
			$query = DB::connection('mysql_tmp')
				->table('categorias_generales')
				->where('grupo', env('CMS_GROUP'))
				->where('estado', '>', 0);

			if ($id) {
				$query->where('id', $id);
			}

			$allCategories = $query->get();

			if ($allCategories->isEmpty()) {
				$stats['message'] = 'No se encontraron categorías para importar.';
				return $stats;
			}

			// Separar categorías padre e hijas
			$parentCategories = $allCategories->filter(function($cat) {
				return is_null($cat->padre) || $cat->padre == 0;
			});

			$childCategories = $allCategories->filter(function($cat) {
				return !is_null($cat->padre) && $cat->padre > 0;
			});

			$this->info("📊 Total categorías: {$allCategories->count()} (Padres → categories: {$parentCategories->count()}, Hijas → service_types: {$childCategories->count()})");

			// Primero importar categorías padre a la tabla 'categories'
			if ($parentCategories->isNotEmpty()) {
				$this->info("\n🔹 Importando categorías padre a tabla 'categories'...");
				$bar = $this->output->createProgressBar($parentCategories->count());
				$bar->start();

				foreach ($parentCategories as $data) {
					$categoryData = [
						'id' => $data->id, // Mantener ID original
						'name' => $data->categoria,
						'module_id' => $serviceModule ? $serviceModule->id : null,
						'team_id' => $teamId,
						'parent_id' => null,
						'description' => strip_tags($data->descripcion ?? ''),
						'data' => json_encode([
							'currency_id' => $data->id_moneda ?? null,
							'price' => $data->valor ?? null,
							'discount' => $data->descuento ?? null,
							'frequency' => $data->frecuencia ?? null,
							'type_id' => $data->id_tipo ?? null,
						]),
						'order' => $data->orden ?? 0,
						'status' => $data->estado ?? 1,
						'created_at' => $data->fecha_alta ?? now(),
						'updated_at' => $data->fecha_modificacion ?? now(),
					];

					$existingCategory = DB::table('categories')->where('id', $data->id)->first();

					if (!$existingCategory) {
						DB::table('categories')->insert($categoryData);
						$stats['imported']++;
					} else {
						DB::table('categories')->where('id', $existingCategory->id)->update($categoryData);
						$stats['updated']++;
					}

					$bar->advance();
				}

				$bar->finish();
				$this->newLine();
			}

			// Luego importar categorías hijas a la tabla 'service_types'
			if ($childCategories->isNotEmpty()) {
				$this->info("\n🔹 Importando categorías hijas a tabla 'service_types'...");
				$bar = $this->output->createProgressBar($childCategories->count());
				$bar->start();

				$serviceTypesImported = 0;
				$serviceTypesUpdated = 0;

				foreach ($childCategories as $data) {
					// Verificar que el padre existe en categories
					$parentExists = DB::table('categories')->where('id', $data->padre)->exists();

					if (!$parentExists) {
						$this->warn("\n⚠️  Padre {$data->padre} no existe para categoría {$data->id}: {$data->categoria}");
						continue;
					}

					$serviceTypeData = [
						'id' => $data->id,
						'name' => $data->categoria,
						'category_id' => $data->padre, // El padre es el category_id
						'description' => strip_tags($data->descripcion ?? ''),
						'data' => json_encode([
							'characteristics' => $data->caracteristicas ?? null,
						]),
						'currency_id' => $data->id_moneda ?? 1,
						'convert_to' => $data->convertir ?? null,
						'price' => $data->valor ?? null,
						'discount' => $data->descuento ?? 0.00,
						'frequency' => $data->frecuencia ?? 1,
						'order' => $data->orden ?? 0,
						'status' => $data->estado ?? 1,
						'created_at' => $data->fecha_alta ?? now(),
						'updated_at' => $data->fecha_modificacion ?? now(),
					];

					$existingServiceType = DB::table('service_types')->where('id', $data->id)->first();

					if (!$existingServiceType) {
						DB::table('service_types')->insert($serviceTypeData);
						$serviceTypesImported++;
					} else {
						DB::table('service_types')->where('id', $existingServiceType->id)->update($serviceTypeData);
						$serviceTypesUpdated++;
					}

					$bar->advance();
				}

				$bar->finish();
				$this->newLine();
				$this->info("✅ Service types importados: {$serviceTypesImported}, actualizados: {$serviceTypesUpdated}");
			}

			$this->info("✅ Categorías padre importadas: {$stats['imported']}, actualizadas: {$stats['updated']}");

		} catch (\Exception $e) {
			$this->newLine();
			throw new \Exception('Error importando categorías: ' . $e->getMessage());
		}

		return $stats;
	}

	/**
	 * Importa los tipos de servicio desde el sistema antiguo
	 */
	protected function importServiceTypes($id = null)
	{
		$stats = [
			'imported' => 0,
			'updated' => 0,
			'message' => null,
		];

		try {
			// Obtener todos los tipos de servicio del sistema antiguo
			$query = DB::connection('mysql_tmp')
				->table('categorias_generales_tipo');

			if ($id) {
				$query->where('id', $id);
			}

			$serviceTypes = $query->get();

			if ($serviceTypes->isEmpty()) {
				$stats['message'] = 'No se encontraron tipos de servicio para importar.';
				return $stats;
			}

			$this->info("📊 Total tipos de servicio: {$serviceTypes->count()}");

			$bar = $this->output->createProgressBar($serviceTypes->count());
			$bar->start();

			foreach ($serviceTypes as $data) {
				$serviceTypeData = [
					'id' => $data->id,
					'name' => $data->tipo,
					'category_id' => null, // Se puede asignar manualmente después si es necesario
					'description' => $data->descripcion ?? null,
					'data' => json_encode([
						'characteristics' => $data->caracteristicas ?? null,
						'template_alta_de_servicio' => $data->template_alta_de_servicio ?? null,
					]),
					'currency_id' => $data->id_moneda ?? 1,
					'convert_to' => $data->convertir ?? null,
					'price' => $data->valor ?? null,
					'discount' => $data->descuento ?? 0.00,
					'frequency' => $data->frecuencia ?? 1,
					'order' => $data->orden ?? 0,
					'status' => $data->estado ?? 1,
					'created_at' => $data->fecha_alta ?? now(),
					'updated_at' => $data->fecha_modificacion ?? now(),
				];

				$existingServiceType = DB::table('service_types')->where('id', $data->id)->first();

				if (!$existingServiceType) {
					DB::table('service_types')->insert($serviceTypeData);
					$stats['imported']++;
				} else {
					DB::table('service_types')->where('id', $existingServiceType->id)->update($serviceTypeData);
					$stats['updated']++;
				}

				$bar->advance();
			}

			$bar->finish();
			$this->newLine();

			$this->info("✅ Tipos de servicio importados: {$stats['imported']}, actualizados: {$stats['updated']}");

		} catch (\Exception $e) {
			$this->newLine();
			throw new \Exception('Error importando tipos de servicio: ' . $e->getMessage());
		}

		return $stats;
	}

	/**
	 * Helper method to check if a string is valid JSON
	 */
	protected function isJson($string)
	{
		if (!is_string($string)) {
			return false;
		}

		json_decode($string);

		return json_last_error() == JSON_ERROR_NONE;
	}

	protected function importInvoices($id = null)
	{
		$stats = [
			'imported' => 0,
			'updated' => 0,
			'message' => null,
		];

		try {
			$query = DB::connection('mysql_tmp')
				->table('facturas')
				->join('empresas_fiscales', 'facturas.id_empresa_fiscal', '=', 'empresas_fiscales.id')
				->where('facturas.grupo', env('CMS_GROUP'))
				->where('facturas.estado', '>', 0)
				->select(
					'facturas.id',
					'empresas_fiscales.id_empresa as enterprise_id',
					'facturas.fecha',
					'facturas.vencimiento',
					'facturas.operacion',
					'facturas.numero_talonario',
					'facturas.numero_factura',
					'facturas.bruto',
					'facturas.descuento',
					'facturas.total_neto',
					'facturas.saldo',
					'facturas.estado',
					'facturas.fecha_alta',
					'facturas.fecha_modificacion',
					'facturas.id_moneda',
					'facturas.id_forma_pago',
					'facturas.observaciones',
					'facturas.id_factura_tipo',
					'facturas.condicion',
					'facturas.IMP105',
					'facturas.IMP210',
					'facturas.IMP270',
					'facturas.EXENTO',
				);

			if ($id) {
				$query->where('facturas.id', $id);
			}

			$invoices = $query->get();

			if ($invoices->isEmpty()) {
				$stats['message'] = 'No invoices found matching the criteria.';

				return $stats;
			}

			$bar = $this->output->createProgressBar(count($invoices));
			$bar->start();

			foreach ($invoices as $data) {
				$existingInvoice = DB::table('invoices')->where('id', $data->id)->first();

				// Map operation from V/C to sell/buy
				$operation = 'sell';
				if ($data->operacion === 'C') {
					$operation = 'buy';
				}

				// Format invoice number based on operation type
				$invoiceNumber = '1';
				if ($operation === 'sell') {
					// For sales invoices, format as 0000-00000000
					$talonario = str_pad($data->numero_talonario ?? 0, 4, '0', STR_PAD_LEFT);
					$numero = str_pad($data->numero_factura ?? 0, 8, '0', STR_PAD_LEFT);
					$invoiceNumber = $talonario . '-' . $numero;
				} else {
					// For purchase invoices, use number_factura as is
					$invoiceNumber = $data->numero_factura ?? '1';
				}

				// Create a data object for additional fields not in the main table
				$additionalData = [
					'currency_id' => $data->id_moneda ?? 1,
					'payment_type_id' => $data->id_forma_pago ?? 1,
					'observations' => $data->observaciones,
					'condition' => $data->condicion,
					'tax_105' => $data->IMP105,
					'tax_210' => $data->IMP210,
					'tax_270' => $data->IMP270,
					'exempt' => $data->EXENTO,
				];

				$invoiceData = [
					'id' => $data->id,
					'enterprise_id' => $data->enterprise_id,
					'type_id' => 1,  // Set to 1 (fixed value) since original types may not exist
					'billing_id' => null,  // Set to null for now
					'operation' => $operation,
					'number' => $invoiceNumber,
					'date' => $data->fecha,
					'due_date' => $data->vencimiento,
					'gross_amount' => $data->bruto ?? 0,
					'discount' => $data->descuento,
					'total_amount' => $data->total_neto ?? 0,
					'balance' => $data->saldo ?? 0,
					'status' => $data->estado,
					// 'data' => json_encode($additionalData),
					'created_at' => $data->fecha_alta ?? now(),
					'updated_at' => $data->fecha_modificacion ?? now(),
				];

				if (!$existingInvoice) {
					DB::table('invoices')->insert($invoiceData);
					$stats['imported']++;
				} else {
					DB::table('invoices')->where('id', $existingInvoice->id)->update($invoiceData);
					$stats['updated']++;
				}

				$bar->advance();
			}

			$bar->finish();
			$this->newLine();
		} catch (\Exception $e) {
			$this->newLine();
			throw new \Exception('Error importing invoices: ' . $e->getMessage());
		}

		return $stats;
	}

	/**
	 * Import services from remote database
	 */
	protected function importServices($id = null)
	{
		$this->info('📦 Importing services from remote database...');

		$stats = [
			'imported' => 0,
			'updated' => 0,
			'message' => null,
		];

		try {
			// Test connection
			DB::connection('mysql_tmp')->getPdo();

			// Get the CMS group
			$cmsGroup = env('CMS_GROUP', 502);
			$this->info("   Using CMS_GROUP: {$cmsGroup}");

			$query = DB::connection('mysql_tmp')
				->table('servicios')
				->where('servicios.grupo', $cmsGroup)
				->where('servicios.estado', '>', 0)
				->where('servicios.operacion', 'V');  // Only sales

			if ($id) {
				$query->where('servicios.id', $id);
			}

			$services = $query->get();

		if ($services->isEmpty()) {
			$stats['message'] = 'No services found matching the criteria.';
			return $stats;
		}

		$this->info("   Found {$services->count()} services to import");
		$bar = $this->output->createProgressBar($services->count());
		$bar->start();

		$skipped = 0;
		foreach ($services as $service) {
				try {
					// Map operation codes: V=sell (Venta), C=buy (Compra)
					$operation = ($service->operacion ?? 'V') === 'V' ? 'sell' : 'buy';

					$existingService = \App\Models\Service::where('id', $service->id)->first();

					\App\Models\Service::updateOrCreate(
						['id' => $service->id],
						[
							'enterprise_id' => $service->id_empresa,
							'service_type_id' => $service->id_categoria ?? null,
							'operation' => $operation,
							'description' => strip_tags($service->descripcion ?? ''),
							'price' => $service->valor ?? 0,
							'frequency' => $service->frecuencia ?? 'M',
							'currency_id' => $service->id_moneda ?? 1,
							'discount' => $service->descuento ?? 0,
							'status' => $service->estado ?? 1,
							'next_billing' => $service->proxima ?? null,
							'last_billed' => $service->ultima ?? null,
							'expires_at' => $service->caduca ?? null,
							'created_at' => $service->fecha_alta ?? now(),
							'updated_at' => $service->fecha_modificacion ?? now(),
						]
					);

					if ($existingService) {
						$stats['updated']++;
				} else {
					$stats['imported']++;
				}
				$bar->advance();
			} catch (\Exception $e) {
				$skipped++;
				if ($skipped <= 10) {
					$this->newLine();
					$this->warn("     Skipped service {$service->id}: " . $e->getMessage());
				}
				$bar->advance();
			}
		}

		$bar->finish();
		$this->newLine();

		if ($skipped > 0) {
			$this->warn("   ⚠️  Skipped {$skipped} services due to errors");
		}

		$this->info("✅ Imported {$stats['imported']} services, updated {$stats['updated']}");
		} catch (\Exception $e) {
			$this->warn('⚠️  Could not import services: ' . $e->getMessage());
		}

		return $stats;
	}

	/**
	 * Import projects from remote database
	 */
	protected function importProjects($id = null)
	{
		$this->info('📁 Importing projects from remote database...');

		$stats = [
			'imported' => 0,
			'updated' => 0,
			'message' => null,
		];

		try {
			// Test connection
			DB::connection('mysql_tmp')->getPdo();

			// Get the CMS group
			$cmsGroup = env('CMS_GROUP', 502);
			$this->info("   Using CMS_GROUP: {$cmsGroup}");

			$query = DB::connection('mysql_tmp')
				->table('proyectos')
				->where('grupo', $cmsGroup)
				->where('estado', '>', 0);

			if ($id) {
				$query->where('id', $id);
			}

			$projects = $query->get();

		if ($projects->isEmpty()) {
			$stats['message'] = 'No projects found matching the criteria.';
			return $stats;
		}

		$this->info("   Found {$projects->count()} projects to import");
		$bar = $this->output->createProgressBar($projects->count());
		$bar->start();

		$skipped = 0;
		foreach ($projects as $project) {
				try {
					// Get team ID - REVISION ALPHA team
					$teamId = 2;

					// Get responsible user - default to the team owner if not found
					$responsibleId = \App\Models\User::where('email', 'diego.mascarenhas@icloud.com')->first()->id;

					// Check if enterprise exists
					if (!DB::table('enterprises')->where('id', $project->id_empresa)->exists()) {
						$skipped++;
						continue;
					}

					// Verificar si la categoría existe en categories, si no, usar NULL
					$categoryId = null;
					if ($project->id_categoria) {
						$categoryExists = DB::table('categories')->where('id', $project->id_categoria)->exists();
						if ($categoryExists) {
							$categoryId = $project->id_categoria;
						}
					}

					$existingProject = \App\Models\Project::where('id', $project->id)->first();

					\App\Models\Project::updateOrCreate(
						['id' => $project->id],
						[
							'team_id' => $teamId,
							'enterprise_id' => $project->id_empresa,
							'category_id' => $categoryId,
							'responsible_id' => $responsibleId,
							'name' => $project->titulo ?? 'Proyecto ' . $project->id,
							'real_name' => null,
							'description' => $project->descripcion ?? null,
							'date_material' => null,
							'date_start' => $project->desde ?? null,
							'date_end' => $project->hasta ?? null,
							'cost' => $project->costo ?? 0,
							'price' => $project->valor ?? 0,
							'discount' => $project->descuento ?? 0,
							'status_id' => $project->estado ?? 1,
							'created_at' => $project->fecha_alta ?? now(),
							'updated_at' => $project->fecha_modificacion ?? now(),
						]
					);

				if ($existingProject) {
					$stats['updated']++;
				} else {
					$stats['imported']++;
				}
				$bar->advance();
			} catch (\Exception $e) {
				$skipped++;
				if ($skipped <= 10) {
					$this->newLine();
					$this->warn("     Skipped project {$project->id}: " . $e->getMessage());
				}
				$bar->advance();
			}
		}

		$bar->finish();
		$this->newLine();

		if ($skipped > 0) {
			$this->warn("   ⚠️  Skipped {$skipped} projects due to errors");
		}

		$this->info("✅ Imported {$stats['imported']} projects, updated {$stats['updated']}");
		} catch (\Exception $e) {
			$this->warn('⚠️  Could not import projects: ' . $e->getMessage());
		}

		return $stats;
	}

	/**
	 * Import payments from remote database
	 */
	protected function importPayments($id = null)
	{
		$this->info('💰 Importing payments from remote database...');

		$stats = [
			'imported' => 0,
			'updated' => 0,
			'message' => null,
		];

		try {
			// Verify payments table exists
			if (!\Illuminate\Support\Facades\Schema::hasTable('payments')) {
				$this->warn('⚠️  Payments table does not exist. Skipping payment import.');
				$this->info('   Run: php artisan vendor:publish --tag="humano-billing-migrations" && php artisan migrate');
				return $stats;
			}

			// Test connection
			DB::connection('mysql_tmp')->getPdo();

		// Get the CMS group
		$cmsGroup = env('CMS_GROUP', 502);
		$this->info("   Using CMS_GROUP: {$cmsGroup}");

		// Use team_id directly
		$teamId = 2; // REVISION ALPHA team

		// Payment type mapping from legacy to new IDs
			$paymentTypeMap = [
				1 => 1,  // Cash
				2 => 2,  // Bank Transfer
				3 => 3,  // Bank Deposit
				4 => 4,  // Check
				5 => 5,  // Debit
				10 => 6,  // Credit Card
				7 => 7,  // PayPal
				17 => 8,  // Stripe
				6 => 12,  // MercadoPago
				13 => 12,  // MercadoPago
				14 => 12,  // MercadoPago
			];

		$query = DB::connection('mysql_tmp')
			->table('movimientos')
			->leftJoin('facturas', 'movimientos.id_factura', '=', 'facturas.id')
			->leftJoin('empresas_fiscales', 'facturas.id_empresa_fiscal', '=', 'empresas_fiscales.id')
			->where('movimientos.grupo', $cmsGroup)
			->where('movimientos.estado', '>', 0)
			->where(function($q) {
				// Si tiene factura, la factura debe tener estado > 0
				// Si no tiene factura, permitir el pago
				$q->whereNull('movimientos.id_factura')
				  ->orWhere('facturas.estado', '>', 0);
			})
			->select(
				'movimientos.*',
				'empresas_fiscales.id_empresa as enterprise_id',
				'facturas.id_empresa_fiscal'
			);

			if ($id) {
				$query->where('movimientos.id', $id);
			}

			$payments = $query->get();

		if ($payments->isEmpty()) {
			$stats['message'] = 'No payments found matching the criteria.';
			return $stats;
		}

	$this->info("   Found {$payments->count()} payments to import");
	$bar = $this->output->createProgressBar($payments->count());
	$bar->start();

	// Get default account for team (we ensured it exists above)
	$defaultTeamAccount = DB::table('payment_accounts')->where('team_id', $teamId)->first();

	$skipped = 0;
	foreach ($payments as $payment) {
			try {
				// Get account ID - if not exists, use default account for this team
				$accountId = $payment->id_cuenta;
			if (!$accountId || !DB::table('payment_accounts')->where('id', $accountId)->exists()) {
				// Use the default team account
				$accountId = $defaultTeamAccount->id;
				}

					// Map legacy payment type ID to new ID
					$legacyTypeId = $payment->id_forma_pago ?? 1;
					$typeId = $paymentTypeMap[$legacyTypeId] ?? 1;  // Default to Cash if not mapped

					// Determine transaction type: I=Income, E=Expense (default to expense if unknown)
					$transactionType = 'expense';
					if (isset($payment->transaccion)) {
						$transactionType = strtoupper($payment->transaccion) === 'I' ? 'income' : 'expense';
					}

					// Get amount from 'valor' field
					$amount = $payment->valor ?? 0;

					// Get enterprise_id from multiple sources
					$enterpriseId = null;

					// 1. Try from the JOIN result
					if ($payment->enterprise_id) {
						if (DB::table('enterprises')->where('id', $payment->enterprise_id)->exists()) {
							$enterpriseId = $payment->enterprise_id;
						}
					}

					// 2. If still null, try to get from invoice
					$invoiceId = $payment->id_factura;
					if (!$enterpriseId && $invoiceId) {
						$invoice = DB::table('invoices')->where('id', $invoiceId)->first();
						if ($invoice && $invoice->enterprise_id) {
							$enterpriseId = $invoice->enterprise_id;
						}
						// If invoice doesn't exist, set invoiceId to null
						if (!$invoice) {
							$invoiceId = null;
						}
					}

					// 3. If still null and we have id_empresa_fiscal, try to find the enterprise
				if (!$enterpriseId && isset($payment->id_empresa_fiscal)) {
					$enterpriseFromFiscal = DB::table('enterprises')
						->where('id', $payment->id_empresa_fiscal)
						->where('team_id', $teamId)
						->first();
						if ($enterpriseFromFiscal) {
							$enterpriseId = $enterpriseFromFiscal->id;
						}
					}

					$existingPayment = \Idoneo\HumanoBilling\Models\Payment::where('id', $payment->id)->first();

				\Idoneo\HumanoBilling\Models\Payment::updateOrCreate(
					['id' => $payment->id],
					[
						'team_id' => $teamId,
							'enterprise_id' => $enterpriseId,
							'invoice_id' => $invoiceId,
							'transaction_type' => $transactionType,
							'date' => $payment->fecha ? \Carbon\Carbon::parse($payment->fecha)->format('Y-m-d') : now()->format('Y-m-d'),
							'amount' => $amount,
							'type_id' => $typeId,
							'account_id' => $accountId,
							'remarks' => $payment->observaciones ?? null,
							'status' => $payment->estado ?? 1,
							'created_at' => $payment->fecha_alta ?? now(),
							'updated_at' => $payment->fecha_modificacion ?? now(),
						]
					);

				if ($existingPayment) {
					$stats['updated']++;
				} else {
					$stats['imported']++;
				}
				$bar->advance();
			} catch (\Exception $e) {
				$skipped++;
				if ($skipped <= 10) {
					$this->newLine();
					$this->warn("     Skipped payment {$payment->id}: " . $e->getMessage());
				}
				$bar->advance();
			}
		}

		$bar->finish();
		$this->newLine();

		if ($skipped > 0) {
			$this->warn("   ⚠️  Skipped {$skipped} payments due to errors");
		}

		$this->info("✅ Imported {$stats['imported']} payments, updated {$stats['updated']}");
		} catch (\Exception $e) {
			$this->warn('⚠️  Could not import payments: ' . $e->getMessage());
		}

		return $stats;
	}

	/**
	 * Import notification types from comunicaciones_tipo
	 */
	protected function importNotificationTypes($id = null)
	{
		$this->info('🔔 Importing notification types...');

		$stats = [
			'imported' => 0,
			'updated' => 0,
			'message' => null,
		];

		try {
			$query = DB::connection('mysql_tmp')
				->table('comunicaciones_tipo')
				->where('estado', '>', 0);

			if ($id) {
				$query->where('id', $id);
			}

			$types = $query->get();

			if ($types->isEmpty()) {
				$stats['message'] = 'No notification types found.';
				return $stats;
			}

			$this->info("   Found {$types->count()} notification types to import");
			$bar = $this->output->createProgressBar($types->count());
			$bar->start();

		foreach ($types as $type) {
			try {
				$existingType = \App\Models\NotificationType::where('id', $type->id)->first();

				if (!$existingType) {
					// Insert with original ID
					DB::table('notification_types')->insert([
						'id' => $type->id,
						'name' => $type->tipo,
						'template_subject' => null,
						'template_body' => null,
						'is_customizable' => true,
						'is_active' => $type->estado > 0,
						'created_at' => now(),
						'updated_at' => now(),
					]);
					$stats['imported']++;
				} else {
					// Update existing
					DB::table('notification_types')
						->where('id', $type->id)
						->update([
							'name' => $type->tipo,
							'is_active' => $type->estado > 0,
							'updated_at' => now(),
						]);
					$stats['updated']++;
				}

				$bar->advance();
			} catch (\Exception $e) {
				// Skip on error
				$bar->advance();
				continue;
			}
		}

			$bar->finish();
			$this->newLine();
			$this->info("✅ Imported {$stats['imported']} notification types, updated {$stats['updated']}");

		} catch (\Exception $e) {
			$this->newLine();
			throw new \Exception('Error importing notification types: ' . $e->getMessage());
		}

		return $stats;
	}

	/**
	 * Import communications as notifications
	 */
	protected function importCommunications($id = null)
	{
		$this->info('🔔 Importing Notifications...');

		$stats = [
			'imported' => 0,
			'updated' => 0,
			'skipped' => 0,
			'message' => null,
		];

		try {
			$query = DB::connection('mysql_tmp')
				->table('comunicaciones')
				->where('grupo', env('CMS_GROUP'))
				->where('estado', '>', 0);

			if ($id) {
				$query->where('id', $id);
			}

			$communications = $query->get();

			if ($communications->isEmpty()) {
				$stats['message'] = 'No communications found.';
				return $stats;
			}

		$this->info("   Found {$communications->count()} notifications to import");
		$bar = $this->output->createProgressBar($communications->count());
		$bar->start();

		// Get user ID from team once (default to first user in team 2)
		$userId = \App\Models\User::whereHas('teams', function($q) {
			$q->where('teams.id', 2);
		})->first()->id ?? 1;

		foreach ($communications as $comm) {
			try {
				// Verificar si el contacto existe, si no existe usar NULL
				$contactId = null;
				if ($comm->id_contacto && DB::table('contacts')->where('id', $comm->id_contacto)->exists()) {
					$contactId = $comm->id_contacto;
				}

				// Si no tiene contact_id válido, registrar en skipped pero continuar
				if (!$contactId) {
					$stats['skipped']++;
					$bar->advance();
					continue;
				}

				$existingNotification = \App\Models\Notification::withoutGlobalScope('team')->where('id', $comm->id)->first();

				\App\Models\Notification::withoutGlobalScope('team')->updateOrCreate(
					['id' => $comm->id],
					[
						'team_id' => 2, // REVISION ALPHA team
						'type_id' => $comm->id_tipo ?? 1,
						'contact_id' => $contactId,
						'user_id' => $userId,
						'reference' => $comm->id_referencia ?? null,
						'subject' => $comm->asunto ?? 'Sin asunto',
						'message' => $comm->data ?? '',
						'is_sent' => $comm->enviado ? true : false,
						'sent_at' => $comm->enviado ? now() : null,
						'is_read' => $comm->recibido ? true : false,
						'read_at' => $comm->recibido ? now() : null,
						'metadata' => json_encode([
							'vinculo' => $comm->vinculo ?? null,
							'debug' => $comm->debug ?? null,
							'estado' => $comm->estado ?? 1,
						]),
						'created_at' => now(),
						'updated_at' => now(),
					]
				);

				if ($existingNotification) {
					$stats['updated']++;
				} else {
					$stats['imported']++;
				}

				$bar->advance();
			} catch (\Exception $e) {
				$stats['skipped']++;
				if ($stats['skipped'] <= 10) {
					$this->newLine();
					$this->warn("     Skipped notification {$comm->id}: " . $e->getMessage());
				}
				$bar->advance();
				continue;
			}
		}

		$bar->finish();
		$this->newLine();

		if ($stats['skipped'] > 0) {
			$this->warn("   ⚠️  Skipped {$stats['skipped']} notifications (contacts not found)");
		}

		$this->info("✅ Imported {$stats['imported']} notifications, updated {$stats['updated']}");

		} catch (\Exception $e) {
			$this->newLine();
			throw new \Exception('Error importing communications: ' . $e->getMessage());
		}

		return $stats;
	}

	/**
	 * Import products with team selection
	 */
	protected function importProductsWithTeam($id = null)
	{
		// Ask for team ID
		$teamId = $this->ask('Enter the Team ID for the products (default: 1)', '1');

		if (!is_numeric($teamId) || $teamId < 1) {
			$this->error('❌ Invalid Team ID. Must be a positive number.');

			return ['imported' => 0, 'updated' => 0, 'message' => 'Invalid Team ID'];
		}

		// Verify team exists
		$team = \App\Models\Team::find($teamId);
		if (!$team) {
			$this->error("❌ Team with ID {$teamId} not found.");

			return ['imported' => 0, 'updated' => 0, 'message' => 'Team not found'];
		}

		$this->info("📋 Importing products for Team: {$team->name} (ID: {$teamId})");

		return $this->importProducts($id, $teamId);
	}

	/**
	 * Import products from CMS7 categorias_generales table
	 */
	protected function importProducts($id = null, $teamId = 1)
	{
		$stats = [
			'categories_imported' => 0,
			'categories_updated' => 0,
			'products_imported' => 0,
			'products_updated' => 0,
			'imported' => 0,
			'updated' => 0,
			'message' => null,
		];

		try {
			$this->info('🛍️ Starting products and categories import from CMS7...');

			// Step 1: Import parent categories (padre IS NULL)
			$this->info('📂 Step 1: Importing categories...');
			$categoryStats = $this->importCategoriesFromCMS7($id, $teamId);
			$stats['categories_imported'] = $categoryStats['imported'];
			$stats['categories_updated'] = $categoryStats['updated'];

			// Step 2: Import child products (padre IS NOT NULL)
			$this->info('📦 Step 2: Importing products...');
			$productStats = $this->importProductsFromCMS7($id, $teamId);
			$stats['products_imported'] = $productStats['imported'];
			$stats['products_updated'] = $productStats['updated'];

			// Total stats for compatibility
			$stats['imported'] = $stats['categories_imported'] + $stats['products_imported'];
			$stats['updated'] = $stats['categories_updated'] + $stats['products_updated'];

			$this->info('✅ Import completed:');
			$this->info("   📂 Categories: {$stats['categories_imported']} imported, {$stats['categories_updated']} updated");
			$this->info("   📦 Products: {$stats['products_imported']} imported, {$stats['products_updated']} updated");
		} catch (\Exception $e) {
			$this->newLine();
			throw new \Exception('Error importing products: ' . $e->getMessage());
		}

		return $stats;
	}

	/**
	 * Import categories from CMS7 (parent items where padre IS NULL)
	 */
	private function importCategoriesFromCMS7($id = null, $teamId = 1)
	{
		$stats = [
			'imported' => 0,
			'updated' => 0,
		];

		$query = DB::connection('mysql_tmp')
			->table('categorias_generales')
			->where('grupo', env('CMS_GROUP'))
			->whereNull('padre')  // Only parent categories
			->whereIn('estado', [1, 2])  // Include active states 1 and 2
			->select('id', 'categoria', 'descripcion', 'caracteristicas', 'fecha_alta', 'fecha_modificacion');

		if ($id) {
			$query->where('id', $id);
		}

		$categories = $query->get();

		if ($categories->isEmpty()) {
			return $stats;
		}

		$this->info('📂 Found ' . count($categories) . ' categories to import');

		$bar = $this->output->createProgressBar(count($categories));
		$bar->start();

		foreach ($categories as $cms7Category) {
			try {
				// Check if category already exists
				$existingCategory = Category::where('team_id', $teamId)
					->where('name', $cms7Category->categoria)
					->first();

				$categoryData = [
					'team_id' => $teamId,
					'name' => $cms7Category->categoria,
					'description' => $this->buildCategoryDescription($cms7Category),
					'status' => true,
					'created_at' => $cms7Category->fecha_alta ?? now(),
					'updated_at' => $cms7Category->fecha_modificacion ?? now(),
				];

				if (!$existingCategory) {
					Category::create($categoryData);
					$stats['imported']++;
					$this->info("✅ Imported category: {$cms7Category->categoria}");
				} else {
					$existingCategory->update($categoryData);
					$stats['updated']++;
					$this->info("🔄 Updated category: {$cms7Category->categoria}");
				}
			} catch (\Exception $e) {
				$this->error("❌ Error importing category {$cms7Category->categoria}: " . $e->getMessage());
			}

			$bar->advance();
		}

		$bar->finish();
		$this->newLine();

		return $stats;
	}

	/**
	 * Import products from CMS7 (child items where padre IS NOT NULL)
	 */
	private function importProductsFromCMS7($id = null, $teamId = 1)
	{
		$stats = [
			'imported' => 0,
			'updated' => 0,
		];

		$query = DB::connection('mysql_tmp')
			->table('categorias_generales')
			->where('grupo', env('CMS_GROUP'))
			->whereNotNull('padre')  // Only child products
			->whereIn('estado', [1, 2])  // Include active states 1 and 2
			->select('id', 'categoria', 'descripcion', 'caracteristicas', 'valor', 'id_moneda', 'padre', 'estado', 'fecha_alta', 'fecha_modificacion', 'username_alta');

		if ($id) {
			$query->where('id', $id);
		}

		$products = $query->get();

		if ($products->isEmpty()) {
			return $stats;
		}

		$this->info('📦 Found ' . count($products) . ' products to import');

		$bar = $this->output->createProgressBar(count($products));
		$bar->start();

		foreach ($products as $cms7Product) {
			try {
				$result = $this->importSingleProduct($cms7Product, $teamId);

				if ($result === 'imported') {
					$stats['imported']++;
					$this->info("✅ Imported: {$cms7Product->categoria}");
				} elseif ($result === 'updated') {
					$stats['updated']++;
					$this->info("🔄 Updated: {$cms7Product->categoria}");
				}
			} catch (\Exception $e) {
				$this->error("❌ Error importing {$cms7Product->categoria}: " . $e->getMessage());
			}

			$bar->advance();
		}

		$bar->finish();
		$this->newLine();

		return $stats;
	}

	/**
	 * Build category description from CMS7 data
	 */
	private function buildCategoryDescription($cms7Category)
	{
		$description = $cms7Category->descripcion ?: '';

		if ($cms7Category->caracteristicas) {
			if ($description) {
				$description .= "\n\n" . $cms7Category->caracteristicas;
			} else {
				$description = $cms7Category->caracteristicas;
			}
		}

		return $description ?: "Categoría importada desde CMS7 - ID: {$cms7Category->id}";
	}

	/**
	 * Import a single product
	 */
	private function importSingleProduct($cms7Product, $teamId = 1)
	{
		// Check if product already exists
		$existingProduct = Product::where('team_id', $teamId)
			->where('name', $cms7Product->categoria)
			->first();

		// Get or create currency
		$currency = $this->getCurrencyForProduct($cms7Product);

		// Get category based on parent (padre) from CMS7
		$category = $this->getCategoryForProduct($teamId, $cms7Product);

		// Create the product data
		$productData = [
			'team_id' => $teamId,
			'name' => $cms7Product->categoria,
			'description' => $this->buildProductDescription($cms7Product),
			'price' => $cms7Product->valor ?? 0.0,
			'currency_id' => $currency->id,
			'category_id' => $category->id,
			'status' => $cms7Product->estado == 1,
			'whatsapp_enabled' => true,  // Enable for WhatsApp by default
			'created_at' => $cms7Product->fecha_alta ?? now(),
			'updated_at' => $cms7Product->fecha_modificacion ?? now(),
		];

		if (!$existingProduct) {
			Product::create($productData);

			return 'imported';
		} else {
			$existingProduct->update($productData);

			return 'updated';
		}
	}

	/**
	 * Get currency for product based on CMS7 id_moneda
	 */
	private function getCurrencyForProduct($cms7Product)
	{
		// Map CMS7 currency IDs to our currency codes
		$currencyMap = [
			1 => 'USD',  // Assuming 1 = USD
			2 => 'EUR',  // Assuming 2 = EUR
			3 => 'ARS',  // Assuming 3 = ARS
			// Add more mappings as needed
		];

		$currencyCode = $currencyMap[$cms7Product->id_moneda] ?? 'USD';

		$currency = Currency::where('code', $currencyCode)->first();

		if (!$currency) {
			// Fallback to USD if currency not found
			$currency = Currency::where('code', 'USD')->first();

			if (!$currency) {
				// Create USD if it doesn't exist
				$currency = Currency::create([
					'id' => 840,  // ISO code for USD
					'code' => 'USD',
					'name' => 'US Dollar',
					'symbol' => '$',
					'status' => true,
				]);
			}
		}

		return $currency;
	}

	/**
	 * Get category for imported products based on CMS7 parent (padre)
	 */
	private function getCategoryForProduct($teamId, $cms7Product)
	{
		// If product has a parent, find the parent category
		if (isset($cms7Product->padre) && $cms7Product->padre) {
			// Get parent category from CMS7
			$parentCategory = DB::connection('mysql_tmp')
				->table('categorias_generales')
				->where('id', $cms7Product->padre)
				->first();

			if ($parentCategory) {
				// Find the corresponding category in our system
				$category = Category::where('team_id', $teamId)
					->where('name', $parentCategory->categoria)
					->first();

				if ($category) {
					return $category;
				}
			}
		}

		// Fallback: Create or get default category
		$category = Category::where('team_id', $teamId)
			->where('name', 'Productos CMS7')
			->first();

		if (!$category) {
			$category = Category::create([
				'team_id' => $teamId,
				'name' => 'Productos CMS7',
				'description' => 'Productos importados desde CMS7 sin categoría padre específica',
				'status' => true,
			]);
		}

		return $category;
	}

	/**
	 * Build product description from CMS7 data
	 */
	private function buildProductDescription($cms7Product)
	{
		$description = $cms7Product->descripcion ?? '';

		if (!empty($cms7Product->caracteristicas)) {
			if (!empty($description)) {
				$description .= "\n\n";
			}
			$description .= "Características:\n" . $cms7Product->caracteristicas;
		}

		// Add import metadata
		$description .= "\n\n[Importado desde CMS7 - ID: {$cms7Product->id}]";

		return $description ?: $cms7Product->categoria;
	}

	/**
	 * Ensure product categories exist
	 */
	private function ensureProductCategories($teamId = 1)
	{
		$categories = [
			[
				'name' => 'Productos CMS7',
				'description' => 'Productos importados desde CMS7',
			],
			[
				'name' => 'E-commerce',
				'description' => 'Productos para e-commerce',
			],
		];

		foreach ($categories as $categoryData) {
			$existing = Category::where('team_id', $teamId)
				->where('name', $categoryData['name'])
				->first();

			if (!$existing) {
				Category::create([
					'team_id' => $teamId,
					'name' => $categoryData['name'],
					'description' => $categoryData['description'],
					'status' => true,
				]);
				$this->info("✅ Created category: {$categoryData['name']}");
			}
		}
	}

	// Add other import methods...
}
