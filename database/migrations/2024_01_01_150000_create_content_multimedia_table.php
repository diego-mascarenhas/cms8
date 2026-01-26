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
        Schema::create('content_multimedia', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('content_id');
            $table->unsignedBigInteger('multimedia_id');
            $table->string('language', 2)->default('es');
            $table->unsignedTinyInteger('type')->nullable()->comment('1=imagen principal, 2=galería, 3=video, etc.');
            $table->unsignedTinyInteger('order')->default(0);
            $table->timestamps();

            $table->index(['content_id', 'language']);
            $table->index(['multimedia_id']);
            $table->index(['type', 'order']);

            $table->foreign('content_id')->references('id')->on('contents')->onDelete('cascade');
            $table->foreign('multimedia_id')->references('id')->on('multimedia')->onDelete('cascade');

            $table->unique(['content_id', 'multimedia_id', 'language', 'type'], 'content_multimedia_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_multimedia');
    }
};
