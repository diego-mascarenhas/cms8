<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WordPress-equivalent `wp_terms` table. Holds term names/slugs shared across taxonomies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('wp_id')->nullable();
            $table->string('name')->default('');
            $table->string('slug')->default('');
            $table->unsignedBigInteger('term_group')->default(0);

            $table->index(['team_id', 'slug']);
            $table->unique(['team_id', 'wp_id']);

            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
