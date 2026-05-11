<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_affiliate_commissions', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('paying_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('referrer_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('paying_enterprise_id')->nullable()->constrained('enterprises')->nullOnDelete();
            $table->foreignId('referrer_enterprise_id')->nullable()->constrained('enterprises')->nullOnDelete();
            $table->string('stripe_invoice_id')->unique();
            $table->unsignedBigInteger('amount_paid_cents');
            $table->char('currency', 3);
            $table->decimal('commission_percent', 8, 4);
            $table->unsignedBigInteger('commission_amount_cents');
            $table->timestamps();

            $table->index(['referrer_team_id', 'created_at']);
            $table->index(['paying_team_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_affiliate_commissions');
    }
};
