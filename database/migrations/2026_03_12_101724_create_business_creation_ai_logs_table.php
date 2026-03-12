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
        Schema::create('business_creation_ai_logs', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('business_creation_session_id')->constrained()->onDelete('cascade');
            $table->string('type', 64)->comment('summary, insights, market_report, etc.');
            $table->text('request_payload')->nullable()->comment('Payload or prompt sent to AI');
            $table->text('response_payload')->nullable()->comment('Raw response from AI');
            $table->json('metadata')->nullable()->comment('Extra data: model, tokens, duration, etc.');
            $table->timestamps();

            $table->index(['business_creation_session_id', 'type'], 'bc_ai_logs_session_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_creation_ai_logs');
    }
};
