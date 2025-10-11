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
        Schema::create('academy_lessons', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('chapter_id')->constrained('academy_chapters')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('video_url')->nullable();
            $table->string('video_path')->nullable(); // Para videos almacenados localmente
            $table->string('video_poster')->nullable();
            $table->integer('duration_minutes')->default(0);
            $table->integer('order')->default(0);
            $table->string('status')->default('published'); // draft, published, archived
            $table->timestamps();
            $table->softDeletes();

            $table->index(['chapter_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academy_lessons');
    }
};
