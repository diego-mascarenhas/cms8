<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_billing_rates', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product', 32);
            $table->decimal('amount', 12, 6);
            $table->string('currency', 3)->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'product', 'effective_from'], 'tbr_team_product_from_idx');
            $table->index(['product', 'effective_from'], 'tbr_product_from_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_billing_rates');
    }
};
