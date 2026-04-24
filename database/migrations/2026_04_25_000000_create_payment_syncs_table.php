<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_syncs', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50)->default('stripe');
            $table->string('external_id');

            $table->string('customer_id')->nullable()->index();
            $table->string('customer_email')->nullable()->index();
            $table->string('status', 32)->nullable()->index();
            $table->string('currency', 3)->default('usd');
            $table->unsignedInteger('amount_cents')->default(0);
            $table->unsignedInteger('amount_refunded_cents')->default(0);
            $table->integer('amount_net_cents')->default(0);
            $table->string('invoice_external_id')->nullable()->index();
            $table->text('description')->nullable();
            $table->timestamp('charge_created_at')->nullable()->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'provider', 'external_id'], 'payment_syncs_team_provider_external_unique');
            $table->index(['status', 'charge_created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_syncs');
    }
};
