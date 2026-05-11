<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_message', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('delay_minutes_after_previous')->nullable();
            $table->json('conditions')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'message_id']);
            $table->index(['campaign_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_message');
    }
};
