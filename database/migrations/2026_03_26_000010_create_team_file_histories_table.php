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
        Schema::create('team_file_histories', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_file_id')->nullable()->constrained('team_files')->nullOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('archived_media_id')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'team_file_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_file_histories');
    }
};
