<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_sync_mappings', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('external_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['external_account_id', 'external_id'], 'task_sync_mappings_external_unique');
            $table->unique(['external_account_id', 'task_id'], 'task_sync_mappings_local_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_sync_mappings');
    }
};
