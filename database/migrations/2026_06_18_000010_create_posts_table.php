<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WordPress-equivalent `wp_posts` table adapted for Humano multi-tenant CMS.
 * Column names mirror WordPress core to ease imports via the wp:import command.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table)
        {
            $table->id();

            // Multi-tenant + import bookkeeping (not present in WordPress core).
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('wp_id')->nullable();
            // Bidirectional WordPress sync tracking (last-write-wins).
            $table->dateTime('wp_modified_gmt')->nullable();
            $table->dateTime('synced_at')->nullable();

            // WordPress core columns.
            $table->unsignedBigInteger('post_author')->nullable();
            $table->dateTime('post_date')->nullable();
            $table->dateTime('post_date_gmt')->nullable();
            $table->longText('post_content')->nullable();
            $table->text('post_title')->nullable();
            $table->text('post_excerpt')->nullable();
            $table->string('post_status', 20)->default('publish');
            $table->string('comment_status', 20)->default('closed');
            $table->string('ping_status', 20)->default('closed');
            $table->string('post_password')->default('');
            $table->string('post_name')->default('');
            $table->unsignedBigInteger('post_parent')->default(0);
            $table->text('guid')->nullable();
            $table->integer('menu_order')->default(0);
            $table->string('post_type', 50)->default('post');
            $table->string('post_mime_type', 100)->default('');
            $table->unsignedBigInteger('comment_count')->default(0);
            $table->dateTime('post_modified')->nullable();
            $table->dateTime('post_modified_gmt')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'post_type', 'post_status']);
            $table->index(['team_id', 'post_name']);
            $table->index(['team_id', 'post_parent']);
            $table->unique(['team_id', 'wp_id']);

            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('post_author')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
