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
        Schema::create('invoice_syncs', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50)->default('stripe');
            $table->string('external_id');

            $table->string('stripe_subscription_id')->nullable()->index();
            $table->string('customer_id')->nullable()->index();
            $table->string('customer_email')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('customer_description')->nullable();
            $table->string('customer_tax_id')->nullable();
            $table->string('customer_address_country', 3)->nullable();
            $table->string('number')->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->string('billing_reason')->nullable();
            $table->boolean('closed')->default(false);
            $table->string('currency', 3)->default('usd');
            $table->decimal('amount_due', 12, 2)->nullable();
            $table->decimal('amount_paid', 12, 2)->nullable();
            $table->decimal('amount_remaining', 12, 2)->nullable();
            $table->decimal('subtotal', 12, 2)->nullable();
            $table->decimal('tax', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->nullable();
            $table->decimal('total_discount_amount', 12, 2)->nullable();
            $table->text('applied_coupons')->nullable();
            $table->timestamp('invoice_created_at')->nullable()->index();
            $table->timestamp('invoice_due_date')->nullable()->index();
            $table->boolean('paid')->default(false)->index();
            $table->string('hosted_invoice_url')->nullable();
            $table->string('invoice_pdf')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'provider', 'external_id'], 'invoice_syncs_team_provider_external_unique');
            $table->index(['status', 'invoice_created_at']);
            $table->index(['customer_id', 'invoice_created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_syncs');
    }
};
