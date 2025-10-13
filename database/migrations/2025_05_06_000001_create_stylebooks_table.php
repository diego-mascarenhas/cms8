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
        Schema::create('stylebooks', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('team_id')->constrained();
            $table->string('name', 255);
            $table->string('file', 255);
            $table->char('language', 2);
            $table->dateTime('date');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stylebooks');
    }
};
