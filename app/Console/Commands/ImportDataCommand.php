<?php

namespace App\Console\Commands;

use App\Helpers\LegacyOrderNumberHelper;
use App\Helpers\LegacyTiendaPedidoEstadoHelper;
use App\Helpers\PhoneHelper;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Team;
use App\Models\User;
use App\Services\Finance\InvoiceItemLegacySyncService;
use App\Services\ProjectCategoryLegacyImportService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ImportDataCommand extends Command
{
    protected $signature = 'import:interactive
                            {--auto : Run automatic import of ALL data (categories, payment types, enterprises, payment accounts, services, projects, invoices, payments, users, stores)}
                            {--stores : Import Pedimos Facil stores into teams only}
                            {--store-id= : Import only one Pedimos Facil store by team ID (id_empresa)}
                            {--first-store : Import only the first Pedimos Facil store for validation}';

    protected $description = 'Interactive menu for importing data from old database';

    /**
     * Plain-text password for users created or reset during legacy import (tiendas, contactos CMS, etc.).
     */
    private const IMPORT_DEFAULT_USER_PASSWORD = 'Simplicity!';

    /**
     * Merge new data with existing data, preserving local values when legacy is empty
     *
     * @param  array  $newData  Data from legacy system
     * @param  object|null  $existingRecord  Existing local record
     * @param  array  $alwaysUpdate  Fields that should always be updated even if empty
     * @return array Merged data
     */
    protected function mergePreservingLocal(array $newData, $existingRecord = null, array $alwaysUpdate = []): array
    {
        if (! $existingRecord)
        {
            return $newData;
        }

        $merged = $newData;

        foreach ($newData as $key => $value)
        {
            // Skip timestamp fields and IDs
            if (in_array($key, ['id', 'created_at', 'updated_at', 'deleted_at', 'team_id']))
            {
                continue;
            }

            // If field is marked to always update, keep new value
            if (in_array($key, $alwaysUpdate))
            {
                continue;
            }

            // If new value is empty/null and existing has a value, preserve existing
            if (($value === null || $value === '' || $value === 0) && ! empty($existingRecord->$key))
            {
                $merged[$key] = $existingRecord->$key;
            }
        }

        return $merged;
    }

    protected function testDatabaseConnection()
    {
        $this->info('Testing database connections...');

        try
        {
            // Test local database connection
            app('db')->connection()->getPdo();
            $this->info('✓ Local database connection successful: '.app('db')->connection()->getDatabaseName());

            // Test remote database connection
            app('db')->connection('mysql_legacy')->getPdo();
            $this->info('✓ Remote database connection successful: '.app('db')->connection('mysql_legacy')->getDatabaseName());

            return true;
        } catch (Exception $e)
        {
            $this->error('Database connection failed!');
            $this->error('Error: '.$e->getMessage());

            // Show connection details (without sensitive data)
            $this->warn('Remote Database Configuration:');
            $config = config('database.connections.mysql_legacy');
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
            7 => '7. Project Categories',
            8 => '8. Projects',
            9 => '9. Invoices',
            10 => '10. Billing Addresses',
            11 => '11. Invoice Items',
            12 => '12. Payments',
            13 => '13. Notification Types',
            14 => '14. Communications',
            15 => '15. Products (CMS7)',
            16 => '16. Stores (Pedimos Facil -> Teams)',
            17 => '17. Import All',
            18 => '18. Exit',
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
            '1. Users' => app('db')->connection('mysql_legacy')
                ->table('contactos')
                ->whereNotNull('email')
                ->where('grupo', env('CMS_GROUP', 502))
                ->whereNotNull('id_empresa')
                ->where('area_privada', '!=', 6)
                ->where('id', '>', 2)
                ->whereNotNull('nombre')
                ->where('nombre', '!=', '')
                ->whereRaw("TRIM(nombre) != ''")
                ->select('id', 'email', 'nombre', 'apellido', 'estado', 'id_empresa', 'area_privada', 'telefono', 'celular', 'fecha_alta', 'fecha_modificacion'),

            '2. Categories' => app('db')->connection('mysql_legacy')
                ->table('categorias_generales')
                ->where('grupo', env('CMS_GROUP', 502))
                ->where('padre', 10)
                ->select('id', 'categoria', 'padre', 'estado'),

            '3. Service Types' => app('db')->connection('mysql_legacy')
                ->table('categorias_generales_tipo')
                ->select('id', 'tipo', 'descripcion', 'caracteristicas', 'id_moneda', 'valor', 'descuento', 'frecuencia', 'template_alta_de_servicio', 'orden', 'estado'),

            '4. Payment Accounts' => app('db')->connection('mysql_legacy')
                ->table('cuentas')
                ->where('grupo', env('CMS_GROUP', 502))
                ->select('id', 'nombre_cuenta', 'id_empresa', 'id_moneda', 'estado'),

            '5. Enterprises' => app('db')->connection('mysql_legacy')
                ->table('empresas')
                ->where('grupo', env('CMS_GROUP', 502))
                ->select('id', 'empresa', 'id_categoria', 'telefono', 'email', 'estado', 'fecha_modificacion'),

            '6. Services' => app('db')->connection('mysql_legacy')
                ->table('servicios')
                ->join('servicios_hosting', 'servicios.id', '=', 'servicios_hosting.id_servicio')
                ->where('servicios.grupo', env('CMS_GROUP', 502))
                ->where('servicios.estado', '>', 0)
                ->where('servicios.operacion', 'V')
                ->select('servicios.*', 'servicios_hosting.*'),

            '7. Project Categories' => app('db')->connection('mysql_legacy')
                ->table('categorias_generales')
                ->where('padre', ProjectCategoryLegacyImportService::LEGACY_PARENT_ID)
                ->where('estado', '>', 0)
                ->orderBy('orden')
                ->orderBy('categoria')
                ->select('id', 'categoria', 'padre', 'orden', 'estado'),

            '8. Projects' => app('db')->connection('mysql_legacy')
                ->table('proyectos')
                ->where('grupo', env('CMS_GROUP', 502))
                ->select('id', 'titulo', 'id_empresa', 'id_categoria', 'estado'),

            '9. Invoices' => app('db')->connection('mysql_legacy')
                ->table('facturas')
                ->join('empresas_fiscales', 'facturas.id_empresa_fiscal', '=', 'empresas_fiscales.id')
                ->where('facturas.grupo', env('CMS_GROUP', 502))
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

            '10. Billing Addresses' => app('db')->connection('mysql_legacy')
                ->table('empresas_fiscales')
                ->where('grupo', env('CMS_GROUP', 502))
                ->where('estado', 1)
                ->select('id', 'id_empresa', 'razon_social', 'cuit', 'ingresos_brutos', 'id_condicion_iva', 'domicilio', 'codigo_postal', 'localidad', 'provincia', 'pais', 'estado', 'fecha_alta', 'fecha_modificacion'),

            '11. Invoice Items' => app('db')->connection('mysql_legacy')
                ->table('facturas_items')
                ->where('grupo', env('CMS_GROUP', 502))
                ->select('id', 'id_factura', 'id_categoria', 'descripcion', 'valor', 'descuento', 'fecha_alta', 'fecha_modificacion'),

            '12. Payments' => app('db')->connection('mysql_legacy')
                ->table('pagos')
                ->where('grupo', env('CMS_GROUP', 502))
                ->select('id', 'id_empresa', 'id_forma_pago', 'estado'),

            '13. Notification Types' => app('db')->connection('mysql_legacy')
                ->table('comunicaciones_tipo')
                ->select('id', 'tipo', 'estado'),

            '14. Communications' => app('db')->connection('mysql_legacy')
                ->table('comunicaciones')
                ->where('grupo', env('CMS_GROUP', 502))
                ->select('id', 'id_contacto', 'id_tipo', 'asunto', 'estado'),

            '15. Products (CMS7)' => app('db')->connection('mysql_legacy')
                ->table('categorias_generales')
                ->where('grupo', env('CMS_GROUP', 502))
                ->whereNull('padre')
                ->where('estado', 1)
                ->select('id', 'categoria', 'descripcion', 'caracteristicas', 'valor', 'id_moneda', 'estado', 'fecha_alta'),

            '16. Stores (Pedimos Facil -> Teams)' => app('db')->connection('mysql_legacy')
                ->table('tienda_configuracion as tc')
                ->join('empresas as e', 'e.id', '=', 'tc.id_empresa')
                ->where('tc.grupo', 513)
                ->whereNotNull('tc.id_empresa')
                ->select('tc.id', 'tc.id_empresa', 'e.empresa as team_name', 'tc.titulo', 'tc.estado'),

            default => throw new \Exception('Invalid type selected'),
        };

        if ($id)
        {
            if ($type === '9. Invoices')
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

        return $query->limit(10)->get();  // Limit preview to 10 records
    }

    public function handle()
    {
        $this->info('=== Database Import Tool ===');

        // Show configuration
        $cmsGroup = env('CMS_GROUP', 502);
        $cmsTeamId = env('CMS_TEAM_ID', 2);
        $this->info('📋 Configuration:');
        $this->info("   CMS_GROUP: {$cmsGroup}");
        $this->info("   CMS_TEAM_ID: {$cmsTeamId}");
        $this->newLine();

        // Test database connection first
        if (! $this->testDatabaseConnection())
        {
            $this->error('Exiting due to database connection failure.');

            return 1;
        }

        if ($this->option('stores'))
        {
            $this->info('🏬 Running stores-only import mode...');
            $this->newLine();

            $storeIdOption = $this->option('store-id');
            if ($storeIdOption !== null && $storeIdOption !== '')
            {
                if (! is_numeric($storeIdOption))
                {
                    $this->error('Invalid --store-id value. It must be a numeric team ID (id_empresa).');

                    return 1;
                }

                $storeTeamId = (int) $storeIdOption;
                $this->info("Importing only store team_id={$storeTeamId}...");
                $this->processImport('16. Stores (Pedimos Facil -> Teams)', $storeTeamId);

                return 0;
            }

            if (! $this->input->isInteractive())
            {
                $this->info('Non-interactive mode detected. Importing all stores.');
                $this->processImport('16. Stores (Pedimos Facil -> Teams)');

                return 0;
            }

            $storeMode = $this->choice(
                'How do you want to import stores?',
                [
                    'All stores',
                    'One store by team ID (id_empresa)',
                ],
                'All stores',
            );

            if ($storeMode === 'One store by team ID (id_empresa)')
            {
                $storeTeamIdInput = $this->ask('Enter team ID (id_empresa)');
                if (! is_numeric($storeTeamIdInput))
                {
                    $this->error('Invalid team ID. It must be numeric.');

                    return 1;
                }

                $storeTeamId = (int) $storeTeamIdInput;
                $this->info("Importing only store team_id={$storeTeamId}...");
                $this->processImport('16. Stores (Pedimos Facil -> Teams)', $storeTeamId);
            } else
            {
                $this->processImport('16. Stores (Pedimos Facil -> Teams)');
            }

            return 0;
        }

        if ($this->option('first-store'))
        {
            $this->info('🧪 Running first-store validation import mode...');
            $this->newLine();

            $firstStoreTeamId = app('db')->connection('mysql_legacy')
                ->table('tienda_configuracion')
                ->where('grupo', 513)
                ->whereNotNull('id_empresa')
                ->orderBy('id_empresa')
                ->value('id_empresa');

            if (! $firstStoreTeamId)
            {
                $this->warn('No Pedimos Facil stores found for group 513.');

                return 0;
            }

            $this->info("Importing only first store team_id={$firstStoreTeamId}...");
            $this->processImport('16. Stores (Pedimos Facil -> Teams)', (int) $firstStoreTeamId);

            return 0;
        }

        if ($this->option('auto'))
        {
            $this->info('🚀 Running in automatic mode: importing ALL data...');
            $this->newLine();

            // Import in order to respect foreign key constraints
            $this->info('📂 Step 1/14: Importing Categories...');
            $this->processImport('2. Categories');
            $this->newLine();

            $this->info('🏢 Step 2/14: Importing Enterprises...');
            $this->processImport('5. Enterprises');
            $this->newLine();

            $this->info('🏬 Step 3/14: Importing Stores (Pedimos Facil -> Teams)...');
            $this->processImport('16. Stores (Pedimos Facil -> Teams)');
            $this->newLine();

            $this->info('📦 Step 4/14: Importing Services...');
            $this->processImport('6. Services');
            $this->newLine();

            $this->info('🏷️  Step 5/14: Importing Project Categories...');
            $this->processImport('7. Project Categories');
            $this->newLine();

            $this->info('📁 Step 6/14: Importing Projects...');
            $this->processImport('8. Projects');
            $this->newLine();

            $this->info('📋 Step 7/14: Importing Billing Addresses...');
            $this->processImport('10. Billing Addresses');
            $this->newLine();

            $this->info('📄 Step 8/14: Importing Invoices...');
            $this->processImport('9. Invoices');
            $this->newLine();

            $this->info('📝 Step 9/14: Importing Invoice Items...');
            $this->processImport('11. Invoice Items');
            $this->newLine();

            $this->info('💳 Step 10/14: Importing Payment Accounts...');
            $this->processImport('4. Payment Accounts');
            $this->newLine();

            $this->info('💰 Step 11/14: Importing Payments (linking enterprises & invoices)...');
            $this->processImport('12. Payments');
            $this->newLine();

            $this->info('👥 Step 12/14: Importing Users/Contacts...');
            $this->processImport('1. Users');
            $this->newLine();

            $this->info('🔔 Step 13/14: Importing Notification Types...');
            $this->processImport('13. Notification Types');
            $this->newLine();

            $this->info('📞 Step 14/14: Importing Notifications...');
            $this->processImport('14. Communications');
            $this->newLine();

            $this->info('✅ Automatic import completed successfully!');

            return 0;
        }

        while (true)
        {
            $choice = $this->showMainMenu();

            if ($choice === '18. Exit')
            {
                $this->info('Goodbye!');
                break;
            }

            if ($choice === '17. Import All')
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
                '3. Service Types' => $this->importServiceTypes($id),
                '4. Payment Accounts' => $this->importPaymentAccounts($id),
                '5. Enterprises' => $this->importEnterprises($id),
                '6. Services' => $this->importServices($id),
                '7. Project Categories' => $this->importProjectCategories($id),
                '8. Projects' => $this->importProjects($id),
                '9. Invoices' => $this->importInvoices($id),
                '10. Billing Addresses' => $this->importBillingAddresses($id),
                '11. Invoice Items' => $this->importInvoiceItems($id),
                '12. Payments' => $this->importPayments($id),
                '13. Notification Types' => $this->importNotificationTypes($id),
                '14. Communications' => $this->importCommunications($id),
                '15. Products (CMS7)' => $this->importProductsWithTeam($id),
                '16. Stores (Pedimos Facil -> Teams)' => $this->importStoresToTeams($id),
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
            $query = app('db')->connection('mysql_legacy')
                ->table('contactos')
                ->whereNotNull('email')
                ->where('grupo', env('CMS_GROUP', 502))
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

            // Obtener o crear la categoría 'Legacy' para el módulo de contactos y el equipo
            $contactsModuleId = app('db')->table('modules')->where('key', 'contacts')->value('id');
            $teamId = env('CMS_TEAM_ID', 2);

            // Buscar la categoría principal "Contactos" para usar como parent
            $mainContactCategory = app('db')->table('categories')
                ->where('name', 'Contactos')
                ->where('module_id', $contactsModuleId)
                ->where('team_id', $teamId)
                ->whereNull('parent_id')
                ->first();

            // Crear o obtener la categoría 'Legacy'
            $legacyCategory = Category::updateOrCreate(
                [
                    'name' => 'Legacy',
                    'module_id' => $contactsModuleId,
                    'team_id' => $teamId,
                ],
                [
                    'description' => 'Contactos importados del sistema legacy',
                    'parent_id' => $mainContactCategory?->id,
                    'status' => 1,
                ],
            );
            $importedCategoryId = $legacyCategory->id;

            foreach ($contacts as $data)
            {
                $existingContact = app('db')->table('contacts')->where('id', $data->id)->first();

                $phone = $data->celular ?? $data->telefono ?? null;
                $cleaned_phone = PhoneHelper::clean($phone, '54', true);

                // Determinar status_id según el estado de la empresa
                $statusId = 5;
                if (! empty($data->id_empresa))
                {
                    $enterprise = app('db')->table('enterprises')->where('id', $data->id_empresa)->first();
                    if ($enterprise && $enterprise->status_id == 1)
                    {
                        $statusId = 6;
                    }
                }

                // Crear usuario si corresponde según area_privada
                $userId = null;
                $shouldCreateUser = in_array($data->area_privada, [2, 3, 4]);  // 2=admin, 3=client, 4=user

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
                                'password' => Hash::make(self::IMPORT_DEFAULT_USER_PASSWORD),
                                'email_verified_at' => now(),
                                'created_at' => $data->fecha_alta,
                                'updated_at' => $data->fecha_modificacion,
                            ]);

                            // Asignar rol
                            $user->assignRole($roleName);

                            // Asignar al equipo CMS
                            $teamId = env('CMS_TEAM_ID', 2);
                            if ($teamId)
                            {
                                $user->teams()->attach($teamId, ['role' => $roleName]);
                            }

                            $userId = $user->id;
                            $stats['users_created']++;
                            // Removed verbose logging - progress bar shows overall progress
                        } catch (\Exception $e)
                        {
                            $stats['users_skipped']++;
                            // Only show errors if verbose
                        }
                    } else
                    {
                        $userId = $existingUser->id;
                        $stats['users_existing']++;
                        // User already exists, skip logging
                    }
                } else
                {
                    $stats['users_skipped']++;
                    // Contact doesn't require user account or has no email
                }

                $contactData = [
                    'id' => $data->id,
                    'team_id' => env('CMS_TEAM_ID', 2),
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
                    app('db')->table('contacts')->insert($contactData);
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

                    // Preserve local values when legacy is empty
                    $mergedData = $this->mergePreservingLocal(
                        $contactData,
                        $existingContact,
                        ['status_id', 'user_id'], // Always update status and user_id
                    );
                    app('db')->table('contacts')->where('id', $existingContact->id)->update($mergedData);
                    $stats['updated']++;
                }

                // Añadir la relación con la empresa si existe id_empresa
                if (! empty($data->id_empresa))
                {
                    // Verificar si existe la empresa
                    $enterpriseExists = app('db')->table('enterprises')->where('id', $data->id_empresa)->exists();

                    if ($enterpriseExists)
                    {
                        // Determinar la posición basada en area_privada
                        $position = 'Usuario';  // Default position
                        $departmentId = null;  // Default department
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
                        $relationExists = app('db')->table('contact_enterprise')
                            ->where('contact_id', $data->id)
                            ->where('enterprise_id', $data->id_empresa)
                            ->exists();

                        if (! $relationExists)
                        {
                            // Crear la relación
                            app('db')->table('contact_enterprise')->insert([
                                'contact_id' => $data->id,
                                'enterprise_id' => $data->id_empresa,
                                'position' => $position,
                                'department_id' => $departmentId,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            // Relationship added silently - progress bar shows overall progress
                        } else
                        {
                            // Actualizar la posición si la relación ya existe
                            app('db')->table('contact_enterprise')
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
                    $exists = app('db')->table('contact_category')
                        ->where('contact_id', $data->id)
                        ->where('category_id', $importedCategoryId)
                        ->exists();
                    if (! $exists)
                    {
                        app('db')->table('contact_category')->insert([
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

    /**
     * Import payment accounts from cuentas table
     */
    protected function importPaymentAccounts($id = null)
    {
        $this->info('💳 Importing payment accounts...');

        $stats = [
            'imported' => 0,
            'updated' => 0,
            'categories_imported' => 0,
            'categories_updated' => 0,
            'branches_imported' => 0,
            'branches_updated' => 0,
            'products_imported' => 0,
            'products_updated' => 0,
            'order_contacts_imported' => 0,
            'order_contacts_updated' => 0,
            'orders_imported' => 0,
            'orders_updated' => 0,
            'message' => null,
        ];

        try
        {
            // Currency mapping from old sys_monedas to new currencies
            // Old ID => New ID
            $currencyMap = [
                1 => 840,  // Pesos (ARG) → USD (no hay ARS, usar USD por defecto)
                2 => 840,  // Dólares → USD
                3 => 978,  // Euros → EUR
                4 => 840,  // Dolar Solidario → USD
                5 => 840,  // Dolar MEP → USD
            ];

            $query = app('db')->connection('mysql_legacy')
                ->table('cuentas')
                ->where('grupo', env('CMS_GROUP', 502))
                ->where('estado', '>', 0);

            if ($id)
            {
                $query->where('id', $id);
            }

            $accounts = $query->get();

            if ($accounts->isEmpty())
            {
                $stats['message'] = 'No payment accounts found.';

                return $stats;
            }

            $this->info("   Found {$accounts->count()} payment accounts to import");
            $bar = $this->output->createProgressBar($accounts->count());
            $bar->start();

            $skipped = 0;
            foreach ($accounts as $account)
            {
                try
                {
                    $existingAccount = app('db')->table('payment_accounts')->where('id', $account->id)->first();

                    // Generate unique code
                    $code = 'PA-'.str_pad($account->id, 6, '0', STR_PAD_LEFT);

                    // Generate account name
                    $name = $account->nombre_cuenta ?? 'Account #'.$account->id;

                    // Map currency ID from old to new
                    $oldCurrencyId = $account->id_moneda ?? 1;
                    $newCurrencyId = $currencyMap[$oldCurrencyId] ?? 840;  // Default to USD if not mapped

                    app('db')->table('payment_accounts')->insert([
                        'id' => $account->id,
                        'team_id' => 2,  // REVISION ALPHA team
                        'code' => $code,
                        'name' => $name,
                        'symbol' => null,
                        'currency_id' => $newCurrencyId,
                        'status' => $account->estado > 0 ? 1 : 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    if ($existingAccount)
                    {
                        $stats['updated']++;
                    } else
                    {
                        $stats['imported']++;
                    }

                    $bar->advance();
                } catch (\Exception $e)
                {
                    $skipped++;
                    if ($skipped <= 10)
                    {
                        $this->newLine();
                        $this->warn("     Skipped account {$account->id}: ".$e->getMessage());
                    }
                    $bar->advance();

                    continue;
                }
            }

            $bar->finish();
            $this->newLine();

            if ($skipped > 0)
            {
                $this->warn("   ⚠️  Skipped {$skipped} accounts due to errors");
            }

            $this->info("✅ Imported {$stats['imported']} payment accounts, updated {$stats['updated']}");
        } catch (\Exception $e)
        {
            $this->newLine();
            throw new \Exception('Error importing payment accounts: '.$e->getMessage());
        }

        return $stats;
    }

    protected function importEnterprises($id = null)
    {
        $stats = [
            'imported' => 0,
            'updated' => 0,
            'categories_imported' => 0,
            'categories_updated' => 0,
            'branches_imported' => 0,
            'branches_updated' => 0,
            'message' => null,
        ];

        try
        {
            $query = app('db')->connection('mysql_legacy')
                ->table('empresas')
                ->where('grupo', env('CMS_GROUP', 502));

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
                $existingEnterprise = app('db')->table('enterprises')->where('id', $data->id)->first();

                if ($data->id_categoria == 3 || $data->id_categoria == 463)
                {
                    $type_id = 2;  // Supplier
                } elseif ($data->id_categoria == 100 || $data->id_categoria == 464)
                {
                    $type_id = 3;  // Partnership
                } else
                {
                    $type_id = 1;  // Client
                }

                // Obtenemos el ID del contacto responsable
                // (Eliminado: ya no se usa responsible_id para relacionar contacto)
                // $contactId = null;
                // if (! empty($data->id_contacto)) {
                //	 // Verificamos si existe directamente en la tabla contacts
                //	 $contactExists = app('db')->table('contacts')->where('id', $data->id_contacto)->exists();

                //	 if ($contactExists) {
                //		 $contactId = $data->id_contacto;
                //		 $this->info("Found contact with ID {$contactId} for enterprise {$data->id}");
                //	 } else {
                //		 // Si no existe, lo importamos desde la base de datos original
                //		 $contactData = app('db')->connection('mysql_legacy')
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
                //					 'team_id' => env('CMS_TEAM_ID', 2),
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

                //				 app('db')->table('contacts')->insert($newContactData);
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
                    'team_id' => env('CMS_TEAM_ID', 2),
                ];

                if (! $existingEnterprise)
                {
                    app('db')->table('enterprises')->insert($enterpriseData);
                    $stats['imported']++;
                } else
                {
                    // Preserve local values when legacy is empty
                    $mergedData = $this->mergePreservingLocal(
                        $enterpriseData,
                        $existingEnterprise,
                        ['status_id', 'type_id'], // Always update status and type
                    );
                    app('db')->table('enterprises')->where('id', $existingEnterprise->id)->update($mergedData);
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

    /**
     * Importa las categorías desde el sistema antiguo (padres e hijas → categories con parent_id).
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
            $teamId = 2;
            $service = app(InvoiceItemLegacySyncService::class);

            if ($id)
            {
                $result = $service->importMissingCategoriesFromLegacy(collect([(int) $id]), $teamId);
                $stats['imported'] = $result['imported_parents'] + $result['imported_children'];
                $stats['updated'] = $result['updated_parents'] + $result['updated_children'];

                if ($stats['imported'] === 0 && $stats['updated'] === 0 && $result['missing_in_legacy'] > 0)
                {
                    $stats['message'] = 'Category not found in legacy system.';
                }

                return $stats;
            }

            $result = $service->importAllCategoriesFromLegacy($teamId);
            $stats['imported'] = $result['imported_parents'] + $result['imported_children'];
            $stats['updated'] = $result['updated_parents'] + $result['updated_children'];

            if ($result['total_legacy'] === 0)
            {
                $stats['message'] = 'No se encontraron categorías para importar.';
            } else
            {
                $this->info("📊 Total categorías legacy: {$result['total_legacy']} (padres: ".($result['imported_parents'] + $result['updated_parents']).', hijas: '.($result['imported_children'] + $result['updated_children'] + $result['skipped_children_missing_parent']).')');
                $this->info("✅ Categorías importadas: {$stats['imported']}, actualizadas: {$stats['updated']}");

                if ($result['skipped_children_missing_parent'] > 0)
                {
                    $this->warn("⚠️  Hijas omitidas por padre inexistente: {$result['skipped_children_missing_parent']}");
                }
            }
        } catch (\Exception $e)
        {
            $this->newLine();
            throw new \Exception('Error importando categorías: '.$e->getMessage());
        }

        return $stats;
    }

    /**
     * @deprecated categorias_generales_tipo is no longer imported; use categories from categorias_generales.
     */
    protected function importServiceTypes($id = null)
    {
        $this->warn('Service types table was removed. Import categories (categorias_generales) instead.');

        return [
            'imported' => 0,
            'updated' => 0,
            'message' => 'Skipped: legacy categorias_generales_tipo is not imported to a separate table.',
        ];
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
            $query = app('db')->connection('mysql_legacy')
                ->table('facturas')
                ->join('empresas_fiscales', 'facturas.id_empresa_fiscal', '=', 'empresas_fiscales.id')
                ->where('facturas.grupo', env('CMS_GROUP', 502))
                ->where('facturas.estado', '>', 0)
                ->select(
                    'facturas.id',
                    'empresas_fiscales.id_empresa as enterprise_id',
                    'facturas.id_empresa_fiscal as billing_id',
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
                $existingInvoice = app('db')->table('invoices')->where('id', $data->id)->first();

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

                $currencyService = app(\App\Services\Finance\InvoiceCurrencyService::class);

                // Create a data object for additional fields not in the main table
                $additionalData = [
                    'currency_id' => $currencyService->legacyMonedaIdToCurrencyId((int) ($data->id_moneda ?? 1)),
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
                    'team_id' => env('CMS_TEAM_ID', 2),
                    'enterprise_id' => $data->enterprise_id,
                    'type_id' => 1,  // Set to 1 (fixed value) since original types may not exist
                    'billing_id' => $data->billing_id,  // Use original empresas_fiscales.id
                    'operation' => $operation,
                    'number' => $invoiceNumber,
                    'date' => $data->fecha,
                    'due_date' => $data->vencimiento,
                    'gross_amount' => $data->bruto ?? 0,
                    'discount' => $data->descuento,
                    'total_amount' => $data->total_neto ?? 0,
                    'balance' => $data->saldo ?? 0,
                    'status' => $data->estado,
                    'created_at' => $data->fecha_alta ?? now(),
                    'updated_at' => $data->fecha_modificacion ?? now(),
                ];

                if (\Illuminate\Support\Facades\Schema::hasColumn('invoices', 'currency_id'))
                {
                    $invoiceData['currency_id'] = $additionalData['currency_id'];
                }

                if (! $existingInvoice)
                {
                    app('db')->table('invoices')->insert($invoiceData);
                    $stats['imported']++;
                } else
                {
                    // Preserve local values when legacy is empty
                    $mergedData = $this->mergePreservingLocal(
                        $invoiceData,
                        $existingInvoice,
                        ['status_id', 'enterprise_id', 'total_amount', 'due_date'], // Always update these fields
                    );
                    app('db')->table('invoices')->where('id', $existingInvoice->id)->update($mergedData);
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
     * Import billing addresses from remote database
     */
    protected function importBillingAddresses($id = null)
    {
        $stats = [
            'imported' => 0,
            'updated' => 0,
            'message' => null,
        ];

        try
        {
            $query = app('db')->connection('mysql_legacy')
                ->table('empresas_fiscales')
                ->where('grupo', env('CMS_GROUP', 502))
                ->select('id', 'id_empresa', 'razon_social', 'cuit', 'ingresos_brutos', 'id_condicion_iva', 'domicilio', 'codigo_postal', 'localidad', 'provincia', 'pais', 'estado', 'fecha_alta', 'fecha_modificacion');

            if ($id)
            {
                $query->where('id', $id);
            }

            $billingAddresses = $query->get();

            if ($billingAddresses->isEmpty())
            {
                $stats['message'] = 'No billing addresses found matching the criteria.';

                return $stats;
            }

            $bar = $this->output->createProgressBar(count($billingAddresses));
            $bar->start();

            foreach ($billingAddresses as $data)
            {
                // Check if enterprise exists in the local database
                $enterpriseExists = app('db')->table('enterprises')->where('id', $data->id_empresa)->exists();
                if (! $enterpriseExists)
                {
                    $bar->advance();

                    continue;
                }

                $existingBillingAddress = app('db')->table('enterprise_billing_addresses')->where('id', $data->id)->first();

                $billingAddressData = [
                    'id' => $data->id,
                    'enterprise_id' => $data->id_empresa,
                    'name' => $data->razon_social ?? 'N/A',
                    'identification_number' => $data->cuit,
                    'tax_status_type_id' => $data->id_condicion_iva ?? 1,
                    'address' => $data->domicilio,
                    'postal_code' => $data->codigo_postal,
                    'locality' => $data->localidad,
                    'province' => $data->provincia,
                    'country' => $data->pais,
                    'status' => $data->estado,
                    'created_at' => $data->fecha_alta ?? now(),
                    'updated_at' => $data->fecha_modificacion ?? now(),
                ];

                if (! $existingBillingAddress)
                {
                    app('db')->table('enterprise_billing_addresses')->insert($billingAddressData);
                    $stats['imported']++;
                } else
                {
                    // Preserve local values when legacy is empty
                    $mergedData = $this->mergePreservingLocal(
                        $billingAddressData,
                        $existingBillingAddress,
                        ['enterprise_id', 'is_default'], // Always update these fields
                    );
                    app('db')->table('enterprise_billing_addresses')->where('id', $existingBillingAddress->id)->update($mergedData);
                    $stats['updated']++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        } catch (\Exception $e)
        {
            $this->newLine();
            throw new \Exception('Error importing billing addresses: '.$e->getMessage());
        }

        return $stats;
    }

    /**
     * Import invoice items from remote database
     */
    protected function importInvoiceItems($id = null)
    {
        $stats = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'skipped_no_invoice' => 0,
            'message' => null,
        ];

        try
        {
            $query = app('db')->connection('mysql_legacy')
                ->table('facturas_items')
                ->where('grupo', env('CMS_GROUP', 502))
                ->select('id', 'id_factura', 'id_categoria', 'descripcion', 'valor', 'descuento', 'fecha_alta', 'fecha_modificacion');

            if ($id)
            {
                $query->where('id', $id);
            }

            $invoiceItems = $query->get();

            if ($invoiceItems->isEmpty())
            {
                $stats['message'] = 'No invoice items found matching the criteria.';

                return $stats;
            }

            $bar = $this->output->createProgressBar(count($invoiceItems));
            $bar->start();

            $skipped = 0;
            $skippedNoInvoice = 0;
            foreach ($invoiceItems as $data)
            {
                try
                {
                    // Check if invoice exists in the local database
                    $invoice = app('db')->table('invoices')->where('id', $data->id_factura)->first(['team_id']);
                    if ($invoice === null)
                    {
                        $skippedNoInvoice++;
                        $stats['skipped_no_invoice']++;
                        if ($skippedNoInvoice <= 10)
                        {
                            $this->newLine();
                            $this->warn("     Skipped invoice item {$data->id}: Invoice {$data->id_factura} does not exist");
                        }
                        $bar->advance();

                        continue;
                    }

                    $existingInvoiceItem = app('db')->table('invoice_items')->where('id', $data->id)->first();

                    // Check if category exists, if not set to null
                    $categoryId = app(InvoiceItemLegacySyncService::class)->resolveCategoryId(
                        $data->id_categoria ? (int) $data->id_categoria : null,
                        (int) ($invoice->team_id ?? 2),
                    );

                    $invoiceItemData = [
                        'id' => $data->id,
                        'invoice_id' => $data->id_factura,
                        'category_id' => $categoryId,
                        'description' => $data->descripcion,
                        'quantity' => 1.0,  // Default quantity from CMS7
                        'unit_price' => $data->valor ?? 0,
                        'discount' => $data->descuento ?? 0,
                        'tax_percentage' => 0,  // Default tax from CMS7
                        'created_at' => $data->fecha_alta ?? now(),
                        'updated_at' => $data->fecha_modificacion ?? now(),
                    ];

                    if (! $existingInvoiceItem)
                    {
                        app('db')->table('invoice_items')->insert($invoiceItemData);
                        $stats['imported']++;
                    } else
                    {
                        // Preserve local values when legacy is empty
                        $mergedData = $this->mergePreservingLocal(
                            $invoiceItemData,
                            $existingInvoiceItem,
                            ['invoice_id', 'category_id', 'description', 'quantity', 'unit_price', 'discount'],
                        );
                        app('db')->table('invoice_items')->where('id', $existingInvoiceItem->id)->update($mergedData);
                        $stats['updated']++;
                    }
                } catch (\Exception $e)
                {
                    $skipped++;
                    if ($skipped <= 10)
                    {
                        $this->newLine();
                        $this->warn("     Skipped invoice item {$data->id}: ".$e->getMessage());
                    }
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            if ($skipped > 0)
            {
                $this->warn("   ⚠️  Skipped {$skipped} invoice items due to errors");
            }

            if ($skippedNoInvoice > 0)
            {
                $this->warn("   ⚠️  Skipped {$skippedNoInvoice} invoice items because their invoices don't exist");
                $this->info('   💡 Tip: Make sure invoices are imported before importing invoice items');
            }

            $stats['skipped'] = $skipped;
        } catch (\Exception $e)
        {
            $this->newLine();
            throw new \Exception('Error importing invoice items: '.$e->getMessage());
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

        try
        {
            // Test connection
            app('db')->connection('mysql_legacy')->getPdo();

            // Get the CMS group
            $cmsGroup = env('CMS_GROUP', 502);
            $this->info("   Using CMS_GROUP: {$cmsGroup}");

            $query = app('db')->connection('mysql_legacy')
                ->table('servicios')
                ->where('servicios.grupo', $cmsGroup)
                ->where('servicios.estado', '>', 0)
                ->where('servicios.operacion', 'V');  // Only sales

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

            $this->info("   Found {$services->count()} services to import");
            $bar = $this->output->createProgressBar($services->count());
            $bar->start();

            $skipped = 0;
            foreach ($services as $service)
            {
                try
                {
                    // Map operation codes: V=sell (Venta), C=buy (Compra)
                    $operation = ($service->operacion ?? 'V') === 'V' ? 'sell' : 'buy';

                    $existingService = \App\Models\Service::where('id', $service->id)->first();

                    $serviceData = [
                        'enterprise_id' => $service->id_empresa,
                        'category_id' => $service->id_categoria ?? null,
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
                    ];

                    if ($existingService)
                    {
                        // Preserve local values when legacy is empty
                        $mergedData = $this->mergePreservingLocal(
                            $serviceData,
                            $existingService,
                            ['status', 'operation', 'enterprise_id'], // Always update these fields
                        );
                        $existingService->update($mergedData);
                        $stats['updated']++;
                    } else
                    {
                        \App\Models\Service::create(array_merge(['id' => $service->id], $serviceData));
                        $stats['imported']++;
                    }
                    $bar->advance();
                } catch (\Exception $e)
                {
                    $skipped++;
                    if ($skipped <= 10)
                    {
                        $this->newLine();
                        $this->warn("     Skipped service {$service->id}: ".$e->getMessage());
                    }
                    $bar->advance();
                }
            }

            $bar->finish();
            $this->newLine();

            if ($skipped > 0)
            {
                $this->warn("   ⚠️  Skipped {$skipped} services due to errors");
            }

            $this->info("✅ Imported {$stats['imported']} services, updated {$stats['updated']}");
        } catch (\Exception $e)
        {
            $this->warn('⚠️  Could not import services: '.$e->getMessage());
        }

        return $stats;
    }

    /**
     * Import Legacy project categories (categorias_generales padre = 40) into the projects module.
     */
    protected function importProjectCategories($id = null)
    {
        $this->info('🏷️  Importing Legacy project categories...');

        $stats = [
            'imported' => 0,
            'updated' => 0,
            'message' => null,
        ];

        try
        {
            app('db')->connection('mysql_legacy')->getPdo();
            $service = app(ProjectCategoryLegacyImportService::class);

            if ($id)
            {
                $categoryId = $service->resolveCategoryIdFromLegacy((int) $id);
                if ($categoryId === null)
                {
                    $stats['message'] = 'Legacy project category not found or projects module missing.';

                    return $stats;
                }

                $stats['imported'] = 1;
                $this->info("   Resolved Legacy category {$id} → categories.id {$categoryId}");

                return $stats;
            }

            $result = $service->importAllFromLegacy();
            $stats['imported'] = $result['imported'];
            $stats['updated'] = $result['updated'];
            $stats['message'] = $result['message'];

            $this->info("   Legacy rows: {$result['total_legacy']}; imported: {$result['imported']}; updated: {$result['updated']}; skipped: {$result['skipped']}");
        } catch (\Exception $e)
        {
            $this->newLine();
            throw new \Exception('Error importing project categories: '.$e->getMessage());
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

        try
        {
            // Test connection
            app('db')->connection('mysql_legacy')->getPdo();

            // Ensure Legacy project categories exist under the projects module before mapping.
            $categoryService = app(ProjectCategoryLegacyImportService::class);
            $categoryImport = $categoryService->importAllFromLegacy();
            if ($categoryImport['message'] && $categoryImport['total_legacy'] === 0)
            {
                $this->warn('   '.$categoryImport['message']);
            } else
            {
                $this->info("   Project categories ready (imported: {$categoryImport['imported']}, updated: {$categoryImport['updated']})");
            }

            // Get the CMS group
            $cmsGroup = env('CMS_GROUP', 502);
            $this->info("   Using CMS_GROUP: {$cmsGroup}");

            $query = app('db')->connection('mysql_legacy')
                ->table('proyectos')
                ->where('grupo', $cmsGroup)
                ->where('estado', '>', 0);

            if ($id)
            {
                $query->where('id', $id);
            }

            $projects = $query->get();

            if ($projects->isEmpty())
            {
                $stats['message'] = 'No projects found matching the criteria.';

                return $stats;
            }

            $this->info("   Found {$projects->count()} projects to import");
            $bar = $this->output->createProgressBar($projects->count());
            $bar->start();

            $skipped = 0;
            foreach ($projects as $project)
            {
                try
                {
                    // Get team ID - REVISION ALPHA team
                    $teamId = 2;

                    // Get responsible user - default to the team owner if not found
                    $responsibleId = \App\Models\User::where('email', 'diego.mascarenhas@icloud.com')->first()->id;

                    // Check if enterprise exists
                    if (! app('db')->table('enterprises')->where('id', $project->id_empresa)->exists())
                    {
                        $skipped++;

                        continue;
                    }

                    $legacyCategoryId = ! empty($project->id_categoria) ? (int) $project->id_categoria : null;
                    $categoryId = $categoryService->resolveCategoryIdFromLegacy($legacyCategoryId);

                    $existingProject = \App\Models\Project::where('id', $project->id)->first();

                    $projectData = [
                        'team_id' => $teamId,
                        'enterprise_id' => $project->id_empresa,
                        'category_id' => $categoryId,
                        'responsible_id' => $responsibleId,
                        'name' => $project->titulo ?? 'Proyecto '.$project->id,
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
                    ];

                    if ($existingProject)
                    {
                        // Preserve local values when legacy is empty; always refresh category when Legacy has one.
                        $alwaysUpdate = ['status_id', 'enterprise_id', 'responsible_id'];
                        if ($categoryId !== null)
                        {
                            $alwaysUpdate[] = 'category_id';
                        }
                        $mergedData = $this->mergePreservingLocal(
                            $projectData,
                            $existingProject,
                            $alwaysUpdate,
                        );
                        $existingProject->update($mergedData);
                        $stats['updated']++;
                    } else
                    {
                        \App\Models\Project::create(array_merge(['id' => $project->id], $projectData));
                        $stats['imported']++;
                    }
                    $bar->advance();
                } catch (\Exception $e)
                {
                    $skipped++;
                    if ($skipped <= 10)
                    {
                        $this->newLine();
                        $this->warn("     Skipped project {$project->id}: ".$e->getMessage());
                    }
                    $bar->advance();
                }
            }

            $bar->finish();
            $this->newLine();

            if ($skipped > 0)
            {
                $this->warn("   ⚠️  Skipped {$skipped} projects due to errors");
            }

            $this->info("✅ Imported {$stats['imported']} projects, updated {$stats['updated']}");
        } catch (\Exception $e)
        {
            $this->warn('⚠️  Could not import projects: '.$e->getMessage());
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

        try
        {
            // Verify payments table exists
            if (! \Illuminate\Support\Facades\Schema::hasTable('payments'))
            {
                $this->warn('⚠️  Payments table does not exist. Skipping payment import.');
                $this->info('   Run: php artisan vendor:publish --tag="humano-billing-migrations" && php artisan migrate');

                return $stats;
            }

            // Test connection
            app('db')->connection('mysql_legacy')->getPdo();

            // Get the CMS group
            $cmsGroup = env('CMS_GROUP', 502);
            $this->info("   Using CMS_GROUP: {$cmsGroup}");

            // Use team_id directly
            $teamId = 2;  // REVISION ALPHA team

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

            $query = app('db')->connection('mysql_legacy')
                ->table('movimientos')
                ->leftJoin('facturas', 'movimientos.id_factura', '=', 'facturas.id')
                ->leftJoin('empresas_fiscales', 'facturas.id_empresa_fiscal', '=', 'empresas_fiscales.id')
                ->where('movimientos.grupo', $cmsGroup)
                ->where('movimientos.estado', '>', 0)
                ->where(function ($q)
                {
                    // Si tiene factura, la factura debe tener estado > 0
                    // Si no tiene factura, permitir el pago
                    $q
                        ->whereNull('movimientos.id_factura')
                        ->orWhere('facturas.estado', '>', 0);
                })
                ->select(
                    'movimientos.*',
                    'empresas_fiscales.id_empresa as enterprise_id',
                    'facturas.id_empresa_fiscal',
                );

            if ($id)
            {
                $query->where('movimientos.id', $id);
            }

            $payments = $query->get();

            if ($payments->isEmpty())
            {
                $stats['message'] = 'No payments found matching the criteria.';

                return $stats;
            }

            $this->info("   Found {$payments->count()} payments to import");
            $bar = $this->output->createProgressBar($payments->count());
            $bar->start();

            // Get default account for team (we ensured it exists above)
            $defaultTeamAccount = app('db')->table('payment_accounts')->where('team_id', $teamId)->first();

            $skipped = 0;
            foreach ($payments as $payment)
            {
                try
                {
                    // Get account ID - if not exists, use default account for this team
                    $accountId = $payment->id_cuenta;
                    if (! $accountId || ! app('db')->table('payment_accounts')->where('id', $accountId)->exists())
                    {
                        // Use the default team account
                        $accountId = $defaultTeamAccount->id;
                    }

                    // Map legacy payment type ID to new ID
                    $legacyTypeId = $payment->id_forma_pago ?? 1;
                    $typeId = $paymentTypeMap[$legacyTypeId] ?? 1;  // Default to Cash if not mapped

                    // Determine transaction type: I=Income, E=Expense (default to expense if unknown)
                    $transactionType = 'expense';
                    if (isset($payment->transaccion))
                    {
                        $transactionType = strtoupper($payment->transaccion) === 'I' ? 'income' : 'expense';
                    }

                    // Get amount from 'valor' field
                    $amount = $payment->valor ?? 0;

                    // Get enterprise_id from multiple sources
                    $enterpriseId = null;

                    // 1. Try from the JOIN result
                    if ($payment->enterprise_id)
                    {
                        if (app('db')->table('enterprises')->where('id', $payment->enterprise_id)->exists())
                        {
                            $enterpriseId = $payment->enterprise_id;
                        }
                    }

                    // 2. If still null, try to get from invoice
                    $invoiceId = $payment->id_factura;
                    if (! $enterpriseId && $invoiceId)
                    {
                        $invoice = app('db')->table('invoices')->where('id', $invoiceId)->first();
                        if ($invoice && $invoice->enterprise_id)
                        {
                            $enterpriseId = $invoice->enterprise_id;
                        }
                        // If invoice doesn't exist, set invoiceId to null
                        if (! $invoice)
                        {
                            $invoiceId = null;
                        }
                    }

                    // 3. If still null and we have id_empresa_fiscal, try to find the enterprise
                    if (! $enterpriseId && isset($payment->id_empresa_fiscal))
                    {
                        $enterpriseFromFiscal = app('db')->table('enterprises')
                            ->where('id', $payment->id_empresa_fiscal)
                            ->where('team_id', $teamId)
                            ->first();
                        if ($enterpriseFromFiscal)
                        {
                            $enterpriseId = $enterpriseFromFiscal->id;
                        }
                    }

                    $existingPayment = \App\Models\Payment::where('id', $payment->id)->first();

                    $paymentData = [
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
                    ];

                    if ($existingPayment)
                    {
                        // Preserve local values when legacy is empty
                        $mergedData = $this->mergePreservingLocal(
                            $paymentData,
                            $existingPayment,
                            ['status', 'transaction_type', 'amount', 'date'], // Always update these fields
                        );
                        $existingPayment->update($mergedData);
                        $stats['updated']++;
                    } else
                    {
                        \App\Models\Payment::create(array_merge(['id' => $payment->id], $paymentData));
                        $stats['imported']++;
                    }
                    $bar->advance();
                } catch (\Exception $e)
                {
                    $skipped++;
                    if ($skipped <= 10)
                    {
                        $this->newLine();
                        $this->warn("     Skipped payment {$payment->id}: ".$e->getMessage());
                    }
                    $bar->advance();
                }
            }

            $bar->finish();
            $this->newLine();

            if ($skipped > 0)
            {
                $this->warn("   ⚠️  Skipped {$skipped} payments due to errors");
            }

            $this->info("✅ Imported {$stats['imported']} payments, updated {$stats['updated']}");
        } catch (\Exception $e)
        {
            $this->warn('⚠️  Could not import payments: '.$e->getMessage());
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

        try
        {
            $query = app('db')->connection('mysql_legacy')
                ->table('comunicaciones_tipo')
                ->where('estado', '>', 0);

            if ($id)
            {
                $query->where('id', $id);
            }

            $types = $query->get();

            if ($types->isEmpty())
            {
                $stats['message'] = 'No notification types found.';

                return $stats;
            }

            $this->info("   Found {$types->count()} notification types to import");
            $bar = $this->output->createProgressBar($types->count());
            $bar->start();

            foreach ($types as $type)
            {
                try
                {
                    $existingType = \App\Models\NotificationType::where('id', $type->id)->first();

                    if (! $existingType)
                    {
                        // Insert with original ID
                        app('db')->table('notification_types')->insert([
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
                    } else
                    {
                        // Update existing
                        app('db')->table('notification_types')
                            ->where('id', $type->id)
                            ->update([
                                'name' => $type->tipo,
                                'is_active' => $type->estado > 0,
                                'updated_at' => now(),
                            ]);
                        $stats['updated']++;
                    }

                    $bar->advance();
                } catch (\Exception $e)
                {
                    // Skip on error
                    $bar->advance();

                    continue;
                }
            }

            $bar->finish();
            $this->newLine();
            $this->info("✅ Imported {$stats['imported']} notification types, updated {$stats['updated']}");
        } catch (\Exception $e)
        {
            $this->newLine();
            throw new \Exception('Error importing notification types: '.$e->getMessage());
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

        try
        {
            $query = app('db')->connection('mysql_legacy')
                ->table('comunicaciones')
                ->where('grupo', env('CMS_GROUP', 502))
                ->where('estado', '>', 0);

            if ($id)
            {
                $query->where('id', $id);
            }

            $communications = $query->get();

            if ($communications->isEmpty())
            {
                $stats['message'] = 'No communications found.';

                return $stats;
            }

            $this->info("   Found {$communications->count()} notifications to import");
            $bar = $this->output->createProgressBar($communications->count());
            $bar->start();

            // Get user ID from team once (default to first user in team 2)
            $userId = \App\Models\User::whereHas('teams', function ($q)
            {
                $q->where('teams.id', 2);
            })->first()->id ?? 1;

            foreach ($communications as $comm)
            {
                try
                {
                    // Verificar si el contacto existe, si no existe usar NULL
                    $contactId = null;
                    if ($comm->id_contacto && app('db')->table('contacts')->where('id', $comm->id_contacto)->exists())
                    {
                        $contactId = $comm->id_contacto;
                    }

                    // Si no tiene contact_id válido, registrar en skipped pero continuar
                    if (! $contactId)
                    {
                        $stats['skipped']++;
                        $bar->advance();

                        continue;
                    }

                    $existingNotification = \App\Models\Notification::withoutGlobalScope('team')->where('id', $comm->id)->first();

                    $notificationData = [
                        'team_id' => 2,  // REVISION ALPHA team
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
                    ];

                    if ($existingNotification)
                    {
                        // Preserve local values when legacy is empty
                        $mergedData = $this->mergePreservingLocal(
                            $notificationData,
                            $existingNotification,
                            ['is_sent', 'is_read', 'subject', 'message'], // Always update these fields
                        );
                        $existingNotification->update($mergedData);
                        $stats['updated']++;
                    } else
                    {
                        \App\Models\Notification::withoutGlobalScope('team')->create(array_merge(['id' => $comm->id], $notificationData));
                        $stats['imported']++;
                    }

                    $bar->advance();
                } catch (\Exception $e)
                {
                    $stats['skipped']++;
                    if ($stats['skipped'] <= 10)
                    {
                        $this->newLine();
                        $this->warn("     Skipped notification {$comm->id}: ".$e->getMessage());
                    }
                    $bar->advance();

                    continue;
                }
            }

            $bar->finish();
            $this->newLine();

            if ($stats['skipped'] > 0)
            {
                $this->warn("   ⚠️  Skipped {$stats['skipped']} notifications (contacts not found)");
            }

            $this->info("✅ Imported {$stats['imported']} notifications, updated {$stats['updated']}");
        } catch (\Exception $e)
        {
            $this->newLine();
            throw new \Exception('Error importing communications: '.$e->getMessage());
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

        $query = app('db')->connection('mysql_legacy')
            ->table('categorias_generales')
            ->where('grupo', env('CMS_GROUP', 502))
            ->whereNull('padre')  // Only parent categories
            ->whereIn('estado', [1, 2])  // Include active states 1 and 2
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
                    // Preserve local values when legacy is empty
                    $mergedData = $this->mergePreservingLocal(
                        $categoryData,
                        $existingCategory,
                        ['name', 'module_key', 'status_id'], // Always update these fields
                    );
                    $existingCategory->update($mergedData);
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

        $query = app('db')->connection('mysql_legacy')
            ->table('categorias_generales')
            ->where('grupo', env('CMS_GROUP', 502))
            ->whereNotNull('padre')  // Only child products
            ->whereIn('estado', [1, 2])  // Include active states 1 and 2
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

        $mainStore = Store::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->where('is_main', true)
            ->first();
        if (! $mainStore)
        {
            $mainStore = Store::ensureMainStoreForTeam($teamId);
        }

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

        if (! $existingProduct || ! $existingProduct->store_id)
        {
            $productData['store_id'] = $mainStore->id;
        }

        if (! $existingProduct)
        {
            Product::create($productData);

            return 'imported';
        } else
        {
            // Preserve local values when legacy is empty
            $mergedData = $this->mergePreservingLocal(
                $productData,
                $existingProduct,
                ['name', 'price', 'category_id', 'status'], // Always update these fields
            );
            $existingProduct->update($mergedData);

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

        if (! $currency)
        {
            // Fallback to USD if currency not found
            $currency = Currency::where('code', 'USD')->first();

            if (! $currency)
            {
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
        if (isset($cms7Product->padre) && $cms7Product->padre)
        {
            // Get parent category from CMS7
            $parentCategory = app('db')->connection('mysql_legacy')
                ->table('categorias_generales')
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

    /**
     * Import all data in the correct order
     */
    protected function importAll()
    {
        $this->info('🚀 Starting full import process...');
        $this->newLine();

        // Import in order to respect foreign key constraints
        $this->info('📂 Step 1/14: Importing Categories...');
        $this->processImport('2. Categories');
        $this->newLine();

        $this->info('🏢 Step 2/14: Importing Enterprises...');
        $this->processImport('5. Enterprises');
        $this->newLine();

        $this->info('🏬 Step 3/14: Importing Stores (Pedimos Facil -> Teams)...');
        $this->processImport('16. Stores (Pedimos Facil -> Teams)');
        $this->newLine();

        $this->info('📦 Step 4/14: Importing Services...');
        $this->processImport('6. Services');
        $this->newLine();

        $this->info('🏷️  Step 5/14: Importing Project Categories...');
        $this->processImport('7. Project Categories');
        $this->newLine();

        $this->info('📁 Step 6/14: Importing Projects...');
        $this->processImport('8. Projects');
        $this->newLine();

        $this->info('📋 Step 7/14: Importing Billing Addresses...');
        $this->processImport('10. Billing Addresses');
        $this->newLine();

        $this->info('📄 Step 8/14: Importing Invoices...');
        $this->processImport('9. Invoices');
        $this->newLine();

        $this->info('📝 Step 9/14: Importing Invoice Items...');
        $this->processImport('11. Invoice Items');
        $this->newLine();

        $this->info('💳 Step 10/14: Importing Payment Accounts...');
        $this->processImport('4. Payment Accounts');
        $this->newLine();

        $this->info('💰 Step 11/14: Importing Payments (linking enterprises & invoices)...');
        $this->processImport('12. Payments');
        $this->newLine();

        $this->info('👥 Step 12/14: Importing Users/Contacts...');
        $this->processImport('1. Users');
        $this->newLine();

        $this->info('🔔 Step 13/14: Importing Notification Types...');
        $this->processImport('13. Notification Types');
        $this->newLine();

        $this->info('📞 Step 14/14: Importing Notifications...');
        $this->processImport('14. Communications');
        $this->newLine();

        $this->info('✅ Full import completed successfully!');
    }

    /**
     * Import Pedimos Facil stores from legacy into teams.
     * Mapping:
     * - teams.id = tienda_configuracion.id_empresa
     * - teams.name = empresas.empresa
     */
    protected function importStoresToTeams($id = null)
    {
        $stats = [
            'imported' => 0,
            'updated' => 0,
            'categories_imported' => 0,
            'categories_updated' => 0,
            'branches_imported' => 0,
            'branches_updated' => 0,
            'products_imported' => 0,
            'products_updated' => 0,
            'order_contacts_imported' => 0,
            'order_contacts_updated' => 0,
            'orders_imported' => 0,
            'orders_updated' => 0,
            'message' => null,
        ];

        try
        {
            $query = app('db')->connection('mysql_legacy')
                ->table('tienda_configuracion as tc')
                ->join('empresas as e', 'e.id', '=', 'tc.id_empresa')
                ->where('tc.grupo', 513)
                ->whereNotNull('tc.id_empresa')
                ->orderBy('tc.id_empresa')
                ->select(
                    'tc.id_empresa',
                    app('db')->raw('CONVERT(CAST(CONVERT(e.empresa USING latin1) AS BINARY) USING utf8mb4) as empresa'),
                );

            if ($id)
            {
                $query->where('tc.id_empresa', $id);
            }

            $stores = $query->distinct()->get();

            if ($stores->isEmpty())
            {
                $stats['message'] = 'No Pedimos Facil stores found to import.';

                return $stats;
            }

            $this->info("Found {$stores->count()} stores to import into teams");
            $bar = $this->output->createProgressBar($stores->count());
            $bar->start();
            $teamIds = [];

            foreach ($stores as $store)
            {
                $normalizedTeamName = $this->normalizeLegacyText((string) $store->empresa);
                $teamName = trim($normalizedTeamName) !== '' ? $normalizedTeamName : "Team {$store->id_empresa}";
                $adminContact = app('db')->connection('mysql_legacy')
                    ->table('contactos')
                    ->where('grupo', 513)
                    ->where('id_empresa', $store->id_empresa)
                    ->whereIn('area_privada', [2, 3, 4])
                    ->orderByRaw('FIELD(area_privada, 2, 3, 4)')
                    ->orderByDesc('id')
                    ->select('id', 'nombre', 'apellido', 'email', 'telefono', 'celular', 'fecha_alta', 'fecha_modificacion')
                    ->first();

                $teamOwnerId = null;
                if ($adminContact)
                {
                    $fullName = trim(((string) $adminContact->nombre).' '.((string) ($adminContact->apellido ?? '')));
                    $name = $fullName !== '' ? $this->normalizeLegacyText($fullName) : 'Legacy User '.$adminContact->id;
                    $email = ! empty($adminContact->email)
                        ? strtolower(trim((string) $adminContact->email))
                        : "legacy-user-{$adminContact->id}@example.local";
                    $cleanPhone = PhoneHelper::clean($adminContact->celular ?? $adminContact->telefono ?? null, '54', true);

                    $userById = User::withTrashed()->find($adminContact->id);
                    $userByEmail = User::withTrashed()->where('email', $email)->first();

                    if ($userById)
                    {
                        $userById->forceFill([
                            'name' => $name,
                            'email' => $email,
                            'phone' => $cleanPhone ?: null,
                            'password' => Hash::make(self::IMPORT_DEFAULT_USER_PASSWORD),
                            'email_verified_at' => $userById->email_verified_at ?? now(),
                            'created_at' => $userById->created_at ?? ($adminContact->fecha_alta ?? now()),
                            'updated_at' => $adminContact->fecha_modificacion ?? now(),
                            'deleted_at' => null,
                        ])->save();

                        $teamOwnerId = $userById->id;
                    } elseif ($userByEmail)
                    {
                        $userByEmail->forceFill([
                            'name' => $name,
                            'phone' => $cleanPhone ?: null,
                            'password' => Hash::make(self::IMPORT_DEFAULT_USER_PASSWORD),
                            'updated_at' => $adminContact->fecha_modificacion ?? now(),
                            'deleted_at' => null,
                        ])->save();
                        $teamOwnerId = $userByEmail->id;
                    } else
                    {
                        app('db')->table('users')->insert([
                            'id' => $adminContact->id,
                            'name' => $name,
                            'email' => $email,
                            'phone' => $cleanPhone ?: null,
                            'password' => Hash::make(self::IMPORT_DEFAULT_USER_PASSWORD),
                            'email_verified_at' => now(),
                            'remember_token' => null,
                            'current_team_id' => null,
                            'profile_photo_path' => null,
                            'subscribed' => true,
                            'created_at' => $adminContact->fecha_alta ?? now(),
                            'updated_at' => $adminContact->fecha_modificacion ?? now(),
                            'deleted_at' => null,
                        ]);

                        $teamOwnerId = (int) $adminContact->id;
                    }
                }

                if (! $teamOwnerId)
                {
                    $teamOwnerId = (int) (User::query()->orderBy('id')->value('id') ?? 1);
                }

                $existingTeam = Team::withoutGlobalScopes()->find($store->id_empresa);

                if ($existingTeam)
                {
                    app('db')->table('teams')
                        ->where('id', $store->id_empresa)
                        ->update([
                            'user_id' => $teamOwnerId,
                            'name' => $teamName,
                            'updated_at' => now(),
                        ]);
                    $stats['updated']++;
                } else
                {
                    app('db')->table('teams')->insert([
                        'id' => $store->id_empresa,
                        'user_id' => $teamOwnerId,
                        'name' => $teamName,
                        'personal_team' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $stats['imported']++;
                }

                $teamModel = Team::withoutGlobalScopes()->find($store->id_empresa);
                if ($teamModel)
                {
                    foreach (['contacts', 'stores', 'products', 'orders', 'prompts', 'chat'] as $moduleKey)
                    {
                        $teamModel->enableModule($moduleKey);
                    }
                    $teamModel->disableModule('mailbox');
                    $teamModel->disableModule('attendance');
                }

                app('db')->table('team_user')->updateOrInsert(
                    [
                        'team_id' => $store->id_empresa,
                        'user_id' => $teamOwnerId,
                    ],
                    [
                        'role' => 'admin',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );

                $ownerUser = User::withTrashed()->find($teamOwnerId);
                if ($ownerUser && app('db')->table('roles')->where('name', 'admin')->exists() && ! $ownerUser->hasRole('admin'))
                {
                    $ownerUser->assignRole('admin');
                }

                app('db')->table('users')
                    ->where('id', $teamOwnerId)
                    ->whereNull('current_team_id')
                    ->update(['current_team_id' => $store->id_empresa]);

                $teamIds[] = (int) $store->id_empresa;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            $productsModuleId = (int) app('db')->table('modules')->where('key', 'products')->value('id');
            if ($productsModuleId > 0)
            {
                $teamIds = array_values(array_unique($teamIds));
                foreach ($teamIds as $teamId)
                {
                    $categoryStats = $this->importStoreProductCategoriesForTeam($teamId, $productsModuleId);
                    $stats['categories_imported'] += $categoryStats['imported'];
                    $stats['categories_updated'] += $categoryStats['updated'];

                    $branchStats = $this->importStoreBranchesForTeam($teamId);
                    $stats['branches_imported'] += $branchStats['imported'];
                    $stats['branches_updated'] += $branchStats['updated'];

                    $productStats = $this->importStoreProductsForTeam($teamId);
                    $stats['products_imported'] += $productStats['imported'];
                    $stats['products_updated'] += $productStats['updated'];

                    $orderContactsStats = $this->importStoreOrderContactsForTeam($teamId);
                    $stats['order_contacts_imported'] += $orderContactsStats['imported'];
                    $stats['order_contacts_updated'] += $orderContactsStats['updated'];

                    $ordersStats = $this->importStoreOrdersForTeam($teamId);
                    $stats['orders_imported'] += $ordersStats['imported'];
                    $stats['orders_updated'] += $ordersStats['updated'];
                }
            } else
            {
                $this->warn('Products module not found. Store categories were not imported.');
            }
        } catch (\Exception $e)
        {
            throw new \Exception('Error importing Pedimos Facil stores to teams: '.$e->getMessage());
        }

        return $stats;
    }

    private function importStoreProductCategoriesForTeam(int $teamId, int $productsModuleId): array
    {
        $stats = [
            'imported' => 0,
            'updated' => 0,
        ];

        $categories = app('db')->connection('mysql_legacy')
            ->table('tienda_productos_categorias as tpc')
            ->leftJoin('tienda_configuracion as tc_store', 'tc_store.id', '=', 'tpc.id_tienda')
            ->leftJoin('tienda_configuracion as tc_team', 'tc_team.id_empresa', '=', 'tpc.id_tienda')
            ->select(
                'tpc.id',
                'tpc.id_tienda',
                'tpc.categoria',
                'tpc.uri',
                'tpc.observaciones',
                'tpc.imagen',
                'tpc.orden',
                'tpc.estado',
                app('db')->raw('COALESCE(tc_store.id_empresa, tc_team.id_empresa, tpc.id_tienda) as resolved_team_id'),
            )
            ->whereRaw('COALESCE(tc_store.id_empresa, tc_team.id_empresa, tpc.id_tienda) = ?', [$teamId])
            ->get();

        foreach ($categories as $legacyCategory)
        {
            $name = $this->normalizeLegacyText((string) ($legacyCategory->categoria ?? ''));
            if ($name === '')
            {
                continue;
            }

            $existingCategory = Category::query()
                ->where('team_id', $teamId)
                ->where('module_id', $productsModuleId)
                ->whereNull('parent_id')
                ->where('name', $name)
                ->first();

            $categoryData = [
                'name' => $name,
                'module_id' => $productsModuleId,
                'team_id' => $teamId,
                'description' => $legacyCategory->observaciones ?: null,
                'data' => [
                    'legacy_store_category_id' => $legacyCategory->id,
                    'legacy_store_id' => $legacyCategory->id_tienda,
                    'uri' => $legacyCategory->uri,
                    'image' => $legacyCategory->imagen,
                    'imported_from_pedimos_facil' => true,
                ],
                'parent_id' => null,
                'order' => (int) ($legacyCategory->orden ?? 0),
                'status' => (int) ($legacyCategory->estado ?? 0) > 0,
            ];

            if (! $existingCategory)
            {
                Category::query()->create($categoryData);
                $stats['imported']++;
            } else
            {
                $mergedData = $this->mergePreservingLocal(
                    $categoryData,
                    $existingCategory,
                    ['name', 'order', 'status'],
                );
                $existingCategory->update($mergedData);
                $stats['updated']++;
            }
        }

        return $stats;
    }

    private function importStoreBranchesForTeam(int $teamId): array
    {
        $stats = [
            'imported' => 0,
            'updated' => 0,
        ];

        $branches = app('db')->connection('mysql_legacy')
            ->table('tienda_sucursales as ts')
            ->join('tienda_configuracion as tc', 'tc.id', '=', 'ts.id_tienda')
            ->where('tc.grupo', 513)
            ->where('tc.id_empresa', $teamId)
            ->select(
                'ts.id',
                'ts.titulo',
                'ts.domicilio',
                'ts.numero',
                'ts.localidad',
                'ts.provincia',
                'ts.estado',
                'ts.orden',
            )
            ->orderBy('ts.orden')
            ->orderBy('ts.id')
            ->get();

        if ($branches->isEmpty())
        {
            Store::ensureMainStoreForTeam($teamId);

            return $stats;
        }

        $mainLegacyBranchId = (int) ($branches->first()->id ?? 0);

        foreach ($branches as $branch)
        {
            $result = $this->upsertStoreFromLegacyBranchRow($teamId, $branch, $mainLegacyBranchId);
            if ($result['created'])
            {
                $stats['imported']++;
            } else
            {
                $stats['updated']++;
            }
        }

        return $stats;
    }

    /**
     * Create or update a Store from a legacy tienda_sucursales row.
     *
     * @param  object  $branch  Row with id, titulo, domicilio, numero, localidad, provincia, estado
     * @return array{created: bool, store: \App\Models\Store}
     */
    private function upsertStoreFromLegacyBranchRow(int $teamId, object $branch, int $mainLegacyBranchId): array
    {
        $storeCode = 'LEGACY-SUC-'.$branch->id;
        $name = $this->normalizeLegacyText((string) ($branch->titulo ?? ''));
        if ($name === '')
        {
            $name = 'Sucursal '.$branch->id;
        }

        $addressParts = [
            $this->normalizeLegacyText((string) ($branch->domicilio ?? '')),
            $this->normalizeLegacyText((string) ($branch->numero ?? '')),
            $this->normalizeLegacyText((string) ($branch->localidad ?? '')),
            $this->normalizeLegacyText((string) ($branch->provincia ?? '')),
        ];
        $address = trim(implode(', ', array_values(array_filter($addressParts, fn ($part) => $part !== ''))));
        $address = $address !== '' ? mb_substr($address, 0, 255) : null;

        $existingStore = Store::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->where('code', $storeCode)
            ->first();
        if (! $existingStore)
        {
            $existingStore = Store::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->where('name', mb_substr($name, 0, 255))
                ->first();
        }

        $storePayload = [
            'team_id' => $teamId,
            'name' => mb_substr($name, 0, 255),
            'code' => $storeCode,
            'address' => $address,
            'status' => (int) ($branch->estado ?? 0) > 0,
            'is_main' => $mainLegacyBranchId > 0 && (int) $branch->id === $mainLegacyBranchId,
        ];
        if ($existingStore && $existingStore->code !== $storeCode)
        {
            $codeTakenByAnotherStore = Store::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->where('code', $storeCode)
                ->where('id', '!=', $existingStore->id)
                ->exists();
            if ($codeTakenByAnotherStore)
            {
                unset($storePayload['code']);
            }
        }

        if (! $existingStore)
        {
            $store = Store::withoutGlobalScope('team')->create($storePayload);

            return ['created' => true, 'store' => $store];
        }

        $existingStore->update($storePayload);

        return ['created' => false, 'store' => $existingStore->fresh()];
    }

    /**
     * Resolve store id for a legacy product id_sucursal: use local row, or create from legacy DB, or main store.
     */
    private function resolveStoreIdForLegacyProductSucursal(int $teamId, int $legacySucursalId, int $mainStoreId): int
    {
        $code = 'LEGACY-SUC-'.$legacySucursalId;
        $existingId = Store::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->where('code', $code)
            ->value('id');
        if ($existingId)
        {
            return (int) $existingId;
        }

        if (! Schema::connection('mysql_legacy')->hasTable('tienda_sucursales'))
        {
            return $mainStoreId;
        }

        $branch = app('db')->connection('mysql_legacy')
            ->table('tienda_sucursales as ts')
            ->join('tienda_configuracion as tc', 'tc.id', '=', 'ts.id_tienda')
            ->where('tc.grupo', 513)
            ->where('tc.id_empresa', $teamId)
            ->where('ts.id', $legacySucursalId)
            ->select(
                'ts.id',
                'ts.titulo',
                'ts.domicilio',
                'ts.numero',
                'ts.localidad',
                'ts.provincia',
                'ts.estado',
                'ts.orden',
            )
            ->first();

        if (! $branch)
        {
            return $mainStoreId;
        }

        $mainLegacyBranchId = (int) (app('db')->connection('mysql_legacy')
            ->table('tienda_sucursales as ts')
            ->join('tienda_configuracion as tc', 'tc.id', '=', 'ts.id_tienda')
            ->where('tc.grupo', 513)
            ->where('tc.id_empresa', $teamId)
            ->orderBy('ts.orden')
            ->orderBy('ts.id')
            ->value('ts.id') ?? 0);

        $result = $this->upsertStoreFromLegacyBranchRow($teamId, $branch, $mainLegacyBranchId);

        return (int) $result['store']->id;
    }

    private function importStoreProductsForTeam(int $teamId): array
    {
        $stats = [
            'imported' => 0,
            'updated' => 0,
        ];

        if (! Schema::connection('mysql_legacy')->hasTable('tienda_productos'))
        {
            return $stats;
        }

        $columns = Schema::connection('mysql_legacy')->getColumnListing('tienda_productos');
        $selectColumns = ['tp.id', 'tp.id_tienda'];
        foreach (['id_categoria', 'id_sucursal', 'titulo', 'nombre', 'producto', 'descripcion', 'contenido1', 'valor', 'precio', 'estado', 'orden', 'imagen', 'foto', 'fecha_alta', 'fecha_modificacion'] as $column)
        {
            if (in_array($column, $columns, true))
            {
                $selectColumns[] = 'tp.'.$column;
            }
        }

        $products = app('db')->connection('mysql_legacy')
            ->table('tienda_productos as tp')
            ->join('tienda_configuracion as tc', 'tc.id', '=', 'tp.id_tienda')
            ->where('tc.grupo', 513)
            ->where('tc.id_empresa', $teamId)
            ->select($selectColumns)
            ->get();

        if ($products->isEmpty())
        {
            return $stats;
        }

        $currencyId = (int) (Currency::query()->where('code', 'ARS')->value('id')
            ?? Currency::query()->where('code', 'USD')->value('id')
            ?? 32);

        // Normalize previously imported legacy products to ARS for this team.
        Product::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->where('code', 'like', 'LEGACY-PROD-%')
            ->update(['currency_id' => $currencyId]);
        $mainStoreId = Store::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->where('is_main', true)
            ->value('id');
        if (! $mainStoreId)
        {
            $mainStoreId = Store::ensureMainStoreForTeam($teamId)->id;
        }

        foreach ($products as $legacyProduct)
        {
            $legacyProductId = (int) $legacyProduct->id;
            $legacyName = $legacyProduct->titulo ?? $legacyProduct->nombre ?? $legacyProduct->producto ?? null;
            $name = $this->normalizeLegacyText((string) ($legacyName ?? ''));
            if ($name === '')
            {
                $name = 'Producto '.$legacyProductId;
            }

            $categoryId = null;
            if (isset($legacyProduct->id_categoria))
            {
                $categoryId = Category::query()
                    ->where('team_id', $teamId)
                    ->where('module_id', app('db')->table('modules')->where('key', 'products')->value('id'))
                    ->where('data->legacy_store_category_id', (int) $legacyProduct->id_categoria)
                    ->value('id');
            }
            if (! $categoryId)
            {
                $categoryId = Category::query()
                    ->where('team_id', $teamId)
                    ->where('module_id', app('db')->table('modules')->where('key', 'products')->value('id'))
                    ->orderBy('id')
                    ->value('id');
            }
            if (! $categoryId)
            {
                continue;
            }

            $storeId = $mainStoreId;
            if (isset($legacyProduct->id_sucursal))
            {
                $storeId = $this->resolveStoreIdForLegacyProductSucursal(
                    $teamId,
                    (int) $legacyProduct->id_sucursal,
                    $mainStoreId,
                );
            }

            $priceValue = $legacyProduct->valor ?? $legacyProduct->precio ?? 0;
            $price = is_numeric($priceValue) ? (float) $priceValue : 0.0;
            $description = $this->normalizeLegacyText((string) ($legacyProduct->descripcion ?? $legacyProduct->contenido1 ?? ''));
            if ($description === '')
            {
                $description = $name;
            }

            $payload = [
                'team_id' => $teamId,
                'name' => mb_substr($name, 0, 255),
                'code' => 'LEGACY-PROD-'.$legacyProductId,
                'description' => $description,
                'short_description' => mb_substr($description, 0, 300),
                'price' => $price,
                'currency_id' => $currencyId,
                'category_id' => $categoryId,
                'store_id' => $storeId,
                'status' => ((int) ($legacyProduct->estado ?? 0)) > 0,
                'whatsapp_enabled' => true,
                'image' => $legacyProduct->imagen ?? $legacyProduct->foto ?? null,
                'created_at' => $legacyProduct->fecha_alta ?? now(),
                'updated_at' => $legacyProduct->fecha_modificacion ?? now(),
            ];

            $existing = Product::withoutGlobalScope('team')
                ->where('id', $legacyProductId)
                ->first();
            if ($existing && (int) $existing->team_id !== $teamId)
            {
                throw new \RuntimeException("Legacy product ID {$legacyProductId} is already used by team_id={$existing->team_id}. Cannot preserve ID for team_id={$teamId}.");
            }
            if (! $existing)
            {
                $existing = Product::withoutGlobalScope('team')
                    ->where('team_id', $teamId)
                    ->where('code', 'LEGACY-PROD-'.$legacyProductId)
                    ->first();
            }
            if ($existing && (int) $existing->id !== $legacyProductId)
            {
                throw new \RuntimeException("Legacy product code LEGACY-PROD-{$legacyProductId} exists with local product ID {$existing->id}. Cannot preserve ID {$legacyProductId}.");
            }

            if (! $existing)
            {
                $payloadWithId = $payload;
                $payloadWithId['id'] = $legacyProductId;
                Product::withoutGlobalScope('team')->forceCreate($payloadWithId);
                $stats['imported']++;
            } else
            {
                $merged = $this->mergePreservingLocal($payload, $existing, ['name', 'price', 'currency_id', 'category_id', 'store_id', 'status', 'image']);
                $existing->update($merged);
                $stats['updated']++;
            }
        }

        return $stats;
    }

    private function importStoreOrderContactsForTeam(int $teamId): array
    {
        $stats = [
            'imported' => 0,
            'updated' => 0,
        ];

        $ordersTable = $this->resolveLegacyOrdersTable();
        if (! $ordersTable)
        {
            return $stats;
        }

        $ordersColumns = Schema::connection('mysql_legacy')->getColumnListing($ordersTable);
        $selectColumns = ['o.id', 'tc.id_empresa as team_id'];
        foreach (['email', 'telefono', 'celular', 'nombre', 'apellido', 'id_contacto', 'fecha_alta', 'fecha_modificacion'] as $column)
        {
            if (in_array($column, $ordersColumns, true))
            {
                $selectColumns[] = 'o.'.$column;
            }
        }

        $rows = app('db')->connection('mysql_legacy')
            ->table($ordersTable.' as o')
            ->join('tienda_configuracion as tc', 'tc.id', '=', 'o.id_tienda')
            ->where('tc.grupo', 513)
            ->where('tc.id_empresa', $teamId)
            ->select($selectColumns)
            ->get();

        if ($rows->isEmpty())
        {
            return $stats;
        }

        $defaultResponsibleId = (int) (Team::withoutGlobalScopes()->where('id', $teamId)->value('user_id') ?? 1);

        foreach ($rows as $row)
        {
            $email = isset($row->email) ? strtolower(trim((string) $row->email)) : null;
            $phone = PhoneHelper::clean($row->celular ?? $row->telefono ?? null, '54', true);
            if (($email === null || $email === '') && ($phone === null || $phone === ''))
            {
                continue;
            }

            $name = $this->normalizeLegacyText((string) ($row->nombre ?? 'Cliente'));
            $surname = $this->normalizeLegacyText((string) ($row->apellido ?? ''));
            if ($name === '')
            {
                $name = 'Cliente';
            }

            $contactQuery = Contact::withoutGlobalScopes()->where('team_id', $teamId);
            if ($email)
            {
                $contactQuery->where('email', $email);
            } else
            {
                $contactQuery->where('phone', $phone);
            }

            $existingContact = $contactQuery->first();

            $payload = [
                'team_id' => $teamId,
                'name' => mb_substr($name, 0, 255),
                'surname' => $surname !== '' ? mb_substr($surname, 0, 255) : null,
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'source_id' => null,
                'country' => 32,
                'language' => 'es',
                'creator_id' => $defaultResponsibleId,
                'responsible_id' => $defaultResponsibleId,
                'status_id' => 5,
                'data' => json_encode([
                    'imported_from_store_orders' => true,
                    'legacy_order_table' => $ordersTable,
                    'legacy_contact_id' => $row->id_contacto ?? null,
                ]),
                'created_at' => $row->fecha_alta ?? now(),
                'updated_at' => $row->fecha_modificacion ?? now(),
            ];

            if (! $existingContact)
            {
                Contact::withoutGlobalScopes()->create($payload);
                $stats['imported']++;
            } else
            {
                $merged = $this->mergePreservingLocal($payload, $existingContact, ['name', 'surname', 'email', 'phone', 'responsible_id', 'status_id']);
                $existingContact->update($merged);
                $stats['updated']++;
            }
        }

        return $stats;
    }

    private function importStoreOrdersForTeam(int $teamId): array
    {
        $stats = [
            'imported' => 0,
            'updated' => 0,
        ];

        $ordersTable = $this->resolveLegacyOrdersTable();
        if (! $ordersTable)
        {
            return $stats;
        }

        $ordersColumns = Schema::connection('mysql_legacy')->getColumnListing($ordersTable);
        $selectColumns = ['o.id', 'tc.id_empresa as team_id'];
        foreach (['id_contacto', 'email', 'telefono', 'celular', 'total', 'importe_total', 'monto_total', 'observaciones', 'estado', 'fecha_alta', 'fecha_modificacion'] as $column)
        {
            if (in_array($column, $ordersColumns, true))
            {
                $selectColumns[] = 'o.'.$column;
            }
        }

        $rows = app('db')->connection('mysql_legacy')
            ->table($ordersTable.' as o')
            ->join('tienda_configuracion as tc', 'tc.id', '=', 'o.id_tienda')
            ->where('tc.grupo', 513)
            ->where('tc.id_empresa', $teamId)
            ->select($selectColumns)
            ->get();

        if ($rows->isEmpty())
        {
            return $stats;
        }

        $currencyId = (int) (Currency::query()->where('code', 'ARS')->value('id')
            ?? Currency::query()->where('code', 'USD')->value('id')
            ?? 32);

        foreach ($rows as $row)
        {
            $contactId = null;
            if (! empty($row->id_contacto))
            {
                $contactId = Contact::withoutGlobalScopes()
                    ->where('team_id', $teamId)
                    ->where('data->legacy_contact_id', (int) $row->id_contacto)
                    ->value('id');
            }

            if (! $contactId)
            {
                $email = isset($row->email) ? strtolower(trim((string) $row->email)) : null;
                $phone = PhoneHelper::clean($row->celular ?? $row->telefono ?? null, '54', true);
                $contactLookup = Contact::withoutGlobalScopes()->where('team_id', $teamId);
                if ($email)
                {
                    $contactLookup->where('email', $email);
                } elseif ($phone)
                {
                    $contactLookup->where('phone', $phone);
                }
                $contactId = $contactLookup->value('id');
            }

            $legacyOrderId = (int) $row->id;
            $orderNumber = LegacyOrderNumberHelper::fromLegacyPedidoId($teamId, $legacyOrderId);
            $legacyStyleOrderNumber = "LEGACY-TEAM-{$teamId}-ORD-{$legacyOrderId}";
            $total = $row->total ?? $row->importe_total ?? $row->monto_total ?? 0;
            $totalAmount = is_numeric($total) ? (float) $total : 0.0;

            $legacyEstado = $row->estado ?? null;
            $statuses = LegacyTiendaPedidoEstadoHelper::toHumanoOrderStatuses($legacyEstado);

            $metadata = [
                'imported_from_store_orders' => true,
                'legacy_order_table' => $ordersTable,
                'legacy_order_id' => $legacyOrderId,
                'legacy_estado' => $legacyEstado !== null && $legacyEstado !== '' ? (int) $legacyEstado : null,
                'legacy_estado_label' => LegacyTiendaPedidoEstadoHelper::legacyLabel($legacyEstado),
                'legacy_status' => $legacyEstado,
            ];

            $payload = [
                'team_id' => $teamId,
                'order_number' => $orderNumber,
                'contact_id' => $contactId ?: null,
                'total_amount' => $totalAmount,
                'currency_id' => $currencyId,
                'payment_status' => $statuses['payment_status'],
                'delivery_status' => $statuses['delivery_status'],
                'notes' => $row->observaciones ?? null,
                'metadata' => $metadata,
                'created_at' => $row->fecha_alta ?? now(),
                'updated_at' => $row->fecha_modificacion ?? now(),
            ];

            $existingOrder = Order::withoutGlobalScopes()
                ->where('team_id', $teamId)
                ->where(function ($query) use ($legacyOrderId, $legacyStyleOrderNumber)
                {
                    $query->where('metadata->legacy_order_id', $legacyOrderId)
                        ->orWhere('order_number', $legacyStyleOrderNumber);
                })
                ->first();

            if (! $existingOrder)
            {
                Order::withoutGlobalScopes()->create($payload);
                $stats['imported']++;
            } else
            {
                $merged = $this->mergePreservingLocal($payload, $existingOrder, ['contact_id', 'total_amount', 'payment_status', 'delivery_status', 'notes', 'order_number', 'metadata']);
                $existingOrder->update($merged);
                $stats['updated']++;
            }
        }

        return $stats;
    }

    private function resolveLegacyOrdersTable(): ?string
    {
        foreach (['tienda_pedidos', 'tienda_ordenes', 'pedidos_tienda'] as $candidate)
        {
            if (Schema::connection('mysql_legacy')->hasTable($candidate))
            {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizeLegacyText(string $value): string
    {
        $value = trim($value);

        if ($value === '')
        {
            return $value;
        }

        if (! mb_check_encoding($value, 'UTF-8'))
        {
            return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
        }

        // Fix common mojibake patterns like "TucumÃ¡n".
        if (str_contains($value, 'Ã') || str_contains($value, 'Â'))
        {
            return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
        }

        return $value;
    }
}
