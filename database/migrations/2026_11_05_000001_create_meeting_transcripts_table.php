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
            $table->string('external_ref')->nullable();
            $table->string('original_filename');
            $table->string('audio_path');
            $table->string('language', 8)->nullable();
            $table->string('status', 20)->default('pending');
            $table->longText('transcript')->nullable();
            $table->json('turns')->nullable();
            $table->json('summary')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
        });

        Schema::create('meeting_voice_profiles', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('provider_speaker_id')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'contact_id'], 'mvp_team_contact_unique');
            $table->index(['team_id', 'provider_speaker_id'], 'mvp_team_speaker_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_voice_profiles');
        Schema::dropIfExists('meeting_transcripts');
    }
};
