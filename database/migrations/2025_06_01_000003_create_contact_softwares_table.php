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
        Schema::create('contact_softwares', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->unsignedSmallInteger('software_id');
            $table->string('proficiency_level')->nullable(); // beginner, intermediate, advanced, expert
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('software_id')->references('id')->on('software')->onDelete('cascade');

            // Prevent duplicate entries
            $table->unique(['contact_id', 'software_id']);

            // Add indexes for better performance
            $table->index(['contact_id']);
            $table->index(['software_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_softwares');
    }
};
