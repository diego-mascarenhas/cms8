<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_password_shares', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_password_id')->constrained('team_passwords')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->unsignedInteger('max_views')->default(1);
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['expires_at', 'consumed_at'], 'tps_exp_cons_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_password_shares');
    }
};
