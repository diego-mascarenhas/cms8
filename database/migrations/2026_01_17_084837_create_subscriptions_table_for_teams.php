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
        Schema::create('stripe_subscriptions', function (Blueprint $table)
        {
            $table->id();
            $table->string('stripe_id')->unique();
            $table->string('type', 10)->default('sell')->index();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('customer_id')->nullable()->index();
            $table->string('customer_email')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('customer_country', 2)->nullable();
            $table->string('customer_tax_id_type', 50)->nullable();
            $table->string('customer_tax_id', 255)->nullable();
            $table->string('status')->nullable()->index();
            $table->string('collection_method')->nullable();
            $table->string('plan_name')->nullable();
            $table->string('plan_interval')->nullable();
            $table->unsignedInteger('plan_interval_count')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('price_currency', 3)->default('usd');
            $table->decimal('unit_amount', 12, 2)->nullable();
            $table->decimal('amount_subtotal', 12, 2)->nullable();
            $table->decimal('amount_total', 12, 2)->nullable();
            $table->decimal('amount_usd', 12, 2)->nullable();
            $table->decimal('amount_ars', 12, 2)->nullable();
            $table->decimal('amount_eur', 12, 2)->nullable();
            $table->text('invoice_note')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_subscriptions');
    }
};
