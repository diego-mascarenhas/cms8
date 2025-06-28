<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->unsignedTinyInteger('source_id');
            $table->string('name');
            $table->string('value');

            $table->foreign('source_id')->references('id')->on('sources')->onDelete('restrict');

        });
    }

    public function down()
    {
        Schema::dropIfExists('channels');
    }
};
