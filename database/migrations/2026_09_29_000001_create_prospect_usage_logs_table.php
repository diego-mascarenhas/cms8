<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_usage_logs', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('source', 64)->default('import');
            $table->unsignedInteger('count')->default(1);
            $table->timestamp('consumed_at');
            $table->timestamps();

            $table->index(['team_id', 'consumed_at'], 'pul_team_consumed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_usage_logs');
    }
};
