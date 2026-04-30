<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 40);
            $table->string('status', 40);
            $table->string('summary')->nullable();
            $table->unsignedInteger('sends_count')->nullable();
            $table->decimal('opened_rate', 5, 2)->nullable()->comment('Percentage 0-100');
            $table->decimal('clicked_rate', 5, 2)->nullable();
            $table->decimal('unsubscribed_rate', 5, 2)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'type']);
            $table->index(['team_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
