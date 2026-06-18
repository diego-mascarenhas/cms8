<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WordPress-equivalent `wp_term_relationships` table. Many-to-many pivot between
 * posts (object_id) and term_taxonomy rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term_relationships', function (Blueprint $table)
        {
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('object_id');
            $table->unsignedBigInteger('term_taxonomy_id');
            $table->integer('term_order')->default(0);

            $table->primary(['object_id', 'term_taxonomy_id']);
            $table->index('term_taxonomy_id');
            $table->index('team_id');

            $table->foreign('object_id')->references('id')->on('posts')->onDelete('cascade');
            $table->foreign('term_taxonomy_id')->references('id')->on('term_taxonomy')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_relationships');
    }
};
