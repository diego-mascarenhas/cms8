<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEnterpriseSentimentsTable extends Migration
{
    public function up()
    {
        Schema::create('enterprise_sentiments', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name');
        });
    }

    public function down()
    {
        Schema::dropIfExists('enterprise_sentiments');
    }
}