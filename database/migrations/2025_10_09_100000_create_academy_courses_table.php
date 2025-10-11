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
        Schema::create('academy_courses', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('long_description')->nullable();
            $table->string('instructor_name');
            $table->string('instructor_title')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->string('skill_level')->default('All Levels');
            $table->integer('students_count')->default(0);
            $table->string('language', 2)->default('es');
            $table->boolean('has_captions')->default(false);
            $table->string('thumbnail')->nullable();
            $table->string('status')->default('draft'); // draft, published, archived
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->foreign('language')->references('code')->on('languages')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academy_courses');
    }
};
