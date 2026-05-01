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
        Schema::table('messages', function (Blueprint $table)
        {
            $table->json('send_allowed_weekdays')->nullable()->after('min_hours_between_emails');
            $table->string('send_window_start', 5)->nullable()->after('send_allowed_weekdays');
            $table->string('send_window_end', 5)->nullable()->after('send_window_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table)
        {
            $table->dropColumn([
                'send_allowed_weekdays',
                'send_window_start',
                'send_window_end',
            ]);
        });
    }
};
