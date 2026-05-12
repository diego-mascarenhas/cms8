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
        Schema::create('user_daily_performance_insights', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('insight_date');
            $table->decimal('performance_ratio', 5, 2)->default(0);
            $table->string('headline', 255);
            $table->text('focus');
            $table->text('message');
            $table->json('context_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'user_id', 'insight_date'], 'daily_perf_insights_team_user_date_uniq');
            $table->index(['team_id', 'insight_date'], 'daily_perf_insights_team_insight_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_daily_performance_insights');
    }
};
