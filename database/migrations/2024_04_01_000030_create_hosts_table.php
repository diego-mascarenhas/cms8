<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hosts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('host')->nullable()->unique();
            $table->string('user')->nullable();
            $table->string('password')->nullable();
            $table->string('private_ip')->nullable()->unique();
            $table->string('public_ip')->nullable()->unique();
            $table->json('data')->nullable();
            $table->string('power_state')->nullable();
            $table->string('connection_state')->nullable();
            $table->unsignedTinyInteger('type_id')->nullable();
            $table->unsignedTinyInteger('private_connection_id')->nullable();
            $table->unsignedTinyInteger('public_connection_id')->nullable();
            $table->timestamps();

            $table->foreign('type_id')->references('id')->on('host_types')->onDelete('set null');
            $table->foreign('private_connection_id')->references('id')->on('network_devices')->onDelete('set null');
            $table->foreign('public_connection_id')->references('id')->on('network_devices')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('hosts');
    }
};
