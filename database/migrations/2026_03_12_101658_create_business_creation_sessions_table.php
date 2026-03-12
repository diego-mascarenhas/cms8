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
        Schema::create('business_creation_sessions', function (Blueprint $table)
        {
            $table->id();
            $table->string('token', 64)->unique()->nullable()->comment('Public link token for landing (resume without auth)');
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('config')->nullable()->comment('Form data (same structure as business_config in settings)');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'created_at']);
            $table->index('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_creation_sessions');
    }
};
