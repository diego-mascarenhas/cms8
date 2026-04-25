<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stripe_subscriptions') && ! Schema::hasTable('service_syncs'))
        {
            Schema::rename('stripe_subscriptions', 'service_syncs');
        }

        if (! Schema::hasTable('service_syncs'))
        {
            return;
        }

        Schema::table('service_syncs', function (Blueprint $table)
        {
            if (! Schema::hasColumn('service_syncs', 'provider'))
            {
                $table->string('provider', 32)->default('stripe')->after('stripe_id');
            }
        });

        DB::table('service_syncs')
            ->whereNull('provider')
            ->update(['provider' => 'stripe']);

        if (! $this->indexExists('service_syncs', 'service_syncs_team_provider_idx'))
        {
            Schema::table('service_syncs', function (Blueprint $table)
            {
                $table->index(['team_id', 'provider'], 'service_syncs_team_provider_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_syncs'))
        {
            return;
        }

        if (Schema::hasColumn('service_syncs', 'provider'))
        {
            $hasTeamProviderIndex = $this->indexExists('service_syncs', 'service_syncs_team_provider_idx');
            Schema::table('service_syncs', function (Blueprint $table)
            use ($hasTeamProviderIndex)
            {
                if ($hasTeamProviderIndex)
                {
                    $table->dropIndex('service_syncs_team_provider_idx');
                }
                $table->dropColumn('provider');
            });
        }

        if (! Schema::hasTable('stripe_subscriptions'))
        {
            Schema::rename('service_syncs', 'stripe_subscriptions');
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql')
        {
            $database = DB::getDatabaseName();
            $rows = DB::select(
                'SELECT 1
                 FROM information_schema.statistics
                 WHERE table_schema = ?
                   AND table_name = ?
                   AND index_name = ?
                 LIMIT 1',
                [$database, $table, $index]
            );

            return $rows !== [];
        }

        if ($driver === 'pgsql')
        {
            $rows = DB::select(
                'SELECT 1
                 FROM pg_indexes
                 WHERE schemaname = current_schema()
                   AND tablename = ?
                   AND indexname = ?
                 LIMIT 1',
                [$table, $index]
            );

            return $rows !== [];
        }

        return false;
    }
};
