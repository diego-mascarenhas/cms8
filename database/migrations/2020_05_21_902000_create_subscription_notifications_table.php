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
        Schema::create('subscription_notifications', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('subscription_id')->constrained('stripe_subscriptions')->cascadeOnDelete();
            $table->enum('notification_type', [
                'warning_5_days',
                'warning_2_days',
                'suspended',
                'reactivated',
            ]);
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('recipient_email');
            $table->string('recipient_name');
            $table->longText('body')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->integer('open_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'notification_type'], 'sub_notif_sub_type_idx');
            $table->index(['status', 'scheduled_at'], 'sub_notif_status_sched_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_notifications');
    }
};
