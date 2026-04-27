<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table)
        {
            $table->enum('source_provider', ['manual', 'stripe', 'mercadopago', 'paypal'])
                ->default('manual')
                ->after('status');
            $table->string('source_reference_id')->nullable()->after('source_provider');
            $table->timestamp('source_synced_at')->nullable()->after('source_reference_id');

            $table->index('source_provider');
            $table->index('source_reference_id');
            $table->unique(['source_provider', 'source_reference_id'], 'invoices_source_provider_reference_unique');
        });

        DB::table('invoices')
            ->whereNull('source_provider')
            ->update(['source_provider' => 'manual']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table)
        {
            $table->dropUnique('invoices_source_provider_reference_unique');
            $table->dropIndex(['source_provider']);
            $table->dropIndex(['source_reference_id']);
            $table->dropColumn(['source_provider', 'source_reference_id', 'source_synced_at']);
        });
    }
};
