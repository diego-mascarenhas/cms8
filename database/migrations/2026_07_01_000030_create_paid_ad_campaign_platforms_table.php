<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paid_ad_campaign_platforms', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('paid_ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_platform_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 32);
            $table->string('external_campaign_id')->nullable();
            $table->string('publish_status', 24)->default('pending');
            $table->text('publish_error')->nullable();
            $table->json('platform_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['paid_ad_campaign_id', 'platform'], 'paid_ad_campaign_platform_unique');
            $table->index('platform', 'paid_ad_campaign_platforms_platform_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paid_ad_campaign_platforms');
    }
};
