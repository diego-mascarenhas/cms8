<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('prompt_types', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name');
        });
    }

    public function down()
    {
        Schema::dropIfExists('prompt_types');
    }
};
