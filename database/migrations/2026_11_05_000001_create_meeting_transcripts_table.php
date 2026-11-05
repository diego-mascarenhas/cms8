<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_transcripts', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('original_filename');
            $table->string('audio_path');
            $table->string('language', 8)->nullable();
            $table->string('status', 20)->default('pending');
            $table->longText('transcript')->nullable();
            $table->json('turns')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_transcripts');
    }
};
