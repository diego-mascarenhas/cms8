<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_passwords', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enterprise_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('username')->nullable();
            $table->text('password_encrypted')->nullable();
            $table->string('url')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'enterprise_id'], 'tp_team_ent_idx');
            $table->index(['team_id', 'name'], 'tp_team_name_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_passwords');
    }
};
