<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_exports', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 50)->index();

            $table->string('external_id')->nullable()->index();
            $table->string('external_number')->nullable();
            $table->string('external_customer_id')->nullable();

            // pending | exported | failed | skipped | rectified
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error_message')->nullable();

            $table->string('payload_hash', 64)->nullable();
            $table->json('payload_snapshot')->nullable();
            $table->json('response_snapshot')->nullable();

            $table->timestamp('exported_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();

            $table->unique(['invoice_id', 'platform'], 'fiscal_exports_invoice_platform_unique');
            $table->index(['team_id', 'platform', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_exports');
    }
};
