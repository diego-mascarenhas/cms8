<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_billing_histories', function (Blueprint $table)
        {
            $table->id();
            $table->string('base_currency', 3)->default('USD');
            $table->date('rate_month')->comment('First day of the month (UTC calendar month)');
            $table->decimal('amount_per_million', 12, 4);
            $table->decimal('markup_percent', 8, 2);
            $table->decimal('sell_rate', 12, 4);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['base_currency', 'rate_month'], 'token_billing_histories_base_month_unique');
            $table->index(['base_currency', 'rate_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_billing_histories');
    }
};
