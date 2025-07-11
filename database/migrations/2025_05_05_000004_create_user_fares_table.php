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
        Schema::create('user_fares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('language_origin_id', 2);
            $table->string('language_destination_id', 2);
            $table->foreignId('fare_id')->constrained('fares');
            $table->string('currency_id', 3);
            $table->decimal('amount', 10, 2);
            $table->boolean('negotiable')->default(false);
            $table->timestamps();

            $table->foreign('language_origin_id')->references('code')->on('languages');
            $table->foreign('language_destination_id')->references('code')->on('languages');
            $table->foreign('currency_id')->references('code')->on('currencies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_fares');
    }
};
