<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_social_connections', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('source_id')->nullable();
            $table->string('provider', 32);
            $table->string('external_account_id')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->text('aux_secret')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->foreign('source_id')->references('id')->on('sources')->nullOnDelete();
            $table->unique(['team_id', 'provider'], 'team_social_connections_team_provider_unique');
            $table->index(['team_id', 'status'], 'team_social_connections_team_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_social_connections');
    }
};
