<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupAndRestoreTables extends Command
{
    protected $signature = 'db:backup-restore {operation} {--tables=*}';
    protected $description = 'Backup and restore specific tables';

    public function handle()
    {
        $operation = $this->argument('operation');
        $tables = $this->option('tables');
        
        if (empty($tables)) {
            $this->error('No tables specified for backup/restore.');
            return;
        }

        // Directorio de backup
        $backupPath = storage_path('app/backups/');

        // Crear directorio si no existe
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true); // Crear el directorio con permisos adecuados
            $this->info("Created backup directory: $backupPath");
        }

        if ($operation === 'backup') {
            $this->backupTables($tables, $backupPath);
        } elseif ($operation === 'restore') {
            $this->restoreTables($tables, $backupPath);
        } else {
            $this->error('Invalid operation. Use "backup" or "restore".');
        }
    }

    private function backupTables(array $tables, string $backupPath)
    {
        $dbConfig = config('database.connections.mysql');

        foreach ($tables as $table) {
            $backupFile = $backupPath . "backup_{$table}.sql";
            $command = sprintf(
                'mysqldump -h %s -P %d -u %s -p%s %s %s > %s',
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['username'],
                $dbConfig['password'],
                $dbConfig['database'],
                escapeshellarg($table), // Escapar el nombre de la tabla
                escapeshellarg($backupFile) // Escapar el nombre del archivo
            );

            exec($command, $output, $returnVar);

            if ($returnVar === 0) {
                $this->info("Backup successful for table $table: $backupFile");
            } else {
                $this->error("Backup failed for table $table");
            }
        }
    }

    private function restoreTables(array $tables, string $backupPath)
    {
        $dbConfig = config('database.connections.mysql');

        foreach ($tables as $table) {
            $backupFile = $backupPath . "backup_{$table}.sql";
            if (!file_exists($backupFile)) {
                $this->error("Backup file not found for table $table: $backupFile");
                continue;
            }

            $command = sprintf(
                'mysql -h %s -P %d -u %s -p%s %s < %s',
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['username'],
                $dbConfig['password'],
                $dbConfig['database'],
                escapeshellarg($backupFile) // Escapar el nombre del archivo
            );

            exec($command, $output, $returnVar);

            if ($returnVar === 0) {
                $this->info("Restore successful for table $table");
            } else {
                $this->error("Restore failed for table $table");
            }
        }
    }
}
