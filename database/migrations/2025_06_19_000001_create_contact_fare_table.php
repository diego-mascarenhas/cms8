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
        Schema::create('contact_fare', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->foreignId('fare_id')->constrained()->onDelete('cascade');
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('proficiency_level')->default(3)->comment('1-5 scale');
            $table->timestamps();
            
            // Prevent duplicate entries
            $table->unique(['contact_id', 'fare_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_fare');
    }
};
