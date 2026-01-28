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
        Schema::create('token_usage_logs', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('module_id')->nullable();
            $table->string('service')->index(); // AIAssistanceService, ClaudeProposalValidator
            $table->integer('json_size')->default(0);
            $table->integer('toon_size')->default(0);
            $table->integer('json_tokens')->default(0);
            $table->integer('toon_tokens')->default(0);
            $table->integer('savings_percentage')->default(0);
            $table->boolean('used_toon')->default(false);
            $table->timestamps();

            $table->foreign('module_id')->references('id')->on('modules')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_usage_logs');
    }
};
