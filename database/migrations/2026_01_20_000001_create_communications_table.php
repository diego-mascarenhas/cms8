<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 32);
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone', 32)->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('status', 32)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status', 'created_at'], 'comm_team_status_created_idx');
            $table->index(['team_id', 'channel'], 'comm_team_channel_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
