<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_enterprise_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('enterprise_id');
            $table->string('action');
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->integer('duration_seconds')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('enterprise_id')->references('id')->on('enterprises')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_enterprise_actions');
    }
};