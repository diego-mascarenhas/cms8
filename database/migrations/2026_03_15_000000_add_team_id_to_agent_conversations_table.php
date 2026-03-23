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
        Schema::table('agent_conversations', function (Blueprint $table)
        {
            $table->foreignId('team_id')->nullable()->after('user_id')->constrained('teams')->nullOnDelete();
            $table->index(['team_id', 'user_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table)
        {
            $table->dropForeign(['team_id']);
            $table->dropIndex(['team_id', 'user_id', 'updated_at']);
        });
    }
};
