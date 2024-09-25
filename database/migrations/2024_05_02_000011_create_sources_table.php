<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name');
            $table->string('base_url');
            $table->string('icon');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sources');
    }
};