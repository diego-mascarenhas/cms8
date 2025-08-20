<?php

namespace App\Console\Commands;

use App\Helpers\PhoneHelper;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ImportDataCommand extends Command
{
	protected $signature = 'import:interactive {--auto : Run automatic import of enterprises and contacts}';

	protected $description = 'Interactive menu for importing data from old database';

	protected function testDatabaseConnection()
	{
		$this->info('Testing database connections...');

		try
		{
			// Test local database connection
			DB::connection()->getPdo();
			$this->info('✓ Local database connection successful: '.DB::connection()->getDatabaseName());

			// Test remote database connection
			DB::connection('mysql_tmp')->getPdo();
			$this->info('✓ Remote database connection successful: '.DB::connection('mysql_tmp')->getDatabaseName());

			return true;
		} catch (Exception $e)
		{
			$this->error('Database connection failed!');
			$this->error('Error: '.$e->getMessage());

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

			if ($this->confirm('Would you like to retry the connection?'))
			{
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
			10 => '10. Communications',
			11 => '11. Products (CMS7)',
			12 => '12. Import All',
			13 => '13. Exit',
		]);
	}

	protected function showSubMenu($type)
	{
		while (true)
		{
			$choice = $this->choice("Select action for $type:", [
				1 => '1. Preview All',
				2 => '2. Preview Specific ID',
				3 => '3. Import All',
				4 => '4. Import Specific ID',
				5 => '5. Back to Main Menu',
			]);

			if ($choice === '5. Back to Main Menu')
			{
				return;
			}

			$id = null;
			if (in_array($choice, ['2. Preview Specific ID', '4. Import Specific ID']))
			{
				$id = $this->ask('Enter the ID');
			}

			switch ($choice)
			{
				case '1. Preview All':
				case '2. Preview Specific ID':
					$this->previewData($type, $id);
					break;
				case '3. Import All':
				case '4. Import Specific ID':
					if ($this->confirm('Are you sure you want to import this data?'))
					{
						$this->processImport($type, $id);
					}
					break;
			}
		}
	}

	protected function previewData($type, $id = null)
	{
		$this->info("Previewing $type data...");

		try
		{
			$data = $this->getData($type, $id);

			if ($data->isEmpty())
			{
				$this->warn('No data found!');

				return;
			}

			// Show preview in table format
			$headers = array_keys((array) $data->first());
			$rows = $data->map(function ($item)
			{
				return (array) $item;
			})->toArray();

			$this->table($headers, $rows);

			$this->info('Total records: '.$data->count());
		} catch (\Exception $e)
		{
			$this->error('Error previewing data: '.$e->getMessage());
		}
	}

	protected function getData($type, $id = null)
	{
		$query = match ($type)
		{
			'1. Users' => DB::connection('mysql_tmp')->table('contactos')
				->whereNotNull('email')
				->where('grupo', env('CMS_GROUP'))
				->whereNotNull('id_empresa')
				->where('area_privada', '!=', 6)
				->where('id', '>', 2)
				->whereNotNull('nombre')
				->where('nombre', '!=', '')
				->whereRaw("TRIM(nombre) != ''")
				->select('id', 'email', 'nombre', 'apellido', 'estado', 'id_empresa', 'area_privada', 'telefono', 'celular', 'fecha_alta', 'fecha_modificacion'),

			'2. Categories' => DB::connection('mysql_tmp')->table('categorias_generales')
				->where('grupo', env('CMS_GROUP'))
				->where('padre', 10)
				->select('id', 'categoria', 'padre', 'estado'),

			'5. Enterprises' => DB::connection('mysql_tmp')->table('empresas')
				->where('grupo', env('CMS_GROUP'))
				->select('id', 'empresa', 'id_categoria', 'telefono', 'email', 'estado', 'fecha_modificacion'),

			'6. Services' => DB::connection('mysql_tmp')->table('servicios')
				->join('servicios_hosting', 'servicios.id', '=', 'servicios_hosting.id_servicio')
				->where('servicios.grupo', env('CMS_GROUP'))
				->where('servicios.estado', '>', 0)
				->where('servicios.operacion', 'V')
				->select('servicios.*', 'servicios_hosting.*'),

			'7. Projects' => DB::connection('mysql_tmp')->table('proyectos')
				->where('grupo', env('CMS_GROUP'))
				->select('id', 'nombre', 'id_empresa', 'estado'),

			'8. Invoices' => DB::connection('mysql_tmp')->table('facturas')
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

			'9. Payments' => DB::connection('mysql_tmp')->table('pagos')
				->where('grupo', env('CMS_GROUP'))
				->select('id', 'id_empresa', 'id_forma_pago', 'estado'),

			'10. Communications' => DB::connection('mysql_tmp')->table('comunicaciones')
				->where('grupo', env('CMS_GROUP'))
				->select('id', 'id_empresa', 'id_comunicacion_tipo', 'estado'),

			'11. Products (CMS7)' => DB::connection('mysql_tmp')->table('categorias_generales')
				->where('grupo', env('CMS_GROUP'))
				->whereNull('padre')
				->where('estado', 1)
				->select('id', 'categoria', 'descripcion', 'caracteristicas', 'valor', 'id_moneda', 'estado', 'fecha_alta'),

			default => throw new \Exception('Invalid type selected'),
		};

		if ($id)
		{
			if ($type === '8. Invoices')
			{
				$query->where('facturas.id', $id);
			} elseif ($type === '6. Services')
			{
				$query->where('servicios.id', $id);
			} else
			{
				$query->where('id', $id);
			}
		}

		return $query->limit(10)->get(); // Limit preview to 10 records
	}

	public function handle()
	{
		$this->info('=== Database Import Tool ===');

		// Test database connection first
		if (! $this->testDatabaseConnection())
		{
			$this->error('Exiting due to database connection failure.');

			return 1;
		}

		if ($this->option('auto'))
		{
			$this->info('Running in automatic mode: importing enterprises and contacts...');
			$this->processImport('5. Enterprises');
			$this->processImport('1. Users');
			$this->info('Automatic import completed.');

			return 0;
		}

		while (true)
		{
			$choice = $this->showMainMenu();

			if ($choice === '13. Exit')
			{
				$this->info('Goodbye!');
				break;
			}

			if ($choice === '12. Import All')
			{
				if ($this->confirm('Are you sure you want to import ALL data?'))
				{
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

		try
		{
			$result = match ($type)
			{
				'1. Users' => $this->importUsers($id),
				'2. Categories' => $this->importCategories($id),
				'5. Enterprises' => $this->importEnterprises($id),
				'6. Services' => $this->importServices($id),
				'7. Projects' => $this->importProjects($id),
				'8. Invoices' => $this->importInvoices($id),
				'9. Payments' => $this->importPayments($id),
				'10. Communications' => $this->importCommunications($id),
				'11. Products (CMS7)' => $this->importProductsWithTeam($id),
				default => throw new \Exception('Invalid type selected'),
			};

			if ($result['imported'] === 0)
			{
				$this->warn('No records were imported.');
				if (isset($result['message']))
				{
					$this->line($result['message']);
				}
			} else
			{
				$this->info("Successfully imported {$result['imported']} records.");
				if (isset($result['updated']))
				{
					$this->info("Updated {$result['updated']} existing records.");
				}

				// Mostrar estadísticas de usuarios si están disponibles
				if (isset($result['users_created']))
				{
					$this->info("Users created: {$result['users_created']}");
					$this->info("Users existing: {$result['users_existing']}");
					$this->info("Users skipped: {$result['users_skipped']}");
				}
			}
		} catch (\Exception $e)
		{
			$this->error('Error during import: '.$e->getMessage());
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

		try
		{
			$query = DB::connection('mysql_tmp')->table('contactos')
				->whereNotNull('email')
				->where('grupo', env('CMS_GROUP'))
				->whereNotNull('id_empresa')
				->where('area_privada', '!=', 6)
				->where('id', '>', 2)
				->whereNotNull('nombre')
				->where('nombre', '!=', '')
				->whereRaw("TRIM(nombre) != ''")
				->select('id', 'email', 'nombre', 'apellido', 'estado', 'id_empresa', 'area_privada', 'telefono', 'celular', 'fecha_alta', 'fecha_modificacion');

			if ($id)
			{
				$query->where('id', $id);
			}

			$contacts = $query->get();

			if ($contacts->isEmpty())
			{
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

			foreach ($contacts as $data)
			{
				$existingContact = DB::table('contacts')->where('id', $data->id)->first();

				$phone = $data->celular ?? $data->telefono ?? null;
				$cleaned_phone = PhoneHelper::clean($phone, '54', true);

				// Determinar status_id según el estado de la empresa
				$statusId = 5;
				if (! empty($data->id_empresa))
				{
					$enterprise = DB::table('enterprises')->where('id', $data->id_empresa)->first();
					if ($enterprise && $enterprise->status_id == 1)
					{
						$statusId = 6;
					}
				}

				// Crear usuario si corresponde según area_privada
				$userId = null;
				$shouldCreateUser = in_array($data->area_privada, [2, 3, 4]); // 2=admin, 3=client, 4=user

				if ($shouldCreateUser && $data->email)
				{
					// Mapear area_privada a roles
					$roleMapping = [
						2 => 'admin',
						3 => 'client',
						4 => 'user',
					];

					$roleName = $roleMapping[$data->area_privada];

					// Verificar si ya existe un usuario con este email
					$existingUser = User::where('email', $data->email)->first();

					if (! $existingUser)
					{
						try
						{
						    // Crear nuevo usuario
						    $user = User::create([
						        'name' => trim($data->nombre.' '.($data->apellido ?? '')),
						        'email' => $data->email,
						        'phone' => $cleaned_phone,
						        'password' => Hash::make('Simplicity!'), // Password temporal
						        'email_verified_at' => now(),
						        'created_at' => $data->fecha_alta,
						        'updated_at' => $data->fecha_modificacion,
						    ]);

						    // Asignar rol
						    $user->assignRole($roleName);

						    // Asignar al equipo CMS
						    $teamId = env('CMS_TEAM_ID');
						    if ($teamId)
						    {
						        $user->teams()->attach($teamId, ['role' => $roleName]);
						    }

						    $userId = $user->id;
						    $stats['users_created']++;
						    $this->info("Usuario creado: {$data->email} con rol {$roleName} (ID: {$userId})");
						} catch (\Exception $e)
						{
						    $stats['users_skipped']++;
						    $this->error("Error creando usuario {$data->email}: ".$e->getMessage());
						}
					} else
					{
						$userId = $existingUser->id;
						$stats['users_existing']++;
						$this->info("Usuario existente encontrado: {$data->email} (ID: {$userId})");
					}
				} else
				{
					$stats['users_skipped']++;
					if (! $shouldCreateUser)
					{
						$this->info("Contacto {$data->id} - area_privada={$data->area_privada} no requiere usuario");
					} else
					{
						$this->warn("Contacto {$data->id} - sin email, no se puede crear usuario");
					}
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

				if (! $existingContact)
				{
					DB::table('contacts')->insert($contactData);
					$stats['imported']++;
				} else
				{
					// Merge existing data with new imported_from_cms7 flag
					$existingData = json_decode($existingContact->data ?? '{}', true);
					if (! is_array($existingData))
					{
						$existingData = [];
					}
					$existingData['imported_from_cms7'] = true;
					$contactData['data'] = json_encode($existingData);
					DB::table('contacts')->where('id', $existingContact->id)->update($contactData);
					$stats['updated']++;
				}

				// Añadir la relación con la empresa si existe id_empresa
				if (! empty($data->id_empresa))
				{
					// Verificar si existe la empresa
					$enterpriseExists = DB::table('enterprises')->where('id', $data->id_empresa)->exists();

					if ($enterpriseExists)
					{
						// Determinar la posición basada en area_privada
						$position = 'Usuario'; // Default position
						$departmentId = null; // Default department
						switch ($data->area_privada)
						{
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

						if (! $relationExists)
						{
						    // Crear la relación
						    DB::table('contact_enterprise')->insert([
						        'contact_id' => $data->id,
						        'enterprise_id' => $data->id_empresa,
						        'position' => $position,
						        'department_id' => $departmentId,
						        'created_at' => now(),
						        'updated_at' => now(),
						    ]);
						    $this->info("Added relationship between contact {$data->id} and enterprise {$data->id_empresa} as {$position}");
						} else
						{
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
					} else
					{
						$this->warn("Enterprise with ID {$data->id_empresa} not found, skipping relationship for contact {$data->id}");
					}
				}

				// Al final de la importación de cada contacto:
				if ($importedCategoryId)
				{
					$exists = DB::table('contact_category')
						->where('contact_id', $data->id)
						->where('category_id', $importedCategoryId)
						->exists();
					if (! $exists)
					{
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
		} catch (\Exception $e)
		{
			$this->newLine();
			throw new \Exception('Error importing contacts: '.$e->getMessage());
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

		try
		{
			$query = DB::connection('mysql_tmp')->table('empresas')
				->where('grupo', env('CMS_GROUP'));

			if ($id)
			{
				$query->where('id', $id);
			}

			$enterprises = $query->get();

			if ($enterprises->isEmpty())
			{
				$stats['message'] = 'No records found matching the criteria.';

				return $stats;
			}

			$bar = $this->output->createProgressBar(count($enterprises));
			$bar->start();

			foreach ($enterprises as $data)
			{
				$existingEnterprise = DB::table('enterprises')->where('id', $data->id)->first();

				if ($data->id_categoria == 3 || $data->id_categoria == 463)
				{
					$type_id = 2; // Supplier
				} elseif ($data->id_categoria == 100 || $data->id_categoria == 464)
				{
					$type_id = 3; // Partnership
				} else
				{
					$type_id = 1; // Client
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
					'status_id' => ($data->estado == 2) ? 2 : 1,
					'created_at' => $data->fecha_alta,
					'updated_at' => $data->fecha_modificacion,
					'deleted_at' => ($data->estado != 2) ? $data->fecha_modificacion : null,
					'team_id' => env('CMS_TEAM_ID'),
				];

				if (! $existingEnterprise)
				{
					DB::table('enterprises')->insert($enterpriseData);
					$stats['imported']++;
				} else
				{
					DB::table('enterprises')->where('id', $existingEnterprise->id)->update($enterpriseData);
					$stats['updated']++;
				}

				$bar->advance();
			}

			$bar->finish();
			$this->newLine();
		} catch (\Exception $e)
		{
			$this->newLine();
			throw new \Exception('Error importing enterprises: '.$e->getMessage());
		}

		return $stats;
	}

	protected function importServices($id = null)
	{
		$stats = [
			'imported' => 0,
			'updated' => 0,
			'message' => null,
		];

		try
		{
			// Buscar el módulo de servicios
			$serviceModule = \App\Models\Module::where('key', 'services')->first();

			if (! $serviceModule)
			{
				throw new \Exception("El módulo 'services' no existe. Ejecute primero el seeder de módulos.");
			}

			$query = DB::connection('mysql_tmp')
				->table('servicios')
				->join('servicios_hosting', 'servicios.id', '=', 'servicios_hosting.id_servicio')
				->where('servicios.grupo', env('CMS_GROUP'))
				->where('servicios.estado', '>', 0)
				->where('servicios.operacion', 'V') // Solo importar servicios de venta
				->select('servicios.*', 'servicios_hosting.*');

			if ($id)
			{
				$query->where('servicios.id', $id);
			}

			$services = $query->get();

			if ($services->isEmpty())
			{
				$stats['message'] = 'No services found matching the criteria.';

				return $stats;
			}

			$bar = $this->output->createProgressBar(count($services));
			$bar->start();

			// Pre-cargar empresas existentes en un array para verificación más rápida
			$enterpriseIds = $services->pluck('id_empresa')->unique()->toArray();
			$existingEnterprises = DB::table('enterprises')->whereIn('id', $enterpriseIds)->pluck('id')->toArray();

			$this->info('Verificando '.count($enterpriseIds).' empresas...');
			$this->info('Encontradas '.count($existingEnterprises).' empresas existentes');

			foreach ($services as $data)
			{
				$existingService = DB::table('services')->where('id', $data->id)->first();

				// Verificar si existe la empresa
				$enterpriseExists = in_array($data->id_empresa, $existingEnterprises);
				if (! $enterpriseExists)
				{
					$this->warn("Enterprise with ID {$data->id_empresa} not found, skipping service {$data->id}");
					$bar->advance();

					continue;
				}

				// Verificar si existe la categoría o asignar una predeterminada (4000)
				$categoryId = 4000; // Categoría predeterminada
				$categoryExists = DB::table('categories')
					->where('id', $data->id_categoria)
					->exists();

				if ($categoryExists)
				{
					// Si la categoría existe, verificamos que tenga el module_id del módulo de servicios
					$category = DB::table('categories')->where('id', $data->id_categoria)->first();

					if (! $category->module_id)
					{
						// Si no tiene module_id, actualizamos la categoría
						DB::table('categories')
						    ->where('id', $data->id_categoria)
						    ->update(['module_id' => $serviceModule->id]);

						$this->info("Categoría {$data->id_categoria} actualizada con module_id {$serviceModule->id}");
					}

					$categoryId = $data->id_categoria;
				} else
				{
					$this->warn("Categoría con ID {$data->id_categoria} no encontrada, asignando categoría predeterminada 4000 para el servicio {$data->id}");
				}

				$cleaned_description = strip_tags($data->descripcion);

				// Crear un array con todos los campos de servicios_hosting
				$hostingData = [];
				foreach ((array) $data as $key => $value)
				{
					// Si es un campo de servicios_hosting (no está en la tabla principal de servicios)
					// El formato puede ser 'servicios_hosting.campo' o simplemente 'campo' dependiendo del driver
					if (strpos($key, 'servicios_hosting.') === 0 ||
						! in_array($key, ['id', 'id_empresa', 'id_categoria', 'descripcion', 'valor',
						    'frecuencia', 'operacion', 'estado', 'fecha_alta',
						    'fecha_modificacion', 'ultima', 'proxima', 'caduca',
						    'id_moneda', 'descuento']))
					{
						// Quitar el prefijo si existe
						$cleanKey = str_replace('servicios_hosting.', '', $key);

						// Si el campo es 'data' y es un JSON válido, lo decodificamos para evitar doble codificación
						if ($cleanKey === 'data' && $value && is_string($value) && $this->isJson($value))
						{
						    $decodedData = json_decode($value, true);
						    // Mezclamos los datos decodificados con el array principal
						    if (is_array($decodedData))
						    {
						        foreach ($decodedData as $dataKey => $dataValue)
						        {
						            $hostingData[$dataKey] = $dataValue;
						        }
						    }
						} else
						{
						    $hostingData[$cleanKey] = $value;
						}
					}
				}

				// Mostrar los datos para depuración
				// $this->info("Datos de hosting para servicio {$data->id}: " . json_encode($hostingData));

				$serviceData = [
					'id' => $data->id,
					'category_id' => $categoryId,
					'enterprise_id' => $data->id_empresa,
					'descriptiontion' => 'Sell', // Siempre será Sell ya que filtramos por 'V'
					'description' => $cleaned_description, // Respetar el nombre del campo como está en la migración
					'data' => json_encode($hostingData),
					'currency_id' => $data->id_moneda,
					'price' => $data->valor,
					'discount' => $data->descuento,
					'frequency' => $data->frecuencia,
					'last_billed' => $data->ultima,
					'next_billing' => $data->proxima,
					'expires_at' => $data->caduca,
					'status' => $data->estado,
					'created_at' => $data->fecha_alta,
					'updated_at' => $data->fecha_modificacion,
				];

				try
				{
					if (! $existingService)
					{
						DB::table('services')->insert($serviceData);
						$stats['imported']++;
						$this->info("Service with ID {$data->id} imported");
					} else
					{
						DB::table('services')->where('id', $existingService->id)->update($serviceData);
						$stats['updated']++;
						$this->info("Service with ID {$data->id} updated");
					}
				} catch (\Exception $e)
				{
					$this->error("Error al importar servicio {$data->id}: ".$e->getMessage());
				}

				$bar->advance();
			}

			$bar->finish();
			$this->newLine();
		} catch (\Exception $e)
		{
			$this->newLine();
			throw new \Exception('Error importing services: '.$e->getMessage());
		}

		return $stats;
	}

	/**
	 * Importa las categorías desde el sistema antiguo
	 */
	protected function importCategories($id = null)
	{
		$stats = [
			'imported' => 0,
			'updated' => 0,
			'message' => null,
		];

		try
		{
			// Buscar el módulo de servicios para asignar a las categorías
			$serviceModule = \App\Models\Module::where('key', 'services')->first();

			if (! $serviceModule)
			{
				$this->warn("El módulo 'services' no existe. Las categorías se importarán sin módulo asignado.");
			}

			$query = DB::connection('mysql_tmp')->table('categorias_generales')
				->where('grupo', env('CMS_GROUP'))
				->where('padre', 10)
				->where('estado', '>', 0);

			if ($id)
			{
				$query->where('id', $id);
			}

			$categories = $query->get();

			if ($categories->isEmpty())
			{
				$stats['message'] = 'No se encontraron categorías para importar.';

				return $stats;
			}

			$bar = $this->output->createProgressBar(count($categories));
			$bar->start();

			foreach ($categories as $data)
			{
				$existingCategory = DB::table('categories')->where('id', $data->id)->first();

				$categoryData = [
					'id' => $data->id,
					'name' => $data->categoria,
					'module_id' => $serviceModule ? $serviceModule->id : null,
					'parent_id' => $data->padre > 0 ? $data->padre : null,
					'description' => strip_tags($data->descripcion ?? ''),
					'data' => json_encode([
						'currency_id' => $data->id_moneda ?? null,
						'price' => $data->valor ?? null,
						'discount' => $data->descuento ?? null,
						'frequency' => $data->frecuencia ?? null,
					]),
					'order' => $data->orden ?? 0,
					'status' => $data->estado ?? 1,
					'created_at' => $data->fecha_alta ?? now(),
					'updated_at' => $data->fecha_modificacion ?? now(),
				];

				if (! $existingCategory)
				{
					DB::table('categories')->insert($categoryData);
					$stats['imported']++;
					$this->info("Categoría {$data->id} importada: {$data->categoria}");
				} else
				{
					DB::table('categories')->where('id', $existingCategory->id)->update($categoryData);
					$stats['updated']++;
					$this->info("Categoría {$data->id} actualizada: {$data->categoria}");
				}

				$bar->advance();
			}

			$bar->finish();
			$this->newLine();
		} catch (\Exception $e)
		{
			$this->newLine();
			throw new \Exception('Error importando categorías: '.$e->getMessage());
		}

		return $stats;
	}

	/**
	 * Helper method to check if a string is valid JSON
	 */
	protected function isJson($string)
	{
		if (! is_string($string))
		{
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

		try
		{
			$query = DB::connection('mysql_tmp')->table('facturas')
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

			if ($id)
			{
				$query->where('facturas.id', $id);
			}

			$invoices = $query->get();

			if ($invoices->isEmpty())
			{
				$stats['message'] = 'No invoices found matching the criteria.';

				return $stats;
			}

			$bar = $this->output->createProgressBar(count($invoices));
			$bar->start();

			foreach ($invoices as $data)
			{
				$existingInvoice = DB::table('invoices')->where('id', $data->id)->first();

				// Map operation from V/C to sell/buy
				$operation = 'sell';
				if ($data->operacion === 'C')
				{
					$operation = 'buy';
				}

				// Format invoice number based on operation type
				$invoiceNumber = '1';
				if ($operation === 'sell')
				{
					// For sales invoices, format as 0000-00000000
					$talonario = str_pad($data->numero_talonario ?? 0, 4, '0', STR_PAD_LEFT);
					$numero = str_pad($data->numero_factura ?? 0, 8, '0', STR_PAD_LEFT);
					$invoiceNumber = $talonario.'-'.$numero;
				} else
				{
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
					'type_id' => 1, // Set to 1 (fixed value) since original types may not exist
					'billing_id' => null, // Set to null for now
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

				if (! $existingInvoice)
				{
					DB::table('invoices')->insert($invoiceData);
					$stats['imported']++;
				} else
				{
					DB::table('invoices')->where('id', $existingInvoice->id)->update($invoiceData);
					$stats['updated']++;
				}

				$bar->advance();
			}

			$bar->finish();
			$this->newLine();
		} catch (\Exception $e)
		{
			$this->newLine();
			throw new \Exception('Error importing invoices: '.$e->getMessage());
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

		if (! is_numeric($teamId) || $teamId < 1)
		{
			$this->error('❌ Invalid Team ID. Must be a positive number.');

			return ['imported' => 0, 'updated' => 0, 'message' => 'Invalid Team ID'];
		}

		// Verify team exists
		$team = \App\Models\Team::find($teamId);
		if (! $team)
		{
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

		try
		{
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
		} catch (\Exception $e)
		{
			$this->newLine();
			throw new \Exception('Error importing products: '.$e->getMessage());
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

		$query = DB::connection('mysql_tmp')->table('categorias_generales')
			->where('grupo', env('CMS_GROUP'))
			->whereNull('padre') // Only parent categories
			->whereIn('estado', [1, 2]) // Include active states 1 and 2
			->select('id', 'categoria', 'descripcion', 'caracteristicas', 'fecha_alta', 'fecha_modificacion');

		if ($id)
		{
			$query->where('id', $id);
		}

		$categories = $query->get();

		if ($categories->isEmpty())
		{
			return $stats;
		}

		$this->info('📂 Found '.count($categories).' categories to import');

		$bar = $this->output->createProgressBar(count($categories));
		$bar->start();

		foreach ($categories as $cms7Category)
		{
			try
			{
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

				if (! $existingCategory)
				{
					Category::create($categoryData);
					$stats['imported']++;
					$this->info("✅ Imported category: {$cms7Category->categoria}");
				} else
				{
					$existingCategory->update($categoryData);
					$stats['updated']++;
					$this->info("🔄 Updated category: {$cms7Category->categoria}");
				}
			} catch (\Exception $e)
			{
				$this->error("❌ Error importing category {$cms7Category->categoria}: ".$e->getMessage());
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

		$query = DB::connection('mysql_tmp')->table('categorias_generales')
			->where('grupo', env('CMS_GROUP'))
			->whereNotNull('padre') // Only child products
			->whereIn('estado', [1, 2]) // Include active states 1 and 2
			->select('id', 'categoria', 'descripcion', 'caracteristicas', 'valor', 'id_moneda', 'padre', 'estado', 'fecha_alta', 'fecha_modificacion', 'username_alta');

		if ($id)
		{
			$query->where('id', $id);
		}

		$products = $query->get();

		if ($products->isEmpty())
		{
			return $stats;
		}

		$this->info('📦 Found '.count($products).' products to import');

		$bar = $this->output->createProgressBar(count($products));
		$bar->start();

		foreach ($products as $cms7Product)
		{
			try
			{
				$result = $this->importSingleProduct($cms7Product, $teamId);

				if ($result === 'imported')
				{
					$stats['imported']++;
					$this->info("✅ Imported: {$cms7Product->categoria}");
				} elseif ($result === 'updated')
				{
					$stats['updated']++;
					$this->info("🔄 Updated: {$cms7Product->categoria}");
				}
			} catch (\Exception $e)
			{
				$this->error("❌ Error importing {$cms7Product->categoria}: ".$e->getMessage());
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

		if ($cms7Category->caracteristicas)
		{
			if ($description)
			{
				$description .= "\n\n".$cms7Category->caracteristicas;
			} else
			{
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
			'price' => $cms7Product->valor ?? 0.00,
			'currency_id' => $currency->id,
			'category_id' => $category->id,
			'status' => $cms7Product->estado == 1,
			'whatsapp_enabled' => true, // Enable for WhatsApp by default
			'created_at' => $cms7Product->fecha_alta ?? now(),
			'updated_at' => $cms7Product->fecha_modificacion ?? now(),
		];

		if (! $existingProduct)
		{
			Product::create($productData);

			return 'imported';
		} else
		{
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
			1 => 'USD', // Assuming 1 = USD
			2 => 'EUR', // Assuming 2 = EUR
			3 => 'ARS', // Assuming 3 = ARS
			// Add more mappings as needed
		];

		$currencyCode = $currencyMap[$cms7Product->id_moneda] ?? 'USD';

		$currency = Currency::where('code', $currencyCode)->first();

		if (! $currency)
		{
			// Fallback to USD if currency not found
			$currency = Currency::where('code', 'USD')->first();

			if (! $currency)
			{
				// Create USD if it doesn't exist
				$currency = Currency::create([
					'id' => 840, // ISO code for USD
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
		if (isset($cms7Product->padre) && $cms7Product->padre)
		{
			// Get parent category from CMS7
			$parentCategory = DB::connection('mysql_tmp')->table('categorias_generales')
				->where('id', $cms7Product->padre)
				->first();

			if ($parentCategory)
			{
				// Find the corresponding category in our system
				$category = Category::where('team_id', $teamId)
					->where('name', $parentCategory->categoria)
					->first();

				if ($category)
				{
					return $category;
				}
			}
		}

		// Fallback: Create or get default category
		$category = Category::where('team_id', $teamId)
			->where('name', 'Productos CMS7')
			->first();

		if (! $category)
		{
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

		if (! empty($cms7Product->caracteristicas))
		{
			if (! empty($description))
			{
				$description .= "\n\n";
			}
			$description .= "Características:\n".$cms7Product->caracteristicas;
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

		foreach ($categories as $categoryData)
		{
			$existing = Category::where('team_id', $teamId)
				->where('name', $categoryData['name'])
				->first();

			if (! $existing)
			{
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
