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
        Schema::create('wordpress_sync_pages', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('wp_id');
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('status', 20)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'wp_id']);
        });

        Schema::create('wordpress_sync_posts', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('wp_id');
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('status', 20)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'wp_id']);
        });

        Schema::create('wordpress_sync_products', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('wp_id');
            $table->string('name');
            $table->longText('description')->nullable();
            $table->string('price', 50)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('status', 20)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'wp_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wordpress_sync_products');
        Schema::dropIfExists('wordpress_sync_posts');
        Schema::dropIfExists('wordpress_sync_pages');
    }
};
