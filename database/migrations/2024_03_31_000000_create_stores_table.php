<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->string('name');
            $table->string('code', 64)->nullable();
            $table->string('address')->nullable();
            $table->json('data')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('is_main')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'name']);
            $table->unique(['team_id', 'code']);
            $table->index(['team_id', 'status']);
            $table->index(['team_id', 'is_main']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
