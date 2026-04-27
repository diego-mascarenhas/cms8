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
        Schema::create('exchange_rate_histories', function (Blueprint $table)
        {
            $table->id();
            $table->string('base_currency', 3);
            $table->string('target_currency', 3);
            $table->date('rate_month')->comment('First day of the month (UTC calendar month)');
            $table->decimal('rate', 20, 8);
            $table->timestamp('fetched_at');
            $table->string('provider')->default('currencyfreaks');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['base_currency', 'target_currency', 'rate_month'], 'exchange_rate_histories_pair_month_unique');
            $table->index(['target_currency', 'rate_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rate_histories');
    }
};
