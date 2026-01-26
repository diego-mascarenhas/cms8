<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multimedia_gallery_items', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('multimedia_id');
            $table->unsignedBigInteger('tag_id');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['multimedia_id', 'tag_id']);
            $table->index(['tag_id', 'order']);

            $table->foreign('multimedia_id')->references('id')->on('multimedia')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multimedia_gallery_items');
    }
};
