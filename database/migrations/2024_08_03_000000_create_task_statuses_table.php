<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('task_statuses', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name');
            $table->string('label_class');
            $table->integer('order')->default(0);
        });
    }

    public function down()
    {
        Schema::dropIfExists('task_statuses');
    }
};
