<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('channel_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('channel_id');
            $table->string('param');
            $table->string('value');

            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::dropIfExists('channel_configs');
    }
};
