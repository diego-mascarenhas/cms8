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
        Schema::create('subscription_products', function (Blueprint $table)
        {
            $table->id();
            $table->string('stripe_id')->nullable()->unique()->index();
            $table->string('stripe_product')->nullable()->index();
            $table->string('stripe_price')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->string('category')->nullable()->index();
            $table->string('plan')->nullable();
            $table->string('type')->nullable();
            $table->string('currency', 3)->default('usd');
            $table->decimal('unit_amount', 12, 2)->nullable();
            $table->string('recurring_interval')->nullable();
            $table->unsignedInteger('recurring_interval_count')->nullable()->default(1);
            $table->json('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_products');
    }
};
