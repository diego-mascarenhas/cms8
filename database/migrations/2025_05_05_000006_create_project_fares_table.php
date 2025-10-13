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
        Schema::create('project_fares', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('fare_id')->constrained('fares')->onDelete('cascade');
            $table->string('source_language_code', 10);
            $table->string('target_language_code', 10);
            $table->integer('quantity'); // Cantidad (ej: 123)
            $table->string('unit')->nullable(); // Unidad (ej: "min/pag")
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['project_id', 'fare_id']);
            $table->index(['source_language_code', 'target_language_code']); // Index compuesto
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_fares');
    }
};
