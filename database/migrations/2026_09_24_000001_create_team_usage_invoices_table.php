<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_usage_invoices', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('frequency', 16);
            $table->timestamp('period_from');
            $table->timestamp('period_to');
            $table->unsignedInteger('billed_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->string('stripe_invoice_id')->nullable();
            $table->string('status', 24)->default('draft');
            $table->foreignId('adjustment_id')->nullable()->constrained('team_usage_invoice_adjustments')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'period_from', 'period_to'], 'tui_team_period_unique');
            $table->index(['team_id', 'status'], 'tui_team_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_usage_invoices');
    }
};
