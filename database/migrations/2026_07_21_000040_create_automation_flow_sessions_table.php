<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_flow_sessions', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('automation_id');
            $table->string('channel', 32);
            $table->string('external_key');
            $table->unsignedBigInteger('current_step_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['automation_id', 'channel', 'external_key'], 'automation_flow_sessions_unique');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('automation_id')->references('id')->on('automations')->onDelete('cascade');
            $table->foreign('current_step_id')->references('id')->on('automation_steps')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_flow_sessions');
    }
};
