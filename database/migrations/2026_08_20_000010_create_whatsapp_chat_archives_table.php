<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_chat_archives', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 32);
            $table->timestamp('archived_at');
            $table->timestamps();

            $table->unique(['team_id', 'phone'], 'wa_chat_archive_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_chat_archives');
    }
};
