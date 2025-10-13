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
        Schema::create('contact_valorations', function (Blueprint $table)
        {
            $table->unsignedSmallInteger('id')->primary();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->string('name', 255);
            $table->string('icon', 10)->default('🔘');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_valorations');
    }
};
