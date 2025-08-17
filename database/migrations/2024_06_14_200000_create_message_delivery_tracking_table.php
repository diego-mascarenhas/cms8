<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_delivery_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_delivery_id')->constrained('message_deliveries')->onDelete('cascade');
            $table->string('event'); // opened, clicked, error, etc.
            $table->timestamp('tracked_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_delivery_tracking');
    }
};
