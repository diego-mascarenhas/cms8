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
            $table->string('source_language_code', 10)->nullable();
            $table->string('target_language_code', 10)->nullable();
            $table->decimal('price', 10, 2)->default(0.00);
            $table->foreignId('unit_id')->nullable()->constrained('units');
            $table->string('currency_code', 3)->default('EUR');
            $table->timestamps();

            // Foreign key constraints (nullable references)
            $table->foreign('source_language_code')->references('code')->on('language_variants')->nullOnDelete();
            $table->foreign('target_language_code')->references('code')->on('language_variants')->nullOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies');

            // Prevent duplicate entries for the same contact and fare (language combinations handled separately)
            $table->index(['contact_id', 'fare_id', 'source_language_code', 'target_language_code'], 'contact_fare_idx');
            
            // Unique constraint to prevent duplicates
            $table->unique(['contact_id', 'fare_id', 'source_language_code', 'target_language_code'], 'contact_fare_unique');
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
