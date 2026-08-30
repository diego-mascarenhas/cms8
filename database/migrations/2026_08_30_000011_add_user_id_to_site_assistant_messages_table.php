<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_assistant_messages', function (Blueprint $table)
        {
            if (! Schema::hasColumn('site_assistant_messages', 'user_id'))
            {
                $table->unsignedBigInteger('user_id')->nullable()->after('contact_id');
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_assistant_messages', function (Blueprint $table)
        {
            if (Schema::hasColumn('site_assistant_messages', 'user_id'))
            {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
        });
    }
};
