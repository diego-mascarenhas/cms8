<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_stat_snapshots', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_social_connection_id')->nullable()->constrained('team_social_connections')->nullOnDelete();
            $table->unsignedTinyInteger('source_id');
            $table->string('metric', 64);
            $table->decimal('value_decimal', 20, 2)->nullable();
            $table->json('value_json')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->foreign('source_id')->references('id')->on('sources')->restrictOnDelete();
            $table->index(['team_id', 'source_id', 'metric', 'captured_at'], 'social_stat_snapshots_team_source_metric_captured_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_stat_snapshots');
    }
};
