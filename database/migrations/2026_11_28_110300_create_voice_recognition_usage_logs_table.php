<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_recognition_usage_logs', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 64)->default('app');
            $table->string('external_ref', 160)->nullable();
            $table->unsignedInteger('seconds');
            $table->timestamp('recognized_at');
            $table->timestamps();

            $table->unique(['team_id', 'external_ref'], 'vrul_team_ref_unique');
            $table->index(['team_id', 'recognized_at'], 'vrul_team_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_recognition_usage_logs');
    }
};
