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
        Schema::create('contact_portfolios', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->year('year')->nullable();
            $table->text('notes')->nullable();
            $table->json('data')->nullable(); // For languages and position
            $table->timestamps();

            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->index('contact_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_portfolios');
    }
};
