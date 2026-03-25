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
            $table->string('assistant_tool_flow_routing_key', 191)->nullable()->after('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table)
        {
            $table->dropColumn('assistant_tool_flow_routing_key');
        });
    }
};
