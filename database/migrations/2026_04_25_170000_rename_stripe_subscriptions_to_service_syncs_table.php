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

        DB::statement('CREATE INDEX IF NOT EXISTS service_syncs_team_provider_idx ON service_syncs (team_id, provider)');
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_syncs'))
        {
            return;
        }

        if (Schema::hasColumn('service_syncs', 'provider'))
        {
            Schema::table('service_syncs', function (Blueprint $table)
            {
                $table->dropColumn('provider');
            });
        }

        if (! Schema::hasTable('stripe_subscriptions'))
        {
            Schema::rename('service_syncs', 'stripe_subscriptions');
        }
    }
};
