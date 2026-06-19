<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WordPress-equivalent `wp_term_taxonomy` table. Assigns a term to a taxonomy
 * (e.g. category, post_tag) with optional hierarchy and a denormalized usage count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term_taxonomy', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('term_id');
            $table->string('taxonomy', 50)->default('');
            $table->longText('description')->nullable();
            $table->unsignedBigInteger('parent')->default(0);
            $table->unsignedBigInteger('count')->default(0);

            $table->index(['team_id', 'taxonomy']);
            $table->unique(['term_id', 'taxonomy']);

            $table->foreign('term_id')->references('id')->on('terms')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_taxonomy');
    }
};
