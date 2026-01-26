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
        Schema::create('contents', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('section_category_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('template', 50)->nullable();
            $table->unsignedTinyInteger('order')->default(0);
            $table->unsignedTinyInteger('status')->default(3);
            $table->boolean('featured')->default(false);
            $table->boolean('featured_slide')->default(false);
            $table->boolean('featured_modal')->default(false);
            $table->json('data')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Campos multiidioma (usando JSON para compatibilidad)
            $table->json('title')->nullable();
            $table->json('subtitle')->nullable();
            $table->json('url')->nullable();
            $table->json('content')->nullable(); // Contenido principal
            $table->json('seo_title')->nullable();
            $table->json('seo_keywords')->nullable();
            $table->json('seo_description')->nullable();

            $table->index(['team_id', 'status']);
            $table->index(['section_category_id']);
            $table->index(['category_id']);
            $table->index(['featured', 'featured_slide', 'featured_modal']);

            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('section_category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contents');
    }
};
