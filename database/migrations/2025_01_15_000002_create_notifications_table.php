<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('type_id')->constrained('notification_types')->onDelete('cascade');
            $table->foreignId('contact_id')->constrained('contacts')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Who sent the notification
            $table->string('reference')->nullable()->index(); // Project ID, Task ID, etc.
            $table->string('subject');
            $table->text('message');
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->json('sent_data')->nullable(); // Store email response, etc.
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->json('metadata')->nullable(); // Additional data
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['team_id', 'contact_id']);
            $table->index(['team_id', 'type_id']);
            $table->index(['team_id', 'reference']);
            $table->index(['sent_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
    }
}; 