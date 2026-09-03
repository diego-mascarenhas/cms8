<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailer_usage_logs', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('source', 64)->default('app');
            $table->unsignedInteger('count')->default(1);
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['team_id', 'sent_at'], 'mul_team_sent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailer_usage_logs');
    }
};
