<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table)
        {
            $table->enum('source_provider', ['manual', 'stripe', 'mercadopago', 'paypal', 'transfer'])
                ->default('manual')
                ->after('status');
            $table->string('source_reference_id')->nullable()->after('source_provider');
            $table->timestamp('source_synced_at')->nullable()->after('source_reference_id');

            $table->index('source_provider');
            $table->index('source_reference_id');
            $table->unique(['source_provider', 'source_reference_id'], 'payments_source_provider_reference_unique');
        });

        DB::table('payments')
            ->whereNull('source_provider')
            ->update(['source_provider' => 'manual']);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table)
        {
            $table->dropUnique('payments_source_provider_reference_unique');
            $table->dropIndex(['source_provider']);
            $table->dropIndex(['source_reference_id']);
            $table->dropColumn(['source_provider', 'source_reference_id', 'source_synced_at']);
        });
    }
};
