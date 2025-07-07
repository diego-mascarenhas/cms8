<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notification_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->onDelete('cascade');
            $table->string('event_type')->default('opened'); // opened, clicked, bounced, etc.
            $table->timestamp('tracked_at')->nullable(); // When the event occurred
            $table->string('ip_address', 45)->nullable(); // IP address of the user
            $table->text('user_agent')->nullable(); // User agent string
            $table->string('country', 2)->nullable(); // Country code
            $table->string('city', 100)->nullable(); // City name
            $table->json('metadata')->nullable(); // Additional tracking data
            $table->timestamps();

            // Indexes for better performance
            $table->index(['notification_id', 'event_type']);
            $table->index(['tracking_token']);
            $table->index(['tracked_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('notification_tracking');
    }
}; 