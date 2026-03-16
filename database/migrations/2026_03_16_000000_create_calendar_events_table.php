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
        Schema::create('calendar_events', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->dateTime('start');
            $table->dateTime('end');
            $table->boolean('all_day')->default(false);
            $table->text('notes')->nullable();
            $table->string('url')->nullable();
            $table->string('label')->nullable();
            $table->string('google_event_id')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'start', 'end'], 'calendar_events_team_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
