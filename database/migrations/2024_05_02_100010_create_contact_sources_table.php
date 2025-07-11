<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('contact_sources', function (Blueprint $table) {
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('source_id');
            $table->string('value');

            $table->foreign('source_id')->references('id')->on('sources')->onDelete('restrict');

            $table->unique(['contact_id', 'source_id', 'value']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('contact_sources');
    }
};
