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
            $table->decimal('price', 10, 2)->default(0.00);
            $table->foreignId('unit_id')->nullable()->constrained('units');
            $table->string('currency_code', 3)->default('EUR');
            $table->timestamps();
            
            // Foreign key constraint for currency
            $table->foreign('currency_code')->references('code')->on('currencies');
            
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
