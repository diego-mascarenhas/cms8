<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;
use Hash;
use Log;
use Exception;
use App\Models\Module;

class ImportDataCommand extends Command
{
    protected $signature = 'import:interactive';
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
                ]
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
            10 => '10. Communications',
            11 => '11. Import All',
            12 => '12. Exit'
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
                5 => '5. Back to Main Menu'
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
            $headers = array_keys((array)$data->first());
            $rows = $data->map(function($item) {
                return (array)$item;
            })->toArray();

            $this->table($headers, $rows);
            
            $this->info("Total records: " . $data->count());

        } catch (\Exception $e) {
            $this->error("Error previewing data: " . $e->getMessage());
        }
    }

    protected function getData($type, $id = null)
    {
        $query = match($type) {
            '1. Users' => DB::connection('mysql_tmp')->table('contactos')
                ->whereNotNull('email')
                ->where('grupo', env('CMS_GROUP'))
                ->whereNotNull('id_empresa')
                ->where('area_privada', '!=', 6)
                ->where('id', '>', 2)
                ->whereNotNull('nombre')
                ->where('nombre', '!=', '')
                ->whereRaw("TRIM(nombre) != ''")
                ->select('id', 'email', 'nombre', 'apellido', 'estado'),

            '2. Categories' => DB::connection('mysql_tmp')->table('categorias_generales')
                ->where('grupo', env('CMS_GROUP'))
                ->where('padre', 10)
                ->select('id', 'categoria', 'padre', 'estado'),

            '5. Enterprises' => DB::connection('mysql_tmp')->table('empresas')
                ->where('grupo', env('CMS_GROUP'))
                ->where('id_categoria', 2)
                ->where('estado', 2)
                ->select('id', 'empresa', 'id_categoria', 'telefono', 'email', 'estado'),

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
                ->where('grupo', env('CMS_GROUP'))
                ->select('id', 'id_empresa', 'id_factura_tipo', 'estado'),

            '9. Payments' => DB::connection('mysql_tmp')->table('pagos')
                ->where('grupo', env('CMS_GROUP'))
                ->select('id', 'id_empresa', 'id_forma_pago', 'estado'),

            '10. Communications' => DB::connection('mysql_tmp')->table('comunicaciones')
                ->where('grupo', env('CMS_GROUP'))
                ->select('id', 'id_empresa', 'id_comunicacion_tipo', 'estado'),

            default => throw new \Exception('Invalid type selected'),
        };

        if ($id) {
            $query->where('id', $id);
        }

        return $query->limit(10)->get(); // Limit preview to 10 records
    }

    public function handle()
    {
        $this->info('=== Database Import Tool ===');

        // Test database connection first
        if (!$this->testDatabaseConnection()) {
            $this->error('Exiting due to database connection failure.');
            return 1;
        }

        while (true) {
            $choice = $this->showMainMenu();

            if ($choice === '12. Exit') {
                $this->info('Goodbye!');
                break;
            }

            if ($choice === '11. Import All') {
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
            $result = match($type) {
                '1. Users' => $this->importUsers($id),
                '2. Categories' => $this->importCategories($id),
                '5. Enterprises' => $this->importEnterprises($id),
                '6. Services' => $this->importServices($id),
                '7. Projects' => $this->importProjects($id),
                '8. Invoices' => $this->importInvoices($id),
                '9. Payments' => $this->importPayments($id),
                '10. Communications' => $this->importCommunications($id),
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
            }
        } catch (\Exception $e) {
            $this->error("Error during import: " . $e->getMessage());
        }
    }

    protected function importUsers($id = null)
    {
        $stats = [
            'imported' => 0,
            'updated' => 0,
            'message' => null
        ];

        try {
            $query = DB::connection('mysql_tmp')->table('contactos')
                ->whereNotNull('email')
                ->where('grupo', env('CMS_GROUP'))
                ->whereNotNull('id_empresa')
                ->where('area_privada', '!=', 6)
                ->where('id', '>', 2)
                ->whereNotNull('nombre')
                ->where('nombre', '!=', '')
                ->whereRaw("TRIM(nombre) != ''");

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

            foreach ($contacts as $data) {
                $existingContact = DB::table('contacts')->where('id', $data->id)->first();

                $phone = $data->celular ?? $data->telefono ?? null;
                $cleaned_phone = $phone ? preg_replace('/\D/', '', $phone) : null;
                if (!empty($cleaned_phone) && strpos($cleaned_phone, '54') !== 0) {
                    $cleaned_phone = '54' . $cleaned_phone;
                }

                $contactData = [
                    'id' => $data->id,
                    'team_id' => env('CMS_TEAM_ID'),
                    'user_id' => null,
                    'name' => $data->nombre . ' ' . $data->apellido,
                    'source_id' => null,
                    'birthday' => null,
                    'profile' => null,
                    'country' => 32,
                    'language' => 'es',
                    'creator_id' => 1,
                    'responsible_id' => null,
                    'data' => json_encode([
                        'phone' => $cleaned_phone,
                        'email' => $data->email,
                    ]),
                    'status_id' => 5,
                    'created_at' => $data->fecha_alta,
                    'updated_at' => $data->fecha_modificacion,
                ];

                if (!$existingContact) {
                    DB::table('contacts')->insert($contactData);
                    $stats['imported']++;
                } else {
                    DB::table('contacts')->where('id', $existingContact->id)->update($contactData);
                    $stats['updated']++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

        } catch (\Exception $e) {
            $this->newLine();
            throw new \Exception("Error importing contacts: " . $e->getMessage());
        }

        return $stats;
    }

    protected function importEnterprises($id = null)
    {
        $stats = [
            'imported' => 0,
            'updated' => 0,
            'message' => null
        ];

        try {
            $query = DB::connection('mysql_tmp')->table('empresas')
                ->where('grupo', env('CMS_GROUP'))
                ->where('id_categoria', 2)
                ->where('estado', 2);

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
                    $type_id = 2; // Supplier
                } elseif ($data->id_categoria == 100 || $data->id_categoria == 464) {
                    $type_id = 3; // Partnership
                } else {
                    $type_id = 1; // Client
                }

                // Obtenemos el ID del contacto responsable
                $contactId = null;
                if (!empty($data->id_contacto)) {
                    // Verificamos si existe directamente en la tabla contacts
                    $contactExists = DB::table('contacts')->where('id', $data->id_contacto)->exists();
                    
                    if ($contactExists) {
                        $contactId = $data->id_contacto;
                        $this->info("Found contact with ID {$contactId} for enterprise {$data->id}");
                    } else {
                        // Si no existe, lo importamos desde la base de datos original
                        $contactData = DB::connection('mysql_tmp')
                            ->table('contactos')
                            ->where('id', $data->id_contacto)
                            ->first();
                        
                        if ($contactData) {
                            // Verificar si el contacto tiene nombre
                            if (!empty(trim($contactData->nombre))) {
                                $phone = $contactData->celular ?? $contactData->telefono ?? null;
                                $cleaned_phone = $phone ? preg_replace('/\D/', '', $phone) : null;
                                if (!empty($cleaned_phone) && strpos($cleaned_phone, '54') !== 0) {
                                    $cleaned_phone = '54' . $cleaned_phone;
                                }
                                
                                $newContactData = [
                                    'id' => $contactData->id,
                                    'team_id' => env('CMS_TEAM_ID'),
                                    'user_id' => null,
                                    'name' => $contactData->nombre . ' ' . $contactData->apellido,
                                    'source_id' => null,
                                    'birthday' => null,
                                    'profile' => null,
                                    'engagment' => 'temperate',
                                    'country' => 32,
                                    'language' => 'es',
                                    'creator_id' => 1,
                                    'responsible_id' => null,
                                    'data' => json_encode([
                                        'phone' => $cleaned_phone,
                                        'email' => $contactData->email,
                                    ]),
                                    'status_id' => 5,
                                    'created_at' => $contactData->fecha_alta,
                                    'updated_at' => $contactData->fecha_modificacion,
                                ];
                                
                                DB::table('contacts')->insert($newContactData);
                                $contactId = $contactData->id;
                                $this->info("Contact with ID {$contactId} was imported for enterprise {$data->id}");
                            } else {
                                $this->warn("Contact with ID {$data->id_contacto} has no name, skipping import");
                            }
                        } else {
                            $this->warn("Contact with ID {$data->id_contacto} not found in source database");
                        }
                    }
                }

                $enterpriseData = [
                    'id' => $data->id,
                    'name' => $data->empresa,
                    'type_id' => $type_id,
                    'responsible_id' => $contactId,
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
                    //'payment_type_id' => $data->id_forma_pago ?? null,
                    //'invoice_type_id' => $data->id_factura_tipo ?? null,
                    'status_id' => $data->estado,
                    'created_at' => $data->fecha_alta,
                    'updated_at' => $data->fecha_modificacion,
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
            throw new \Exception("Error importing enterprises: " . $e->getMessage());
        }

        return $stats;
    }

    protected function importServices($id = null)
    {
        $stats = [
            'imported' => 0,
            'updated' => 0,
            'message' => null
        ];

        try {
            // Buscar el módulo de servicios
            $serviceModule = \App\Models\Module::where('key', 'services')->first();
            
            if (!$serviceModule) {
                throw new \Exception("El módulo 'services' no existe. Ejecute primero el seeder de módulos.");
            }
            
            $query = DB::connection('mysql_tmp')
                ->table('servicios')
                ->join('servicios_hosting', 'servicios.id', '=', 'servicios_hosting.id_servicio')
                ->where('servicios.grupo', env('CMS_GROUP'))
                ->where('servicios.estado', '>', 0)
                ->where('servicios.operacion', 'V') // Solo importar servicios de venta
                ->select('servicios.*', 'servicios_hosting.*');

            if ($id) {
                $query->where('servicios.id', $id);
            }

            $services = $query->get();

            if ($services->isEmpty()) {
                $stats['message'] = 'No services found matching the criteria.';
                return $stats;
            }

            $bar = $this->output->createProgressBar(count($services));
            $bar->start();

            // Pre-cargar empresas existentes en un array para verificación más rápida
            $enterpriseIds = $services->pluck('id_empresa')->unique()->toArray();
            $existingEnterprises = DB::table('enterprises')->whereIn('id', $enterpriseIds)->pluck('id')->toArray();
            
            $this->info("Verificando " . count($enterpriseIds) . " empresas...");
            $this->info("Encontradas " . count($existingEnterprises) . " empresas existentes");

            foreach ($services as $data) {
                $existingService = DB::table('services')->where('id', $data->id)->first();

                // Verificar si existe la empresa
                $enterpriseExists = in_array($data->id_empresa, $existingEnterprises);
                if (!$enterpriseExists) {
                    $this->warn("Enterprise with ID {$data->id_empresa} not found, skipping service {$data->id}");
                    $bar->advance();
                    continue;
                }

                // Verificar si existe la categoría o asignar una predeterminada (4000)
                $categoryId = 4000; // Categoría predeterminada
                $categoryExists = DB::table('categories')
                    ->where('id', $data->id_categoria)
                    ->exists();
                
                if ($categoryExists) {
                    // Si la categoría existe, verificamos que tenga el module_id del módulo de servicios
                    $category = DB::table('categories')->where('id', $data->id_categoria)->first();
                    
                    if (!$category->module_id) {
                        // Si no tiene module_id, actualizamos la categoría
                        DB::table('categories')
                            ->where('id', $data->id_categoria)
                            ->update(['module_id' => $serviceModule->id]);
                        
                        $this->info("Categoría {$data->id_categoria} actualizada con module_id {$serviceModule->id}");
                    }
                    
                    $categoryId = $data->id_categoria;
                } else {
                    $this->warn("Categoría con ID {$data->id_categoria} no encontrada, asignando categoría predeterminada 4000 para el servicio {$data->id}");
                }

                $cleaned_description = strip_tags($data->descripcion);
                
                // Crear un array con todos los campos de servicios_hosting
                $hostingData = [];
                foreach ((array)$data as $key => $value) {
                    // Si es un campo de servicios_hosting (no está en la tabla principal de servicios)
                    // El formato puede ser 'servicios_hosting.campo' o simplemente 'campo' dependiendo del driver
                    if (strpos($key, 'servicios_hosting.') === 0 || 
                        !in_array($key, ['id', 'id_empresa', 'id_categoria', 'descripcion', 'valor', 
                                       'frecuencia', 'operacion', 'estado', 'fecha_alta', 
                                       'fecha_modificacion', 'ultima', 'proxima', 'caduca',
                                       'id_moneda', 'descuento'])) {
                        // Quitar el prefijo si existe
                        $cleanKey = str_replace('servicios_hosting.', '', $key);
                        
                        // Si el campo es 'data' y es un JSON válido, lo decodificamos para evitar doble codificación
                        if ($cleanKey === 'data' && $value && is_string($value) && $this->isJson($value)) {
                            $decodedData = json_decode($value, true);
                            // Mezclamos los datos decodificados con el array principal
                            if (is_array($decodedData)) {
                                foreach ($decodedData as $dataKey => $dataValue) {
                                    $hostingData[$dataKey] = $dataValue;
                                }
                            }
                        } else {
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

                try {
                    if (!$existingService) {
                        DB::table('services')->insert($serviceData);
                        $stats['imported']++;
                        $this->info("Service with ID {$data->id} imported");
                    } else {
                        DB::table('services')->where('id', $existingService->id)->update($serviceData);
                        $stats['updated']++;
                        $this->info("Service with ID {$data->id} updated");
                    }
                } catch (\Exception $e) {
                    $this->error("Error al importar servicio {$data->id}: " . $e->getMessage());
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        } catch (\Exception $e) {
            $this->newLine();
            throw new \Exception("Error importing services: " . $e->getMessage());
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
            'message' => null
        ];

        try {
            // Buscar el módulo de servicios para asignar a las categorías
            $serviceModule = \App\Models\Module::where('key', 'services')->first();
            
            if (!$serviceModule) {
                $this->warn("El módulo 'services' no existe. Las categorías se importarán sin módulo asignado.");
            }

            $query = DB::connection('mysql_tmp')->table('categorias_generales')
                ->where('grupo', env('CMS_GROUP'))
                ->where('padre', 10)
                ->where('estado', '>', 0);

            if ($id) {
                $query->where('id', $id);
            }

            $categories = $query->get();

            if ($categories->isEmpty()) {
                $stats['message'] = 'No se encontraron categorías para importar.';
                return $stats;
            }

            $bar = $this->output->createProgressBar(count($categories));
            $bar->start();

            foreach ($categories as $data) {
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

                if (!$existingCategory) {
                    DB::table('categories')->insert($categoryData);
                    $stats['imported']++;
                    $this->info("Categoría {$data->id} importada: {$data->categoria}");
                } else {
                    DB::table('categories')->where('id', $existingCategory->id)->update($categoryData);
                    $stats['updated']++;
                    $this->info("Categoría {$data->id} actualizada: {$data->categoria}");
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        } catch (\Exception $e) {
            $this->newLine();
            throw new \Exception("Error importando categorías: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Helper method to check if a string is valid JSON
     */
    protected function isJson($string) {
        if (!is_string($string)) return false;
        
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    // Add other import methods...
} 