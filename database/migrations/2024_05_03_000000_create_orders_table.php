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
        Schema::create('orders', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('order_number')->unique();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->decimal('total_amount', 10, 2)->unsigned()->default(0);
            $table->unsignedInteger('currency_id')->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded', 'cancelled'])->default('pending');
            $table->enum('delivery_status', ['processing', 'dispatched', 'delivered', 'out_for_delivery', 'cancelled'])->default('processing');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');

            // Indexes for better performance
            $table->index(['team_id', 'payment_status']);
            $table->index(['team_id', 'delivery_status']);
            $table->index(['team_id', 'store_id']);
            $table->index('order_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
