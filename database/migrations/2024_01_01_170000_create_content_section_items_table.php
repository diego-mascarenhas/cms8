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
        Schema::create('content_section_items', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('content_id');
            $table->string('section_key', 100); // Identificador único de la sección (ej: 'intro', 'features', 'testimonials')
            $table->string('section_label', 255)->nullable(); // Etiqueta legible de la sección
            $table->json('content')->nullable(); // Contenido multiidioma de la sección
            $table->unsignedTinyInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('data')->nullable(); // Datos adicionales de la sección
            $table->timestamps();
            $table->softDeletes();

            $table->index(['content_id', 'is_active']);
            $table->index(['content_id', 'order']);
            $table->unique(['content_id', 'section_key'], 'content_section_unique');

            $table->foreign('content_id')->references('id')->on('contents')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_section_items');
    }
};
