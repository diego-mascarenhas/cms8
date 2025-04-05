<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;
use Hash;
use Log;
use Exception;

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
                ->select('servicios.id', 'servicios.id_empresa', 'servicios.id_categoria', 
                         'servicios.descripcion', 'servicios.valor', 'servicios.frecuencia',
                         'servicios.operacion', 'servicios_hosting.user', 'servicios.estado'),

            // Add other cases for different types...
            
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
                // ... other cases
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
            $query = DB::connection('mysql_tmp')
                ->table('servicios')
                ->join('servicios_hosting', 'servicios.id', '=', 'servicios_hosting.id_servicio')
                ->where('servicios.grupo', env('CMS_GROUP'))
                ->where('servicios.estado', '>', 0)
                ->where('servicios.operacion', 'V') // Solo importar servicios de venta
                ->select('servicios.*', 'servicios_hosting.user');

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

                // Verificar si existe la categoría o asignar 4000 como predeterminada
                $categoryId = 4000; // Categoría predeterminada
                $categoryExists = DB::table('categories')->where('id', $data->id_categoria)->exists();
                if ($categoryExists) {
                    $categoryId = $data->id_categoria;
                } else {
                    $this->warn("Category with ID {$data->id_categoria} not found, assigning default category 4000 for service {$data->id}");
                }

                $cleaned_description = strip_tags($data->descripcion);

                $serviceData = [
                    'id' => $data->id,
                    'category_id' => 4000, //$categoryId,
                    'enterprise_id' => $data->id_empresa,
                    'operation' => 'Sell', // Siempre será Sell ya que filtramos por 'V'
                    'desctiption' => $cleaned_description, // Respetar el nombre del campo como está en la migración
                    'data' => json_encode(['user' => $data->user]),
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

    // Add other import methods...
} 