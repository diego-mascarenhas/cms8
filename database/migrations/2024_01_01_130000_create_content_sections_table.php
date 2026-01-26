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
        Schema::create('content_sections', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('template', 50)->nullable();
            $table->unsignedTinyInteger('order')->default(0);
            $table->boolean('status')->default(true);
            $table->json('data')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->index(['category_id']);
            $table->index(['slug']);

            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
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
        Schema::dropIfExists('content_sections');
    }
};
