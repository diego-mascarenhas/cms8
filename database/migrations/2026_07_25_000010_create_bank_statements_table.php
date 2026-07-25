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
            $table->string('provider', 64);
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->string('source', 32)->default('api');
            $table->string('original_filename')->nullable();
            $table->timestamps();

            $table->unique(
                ['team_id', 'provider', 'period_year', 'period_month'],
                'bank_statements_team_provider_period_unique',
            );
            $table->index(['team_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statements');
    }
};
