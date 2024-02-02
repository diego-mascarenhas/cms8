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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('data')->nullable();
            $table->decimal('price', 15, 2);
            $table->unsignedTinyInteger('discount')->default(0);
            $table->tinyInteger('frequency')->unsigned();
            $table->unsignedTinyInteger('order');
            $table->unsignedTinyInteger('status')->default(0);
            $table->unsignedTinyInteger('services_type_id');

            $table->foreign('services_type_id')->references('id')->on('services_types')->onDelete('cascade');
            //$table->foreignId('user_id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
