<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paid_ad_metric_snapshots', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('paid_ad_campaign_platform_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('spend', 12, 2)->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('cpc', 12, 4)->default(0);
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['paid_ad_campaign_platform_id', 'date'], 'paid_ad_metric_platform_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paid_ad_metric_snapshots');
    }
};
