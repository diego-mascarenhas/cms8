<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('message_deliveries', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('contact_id');
            $table->string('smtp_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->integer('status_id')->default(0);
            $table->string('email_provider')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('delivery_status')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('complained_at')->nullable();
            $table->json('provider_data')->nullable();
            $table->text('error_message')->nullable();
            $table->string('error_type', 50)->nullable(); // 'smtp_error' | 'bounce' | 'complaint' | 'reject'
            $table->string('bounce_type', 50)->nullable(); // 'hard' | 'soft' | 'complaint' | 'block'
            $table->text('bounce_reason')->nullable();
            $table->timestamps();

            $table->index(['message_id', 'status_id']);
            $table->index(['message_id', 'created_at']); // Para ORDER BY en listados
            $table->index(['contact_id']);
            $table->index(['team_id']);
            $table->index(['sent_at']);
            $table->index(['opened_at']);
            $table->index(['clicked_at']);
            $table->index(['error_type']);
            $table->index(['bounce_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('message_deliveries');
    }
};
