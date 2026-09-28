<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_work_plans', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_starts_on');
            $table->text('challenge')->nullable();
            $table->json('items');
            $table->json('review')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'user_id', 'week_starts_on'], 'weekly_work_plans_team_user_week_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_work_plans');
    }
};
