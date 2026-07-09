<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_platform_connections', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 32);
            $table->string('external_account_id')->nullable();
            $table->string('ad_account_id')->nullable();
            $table->string('ad_account_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 24)->default('pending_account');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'platform', 'ad_account_id'], 'ad_conn_team_platform_account_unique');
            $table->index(['team_id', 'platform'], 'ad_conn_team_platform_idx');
            $table->index(['team_id', 'status'], 'ad_conn_team_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_platform_connections');
    }
};
