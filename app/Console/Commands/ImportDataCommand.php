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
                ->select('id', 'email', 'nombre', 'apellido', 'estado'),

            '2. Categories' => DB::connection('mysql_tmp')->table('categorias_generales')
                ->where('grupo', env('CMS_GROUP'))
                ->select('id', 'categoria', 'padre', 'estado'),

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
                ->where('id', '>', 2);

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
                    'country' => 724,
                    'language' => 'es',
                    'creator_id' => 1,
                    'responsible_id' => null,
                    'data' => json_encode([
                        'phone' => $cleaned_phone,
                        'email' => $data->email,
                    ]),
                    'status_id' => 1,
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

    // Add other import methods...
} 