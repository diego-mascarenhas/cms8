<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paid_ad_audience_campaign', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('paid_ad_audience_id')->constrained()->cascadeOnDelete();
            $table->foreignId('paid_ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['paid_ad_audience_id', 'paid_ad_campaign_id'], 'paid_ad_audience_campaign_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paid_ad_audience_campaign');
    }
};
