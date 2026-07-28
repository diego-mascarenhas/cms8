<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statements', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_account_id')
                ->nullable()
                ->constrained('payment_accounts')
                ->nullOnDelete();
            $table->string('provider', 64);
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->string('source', 32)->default('api');
            $table->string('original_filename')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('disk', 32)->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->json('validation_summary')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'provider']);
            $table->index(
                ['team_id', 'provider', 'period_year', 'period_month'],
                'bank_statements_team_provider_period_idx',
            );
            $table->index(
                ['payment_account_id', 'period_year', 'period_month'],
                'bank_statements_account_period_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statements');
    }
};
