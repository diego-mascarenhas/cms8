<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_lines', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('bank_statement_id')->constrained('bank_statements')->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('reference')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('ARS');
            $table->string('payer_name')->nullable();
            $table->string('payer_id_type', 64)->nullable();
            $table->string('payer_id_number', 64)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('payment_sync_id')->nullable()->constrained('payment_syncs')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('match_status', 32)->nullable();
            $table->timestamp('reconcile_dismissed_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(
                ['bank_statement_id', 'external_id'],
                'bank_statement_lines_statement_external_unique',
            );
            $table->index(['payment_sync_id']);
            $table->index(['payment_id']);
            $table->index(['payer_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
    }
};
