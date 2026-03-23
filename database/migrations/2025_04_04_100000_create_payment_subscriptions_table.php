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
        Schema::create('payment_subscriptions', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('provider', 50)->index(); // stripe, paypal, mercadopago, local
            $table->string('external_id')->nullable()->index();
            $table->string('status', 50)->nullable()->index(); // active, canceled, past_due, trialing, pending, expired
            $table->string('name')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_subscriptions');
    }
};
