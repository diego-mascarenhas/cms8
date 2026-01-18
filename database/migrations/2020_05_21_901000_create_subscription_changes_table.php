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
        Schema::create('subscription_changes', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('subscription_id')->constrained('stripe_subscriptions')->cascadeOnDelete();
            $table->string('source')->default('stripe');
            $table->json('changed_fields');
            $table->json('previous_values')->nullable();
            $table->json('current_values')->nullable();
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamps();

            $table->index(['subscription_id', 'detected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_changes');
    }
};
