<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_ratings', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('tiempo_respuesta')->comment('1-5 stars');
            $table->tinyInteger('atencion')->comment('1-5 stars');
            $table->tinyInteger('solucion')->comment('1-5 stars');
            $table->text('comentarios')->nullable();
            $table->timestamps();

            $table->unique(['ticket_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_ratings');
    }
};
