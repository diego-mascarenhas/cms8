<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('go_links', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('code', 16)->unique();
            $table->string('type', 40);
            $table->string('target_url', 2048);
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('hits_count')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'type'], 'go_links_team_type_unique');
            $table->index('type');
        });

        Schema::create('go_link_hits', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('go_link_id')->constrained('go_links')->cascadeOnDelete();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('referer', 2048)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['go_link_id', 'created_at'], 'go_link_hits_link_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('go_link_hits');
        Schema::dropIfExists('go_links');
    }
};
