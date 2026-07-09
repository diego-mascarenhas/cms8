<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paid_ad_audiences', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('type', 24)->default('saved');
            $table->json('targeting_rules')->nullable();
            $table->unsignedBigInteger('estimated_size')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'type'], 'paid_ad_audiences_team_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paid_ad_audiences');
    }
};
