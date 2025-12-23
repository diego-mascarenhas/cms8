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
        Schema::create('exchange_rates', function (Blueprint $table)
        {
            $table->id();
            $table->string('base_currency', 3)->index();
            $table->string('target_currency', 3)->index();
            $table->decimal('rate', 20, 8);
            $table->date('date')->index();
            $table->timestamp('fetched_at');
            $table->timestamps();

            // Ensure unique combination of currencies and date
            $table->unique(['base_currency', 'target_currency', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
