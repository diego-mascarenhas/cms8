<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paid_ad_usage_logs', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->index(['team_id', 'created_at'], 'paid_ad_usage_team_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paid_ad_usage_logs');
    }
};
