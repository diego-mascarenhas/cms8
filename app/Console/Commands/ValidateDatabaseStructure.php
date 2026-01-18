<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ValidateDatabaseStructure extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:validate-structure';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validates database structure against migrations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Validating database structure...');
        $this->newLine();

        $issues = [];
        $tables = $this->getExpectedTables();

        foreach ($tables as $table => $columns)
        {
            if (! Schema::hasTable($table))
            {
                $issues[] = [
                    'type' => 'missing_table',
                    'table' => $table,
                    'message' => "Table '{$table}' does not exist",
                ];

                continue;
            }

            foreach ($columns as $column => $type)
            {
                if (! Schema::hasColumn($table, $column))
                {
                    $issues[] = [
                        'type' => 'missing_column',
                        'table' => $table,
                        'column' => $column,
                        'expected_type' => $type,
                        'message' => "Column '{$column}' does not exist in table '{$table}'",
                    ];
                } else
                {
                    // Check column type
                    $actualType = $this->getColumnType($table, $column);
                    if ($actualType && ! $this->isTypeCompatible($type, $actualType))
                    {
                        $issues[] = [
                            'type' => 'type_mismatch',
                            'table' => $table,
                            'column' => $column,
                            'expected_type' => $type,
                            'actual_type' => $actualType,
                            'message' => "Column '{$column}' in table '{$table}' has type '{$actualType}' but expected '{$type}'",
                        ];
                    }
                }
            }
        }

        if (empty($issues))
        {
            $this->info('✓ Database structure is valid!');
            $this->newLine();

            return Command::SUCCESS;
        }

        $this->error('Found '.count($issues).' issue(s):');
        $this->newLine();

        foreach ($issues as $issue)
        {
            $this->line("  [{$issue['type']}] {$issue['message']}");
            if (isset($issue['expected_type']))
            {
                $this->line("    Expected type: {$issue['expected_type']}");
            }
            if (isset($issue['actual_type']))
            {
                $this->line("    Actual type: {$issue['actual_type']}");
            }
        }

        $this->newLine();
        $this->warn('Run migrations to fix these issues: php artisan migrate');

        return Command::FAILURE;
    }

    /**
     * Get expected tables and columns from migrations
     */
    private function getExpectedTables(): array
    {
        return [
            'subscriptions' => [
                'id' => 'bigint',
                'user_id' => 'bigint',
                'team_id' => 'bigint',
                'type' => 'string',
                'stripe_id' => 'string',
                'stripe_status' => 'string',
                'stripe_price' => 'string',
                'quantity' => 'integer',
                'trial_ends_at' => 'timestamp',
                'ends_at' => 'timestamp',
                'data' => 'json',
                'created_at' => 'timestamp',
                'updated_at' => 'timestamp',
            ],
            'subscription_products' => [
                'id' => 'bigint',
                'stripe_id' => 'string',
                'stripe_product' => 'string',
                'stripe_price' => 'string',
                'name' => 'string',
                'description' => 'text',
                'active' => 'boolean',
                'category' => 'string',
                'plan' => 'string',
                'type' => 'string',
                'currency' => 'string',
                'unit_amount' => 'decimal',
                'recurring_interval' => 'string',
                'recurring_interval_count' => 'integer',
                'metadata' => 'json',
                'last_synced_at' => 'timestamp',
                'raw_payload' => 'json',
                'created_at' => 'timestamp',
                'updated_at' => 'timestamp',
            ],
            'teams' => [
                'id' => 'bigint',
                'user_id' => 'bigint',
                'name' => 'string',
                'personal_team' => 'boolean',
                'stripe_id' => 'string',
                'created_at' => 'timestamp',
                'updated_at' => 'timestamp',
            ],
        ];
    }

    /**
     * Get actual column type from database
     */
    private function getColumnType(string $table, string $column): ?string
    {
        try
        {
            $connection = DB::connection();
            $database = $connection->getDatabaseName();
            $result = DB::select('
				SELECT DATA_TYPE, COLUMN_TYPE
				FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = ?
				AND TABLE_NAME = ?
				AND COLUMN_NAME = ?
			', [$database, $table, $column]);

            if (! empty($result))
            {
                return strtolower($result[0]->DATA_TYPE);
            }
        } catch (\Exception $e)
        {
            // Ignore errors
        }

        return null;
    }

    /**
     * Check if actual type is compatible with expected type
     */
    private function isTypeCompatible(string $expected, string $actual): bool
    {
        $compatibility = [
            'bigint' => ['bigint', 'int'],
            'integer' => ['int', 'bigint', 'integer'],
            'string' => ['varchar', 'char', 'text', 'string'],
            'text' => ['text', 'longtext', 'mediumtext', 'varchar'],
            'boolean' => ['tinyint', 'boolean', 'bool'],
            'decimal' => ['decimal', 'double', 'float'],
            'timestamp' => ['timestamp', 'datetime'],
            'json' => ['json', 'text', 'longtext'],
        ];

        if (! isset($compatibility[$expected]))
        {
            return true; // Unknown type, assume compatible
        }

        return in_array($actual, $compatibility[$expected]);
    }
}
