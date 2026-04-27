<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_runs', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('external_account_id')->constrained()->cascadeOnDelete();
            $table->string('resource', 64);
            $table->string('status', 32)->default('success');
            $table->unsignedInteger('pulled_count')->default(0);
            $table->unsignedInteger('upserted_count')->default(0);
            $table->unsignedInteger('deleted_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['external_account_id', 'resource', 'status'], 'sync_runs_account_resource_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
    }
};
