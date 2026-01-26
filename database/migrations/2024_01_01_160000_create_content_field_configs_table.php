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
        Schema::create('content_field_configs', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('section_category_id');
            $table->string('field_key');
            $table->string('field_type', 50);
            $table->string('field_label');
            $table->json('field_options')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('order')->default(0);
            $table->boolean('required')->default(false);
            $table->timestamps();

            $table->index(['team_id', 'section_category_id']);
            $table->index(['is_active', 'order']);

            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('section_category_id')->references('id')->on('categories')->onDelete('cascade');

            $table->unique(['section_category_id', 'field_key'], 'content_field_config_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_field_configs');
    }
};
