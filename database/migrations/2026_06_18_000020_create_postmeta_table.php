<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WordPress-equivalent `wp_postmeta` table. Stores arbitrary key/value metadata per post
 * (custom fields, i18n translations under the `_humano_*_{locale}` convention, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postmeta', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('post_id');
            $table->string('meta_key')->nullable();
            $table->longText('meta_value')->nullable();

            $table->index(['post_id', 'meta_key']);
            $table->index('team_id');

            $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postmeta');
    }
};
