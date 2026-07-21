<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->string('name');
            $table->string('slug');
            $table->string('kind', 32)->default('funnel');
            $table->boolean('is_active')->default(true);
            $table->string('entry_prompt_key')->nullable();
            $table->json('channels')->nullable();
            $table->string('public_token', 64)->nullable()->unique();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'slug']);
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->index(['team_id', 'is_active']);
            $table->index(['team_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automations');
    }
};
