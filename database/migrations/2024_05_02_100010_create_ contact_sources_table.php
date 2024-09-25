<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('contact_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('source_id');
            $table->string('value');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('source_id')->references('id')->on('sources')->onDelete('restrict');

        });
    }

    public function down()
    {
        Schema::dropIfExists('contact_sources');
    }
};