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
        Schema::create('software', function (Blueprint $table) {
            $table->unsignedSmallInteger('id', true);
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name', 255);
            $table->unsignedSmallInteger('type_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('type_id')->references('id')->on('software_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('software');
    }
}; 