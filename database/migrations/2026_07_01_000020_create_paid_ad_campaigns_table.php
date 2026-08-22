<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paid_ad_campaigns', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('objective', 32)->default('traffic');
            $table->string('status', 24)->default('draft');
            $table->string('budget_type', 16)->default('daily');
            $table->decimal('budget_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->json('targeting')->nullable();
            $table->json('creative')->nullable();
            $table->json('settings')->nullable();
            $table->foreignId('calendar_event_id')->nullable()->constrained('calendar_events')->nullOnDelete();
            $table->timestamps();

            $table->index(['team_id', 'status'], 'paid_ad_campaigns_team_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paid_ad_campaigns');
    }
};
