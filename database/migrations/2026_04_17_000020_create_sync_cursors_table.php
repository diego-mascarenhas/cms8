<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_cursors', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('external_account_id')->constrained()->cascadeOnDelete();
            $table->string('resource', 64);
            $table->text('cursor')->nullable();
            $table->timestamp('full_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['external_account_id', 'resource'], 'sync_cursors_account_resource_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_cursors');
    }
};
