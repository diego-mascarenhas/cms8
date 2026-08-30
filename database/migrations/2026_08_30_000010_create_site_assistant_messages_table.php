<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_assistant_messages', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('automation_id');
            $table->unsignedBigInteger('session_id')->nullable();
            $table->string('session_key', 191);
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('role', 16);
            $table->text('body');
            $table->timestamps();

            $table->index(['team_id', 'session_key', 'id'], 'sam_team_session_idx');
            $table->index(['team_id', 'created_at'], 'sam_team_created_idx');
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->foreign('automation_id')->references('id')->on('automations')->cascadeOnDelete();
            $table->foreign('session_id')->references('id')->on('automation_flow_sessions')->nullOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_assistant_messages');
    }
};
