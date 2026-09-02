<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_usage_invoice_adjustments', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('frequency', 16);
            $table->timestamp('period_from');
            $table->timestamp('period_to');
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'invoiced_at'], 'tuia_team_invoiced_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_usage_invoice_adjustments');
    }
};
