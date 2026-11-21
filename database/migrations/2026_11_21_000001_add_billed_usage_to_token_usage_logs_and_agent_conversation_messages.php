<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('token_usage_logs', function (Blueprint $table)
        {
            $table->json('billed_usage')->nullable()->after('used_toon');
            $table->foreignId('usage_invoice_id')->nullable()->after('billed_usage')->constrained('team_usage_invoices')->nullOnDelete();
        });

        Schema::table('agent_conversation_messages', function (Blueprint $table)
        {
            $table->json('billed_usage')->nullable()->after('usage');
            $table->foreignId('usage_invoice_id')->nullable()->after('billed_usage')->constrained('team_usage_invoices')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_conversation_messages', function (Blueprint $table)
        {
            $table->dropConstrainedForeignId('usage_invoice_id');
            $table->dropColumn('billed_usage');
        });

        Schema::table('token_usage_logs', function (Blueprint $table)
        {
            $table->dropConstrainedForeignId('usage_invoice_id');
            $table->dropColumn('billed_usage');
        });
    }
};
