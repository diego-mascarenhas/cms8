<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Humano-specific registry for post types. WordPress registers these in code via
 * register_post_type(); Humano persists them per team to drive the admin UI and the
 * custom-field schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_types', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->string('name', 50);
            $table->string('label');
            $table->string('label_singular')->nullable();
            $table->string('icon')->nullable();
            $table->json('supports')->nullable();
            $table->boolean('hierarchical')->default(false);
            $table->boolean('has_archive')->default(false);
            $table->boolean('public')->default(true);
            $table->json('taxonomies')->nullable();
            $table->json('data')->nullable();
            $table->unsignedInteger('menu_order')->default(0);
            $table->timestamps();

            $table->unique(['team_id', 'name']);

            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_types');
    }
};
