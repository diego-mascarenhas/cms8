<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_snapshots', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('key', 128)->nullable();
            $table->json('payload');
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['team_id', 'type', 'captured_at']);
            $table->index(['team_id', 'type', 'key', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_snapshots');
    }
};
